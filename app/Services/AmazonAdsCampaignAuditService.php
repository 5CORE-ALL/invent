<?php

namespace App\Services;

use App\Models\AmazonAdsCampaignAudit;
use App\Models\AmazonAdsLiveSyncState;
use App\Services\Ai\AiJsonClient;
use App\Support\AmazonAdsCampaignSkuMetrics;
use App\Support\AmazonAdsDesiredSbgtResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Audit button on /amazon-ads/all: collects what we know about one campaign (reports, budget, bid, stock, price,
 * rating, page views, keywords) and asks the AI why it is not getting enough clicks.
 * The result is stored per campaign so the dot beside the button can show it any time.
 */
class AmazonAdsCampaignAuditService
{
    private const REPORT_TABLES = [
        'sp' => 'amazon_sp_campaign_reports',
        'sb' => 'amazon_sb_campaign_reports',
    ];

    private const REPORT_COLUMNS = [
        'report_date_range', 'campaignStatus', 'campaignBudgetAmount', 'impressions', 'clicks', 'cost', 'spend',
        'costPerClick', 'clickThroughRate', 'purchases30d', 'purchases', 'sales30d', 'sales',
        'topOfSearchImpressionShare', 'campaignBiddingStrategy',
    ];

    private const SEVERITIES = ['high', 'medium', 'low', 'ok'];

    /** Aspects that get their own dot column on the grid. Anything else the AI reports is category "other". */
    public const ASPECTS = ['budget', 'bid', 'keywords', 'stock', 'listing'];

    private const IMPACT_RANK = ['ok' => 0, 'low' => 1, 'medium' => 2, 'high' => 3];

    /** A "running" audit older than this is treated as dead (the request was cut off). */
    public const STALE_RUNNING_MINUTES = 10;

    public function __construct(private readonly AiJsonClient $ai) {}

    /**
     * Audit one campaign and store the result.
     */
    public function audit(string $channel, string $campaignId, string $campaignName = '', ?string $requestedBy = null): AmazonAdsCampaignAudit
    {
        AmazonAdsCampaignAudit::ensureTable();
        $channel = $channel === 'sb' ? 'sb' : 'sp';

        $row = AmazonAdsCampaignAudit::query()->firstOrNew(['channel' => $channel, 'campaign_id' => $campaignId]);
        $row->fill([
            'campaign_name' => $campaignName !== '' ? $campaignName : $row->campaign_name,
            'status' => AmazonAdsCampaignAudit::STATUS_RUNNING,
            'error' => null,
            'requested_by' => $requestedBy,
            'started_at' => now(),
            'finished_at' => null,
        ])->save();

        try {
            $facts = $this->collectFacts($channel, $campaignId, (string) $row->campaign_name);
            [$report, $error, $provider] = $this->ai->ask($this->systemPrompt(), $this->userPrompt($facts));
            if ($report === null) {
                throw new \RuntimeException($error);
            }
            $report = $this->normalizeReport($report);

            $row->fill([
                'status' => AmazonAdsCampaignAudit::STATUS_DONE,
                'severity' => $report['severity'],
                'summary' => $report['verdict'],
                'report' => $report,
                'facts' => $facts,
                'provider' => $provider,
                'error' => null,
                'finished_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('amazon-ads campaign audit failed', [
                'channel' => $channel,
                'campaign_id' => $campaignId,
                'error' => $e->getMessage(),
            ]);
            $row->fill([
                'status' => AmazonAdsCampaignAudit::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 1000),
                'finished_at' => now(),
            ])->save();
        }

        return $row->refresh();
    }

    /**
     * What the dot shows for a campaign.
     *
     * @param  list<string>  $campaignIds
     * @return array<string, array{channel: string, status: string, severity: ?string, verdict: ?string, at: ?string, error: ?string, aspects: ?array<string, string>}>
     */
    public function statuses(array $campaignIds): array
    {
        if ($campaignIds === [] || ! Schema::hasTable(AmazonAdsCampaignAudit::TABLE)) {
            return [];
        }
        $out = [];
        foreach (array_chunk($campaignIds, 500) as $chunk) {
            $rows = AmazonAdsCampaignAudit::query()
                ->whereIn('campaign_id', $chunk)
                ->orderBy('updated_at')
                ->get(['channel', 'campaign_id', 'status', 'severity', 'summary', 'report', 'error', 'started_at', 'finished_at']);
            foreach ($rows as $r) {
                $out[(string) $r->campaign_id] = $this->statusPayload($r);
            }
        }

        return $out;
    }

    /**
     * @return array{channel: string, status: string, severity: ?string, verdict: ?string, at: ?string, error: ?string, aspects: ?array<string, string>}
     */
    public function statusPayload(AmazonAdsCampaignAudit $r): array
    {
        $status = (string) $r->status;
        $error = $r->error;
        if ($status === AmazonAdsCampaignAudit::STATUS_RUNNING
            && $r->started_at !== null
            && $r->started_at->lt(now()->subMinutes(self::STALE_RUNNING_MINUTES))) {
            $status = AmazonAdsCampaignAudit::STATUS_FAILED;
            $error = 'The audit did not finish. Run it again.';
        }

        return [
            'channel' => (string) $r->channel,
            'status' => $status,
            'severity' => $r->severity,
            'verdict' => $r->summary,
            'at' => $r->finished_at?->toIso8601String(),
            'error' => $error,
            'aspects' => $status === AmazonAdsCampaignAudit::STATUS_DONE ? $this->aspectLevels($r->report) : null,
        ];
    }

    /**
     * Worst impact per aspect (budget / bid / keywords / stock / listing) from the stored report:
     * ok = audited and nothing found, low / medium / high = worst reason in that aspect.
     * Null for reports saved before reasons had a category: run the audit again to fill the aspect dots.
     *
     * @param  mixed  $report
     * @return array<string, string>|null
     */
    private function aspectLevels(mixed $report): ?array
    {
        if (! is_array($report) || ! isset($report['reasons']) || ! is_array($report['reasons'])) {
            return null;
        }
        $reasons = array_values(array_filter($report['reasons'], 'is_array'));
        $hasCategory = false;
        foreach ($reasons as $r) {
            if (isset($r['category'])) {
                $hasCategory = true;
                break;
            }
        }
        if ($reasons !== [] && ! $hasCategory) {
            return null;
        }
        $out = array_fill_keys(self::ASPECTS, 'ok');
        foreach ($reasons as $r) {
            $cat = (string) ($r['category'] ?? 'other');
            if (! isset($out[$cat])) {
                continue;
            }
            $impact = (string) ($r['impact'] ?? 'medium');
            if ((self::IMPACT_RANK[$impact] ?? 2) > self::IMPACT_RANK[$out[$cat]]) {
                $out[$cat] = isset(self::IMPACT_RANK[$impact]) ? $impact : 'medium';
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectFacts(string $channel, string $campaignId, string $campaignName): array
    {
        $facts = [
            'campaign' => ['id' => $campaignId, 'name' => $campaignName, 'channel' => strtoupper($channel)],
            'today' => Carbon::now('America/Los_Angeles')->toDateString(),
        ];

        $facts['reports'] = $this->reportRanges($channel, $campaignId, $campaignName);
        $facts['daily_last_14'] = $this->dailyTrend($channel, $campaignId);
        $facts['live_bid_budget'] = $this->liveSyncStates($channel, $campaignId);

        if ($campaignName !== '') {
            $facts['product'] = $this->productMetrics($channel, $campaignId, $campaignName);
        }
        if ($channel === 'sp') {
            $facts['keywords_l30'] = $this->keywordSummary($campaignId);
        }
        $facts['signals'] = $this->signals($facts);

        return $facts;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function reportRanges(string $channel, string $campaignId, string $campaignName): array
    {
        $table = self::REPORT_TABLES[$channel];
        if (! Schema::hasTable($table)) {
            return [];
        }
        $cols = array_values(array_filter(self::REPORT_COLUMNS, static fn (string $c): bool => Schema::hasColumn($table, $c)));
        $out = [];
        foreach (['L1', 'L7', 'L30'] as $range) {
            try {
                $q = DB::table($table)->where('report_date_range', $range);
                if (Schema::hasColumn($table, 'campaign_id')) {
                    $q->where('campaign_id', $campaignId);
                } else {
                    $q->where('campaignName', $campaignName);
                }
                $r = $q->orderByDesc('id')->first($cols);
            } catch (\Throwable) {
                $r = null;
            }
            if ($r !== null) {
                $out[$range] = $this->cleanRow((array) $r, ['report_date_range']);
            }
        }

        return $out;
    }

    /**
     * Last 14 daily rows (impressions / clicks / spend per day) - shows when clicks started to fall.
     *
     * @return list<array<string, mixed>>
     */
    private function dailyTrend(string $channel, string $campaignId): array
    {
        $table = self::REPORT_TABLES[$channel];
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'campaign_id')) {
            return [];
        }
        $cols = array_values(array_filter(
            ['report_date_range', 'campaignStatus', 'campaignBudgetAmount', 'impressions', 'clicks', 'cost', 'spend', 'costPerClick'],
            static fn (string $c): bool => Schema::hasColumn($table, $c)
        ));
        try {
            $rows = DB::table($table)
                ->where('campaign_id', $campaignId)
                ->whereRaw("LEFT(TRIM(report_date_range), 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
                ->orderByDesc('report_date_range')
                ->limit(14)
                ->get($cols);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $row = $this->cleanRow((array) $r, []);
            $row['date'] = substr((string) ($row['report_date_range'] ?? ''), 0, 10);
            unset($row['report_date_range']);
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function liveSyncStates(string $channel, string $campaignId): array
    {
        try {
            $rows = AmazonAdsLiveSyncState::query()
                ->where('channel', $channel)
                ->where('campaign_id', $campaignId)
                ->get(['field', 'desired_value', 'live_value', 'status', 'reason']);
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(static fn ($r): array => [
            'field' => (string) $r->field,
            'our_value' => $r->desired_value !== null ? (float) $r->desired_value : null,
            'live_on_amazon' => $r->live_value !== null ? (float) $r->live_value : null,
            'sync_status' => (string) $r->status,
            'reason' => $r->reason,
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function productMetrics(string $channel, string $campaignId, string $campaignName): array
    {
        $out = [];
        try {
            $m = AmazonAdsCampaignSkuMetrics::mapForCampaignNames([$campaignName])[$campaignName] ?? [];
            foreach (['sku' => 'sku', 'inv' => 'inventory', 'dil' => 'dil_percent', 'ovl30' => 'units_sold_l30', 'price' => 'price', 'lmp_price' => 'lowest_market_price', 'rating' => 'rating', 'review_count' => 'review_count'] as $from => $to) {
                if (isset($m[$from]) && $m[$from] !== '') {
                    $out[$to] = is_numeric($m[$from]) ? round((float) $m[$from], 2) : $m[$from];
                }
            }
        } catch (\Throwable) {
        }
        try {
            $cvr = AmazonAdsCampaignSkuMetrics::parentListingCvrForCampaignNames([$campaignName])[$campaignName] ?? [];
            foreach (['page_cvr' => 'listing_cvr_percent_l30', 'sess30' => 'page_views_l30', 'sess7' => 'page_views_l7'] as $from => $to) {
                if (isset($cvr[$from]) && is_numeric($cvr[$from])) {
                    $out[$to] = round((float) $cvr[$from], 2);
                }
            }
        } catch (\Throwable) {
        }
        try {
            $sbgt = AmazonAdsDesiredSbgtResolver::sbgtForCampaigns(
                [['campaign_id' => $campaignId, 'campaignName' => $campaignName]],
                $channel
            );
            if (isset($sbgt[$campaignId]) && is_numeric($sbgt[$campaignId])) {
                $out['budget_our_rule_sbgt'] = (int) $sbgt[$campaignId];
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function keywordSummary(string $campaignId): array
    {
        $table = 'amazon_sp_keyword_reports';
        if (! Schema::hasTable($table)) {
            return [];
        }
        try {
            $rows = DB::table($table)
                ->where('campaign_id', $campaignId)
                ->where('report_date_range', 'L30')
                ->get(['keyword', 'matchType', 'adKeywordStatus', 'impressions', 'clicks', 'cost', 'costPerClick']);
        } catch (\Throwable) {
            return [];
        }
        if ($rows->isEmpty()) {
            return ['keywords_in_report' => 0];
        }
        $zeroImpr = 0;
        $impNoClick = 0;
        foreach ($rows as $r) {
            $imp = (float) ($r->impressions ?? 0);
            $clk = (float) ($r->clicks ?? 0);
            if ($imp <= 0) {
                $zeroImpr++;
            } elseif ($clk <= 0) {
                $impNoClick++;
            }
        }
        $top = $rows->sortByDesc(static fn ($r) => (float) ($r->impressions ?? 0))->take(12)->map(static fn ($r): array => [
            'keyword' => (string) $r->keyword,
            'match' => (string) $r->matchType,
            'state' => (string) $r->adKeywordStatus,
            'impressions' => (int) ($r->impressions ?? 0),
            'clicks' => (int) ($r->clicks ?? 0),
            'cpc' => $r->costPerClick !== null ? round((float) $r->costPerClick, 2) : null,
        ])->values()->all();

        $out = [
            'keywords_in_report' => $rows->count(),
            'with_zero_impressions' => $zeroImpr,
            'with_impressions_but_no_clicks' => $impNoClick,
            'top_by_impressions' => $top,
        ];
        try {
            if (Schema::hasTable('amazon_sp_negative_keywords') && Schema::hasColumn('amazon_sp_negative_keywords', 'campaign_id')) {
                $out['negative_keywords_on_campaign'] = (int) DB::table('amazon_sp_negative_keywords')->where('campaign_id', $campaignId)->count();
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /**
     * Plain-number observations computed by us, so the AI does not have to do arithmetic.
     *
     * @param  array<string, mixed>  $facts
     * @return list<string>
     */
    private function signals(array $facts): array
    {
        $s = [];
        $reports = $facts['reports'] ?? [];
        foreach (['L1' => 1, 'L7' => 7, 'L30' => 30] as $range => $days) {
            $r = $reports[$range] ?? null;
            if (! is_array($r)) {
                continue;
            }
            $imp = (float) ($r['impressions'] ?? 0);
            $clk = (float) ($r['clicks'] ?? 0);
            $spend = (float) ($r['cost'] ?? $r['spend'] ?? 0);
            $budget = (float) ($r['campaignBudgetAmount'] ?? 0);
            $s[] = "{$range}: impressions {$imp}, clicks {$clk}, spend \${$spend}".($imp > 0 ? ', CTR '.round($clk / $imp * 100, 2).'%' : '').($clk > 0 ? ', avg CPC $'.round($spend / $clk, 2) : '');
            if ($budget > 0) {
                $s[] = "{$range}: spent ".round($spend / ($budget * $days) * 100, 1)."% of the daily budget \${$budget} x {$days} day(s)";
            }
        }
        if ($reports === []) {
            $s[] = 'No L1 / L7 / L30 report rows were found for this campaign.';
        }
        $daily = $facts['daily_last_14'] ?? [];
        if (count($daily) >= 4) {
            $recent = array_slice($daily, 0, 3);
            $older = array_slice($daily, 3);
            $avg = static fn (array $rows): float => $rows === [] ? 0.0 : array_sum(array_map(static fn ($r) => (float) ($r['clicks'] ?? 0), $rows)) / count($rows);
            $s[] = 'Average clicks per day: last 3 days '.round($avg($recent), 1).', the 11 days before '.round($avg($older), 1);
        }
        $p = $facts['product'] ?? [];
        if (isset($p['inventory']) && (float) $p['inventory'] <= 0) {
            $s[] = 'Inventory is 0 or below: Amazon will not show the ad.';
        }
        if (isset($p['rating']) && (float) $p['rating'] > 0 && (float) $p['rating'] < 4.0) {
            $s[] = 'Rating is below 4.0: lowers click-through and conversion.';
        }
        if (isset($p['price'], $p['lowest_market_price']) && (float) $p['lowest_market_price'] > 0 && (float) $p['price'] > (float) $p['lowest_market_price'] * 1.1) {
            $s[] = 'Our price is more than 10% above the lowest market price.';
        }

        return $s;
    }

    private function systemPrompt(): string
    {
        return 'You are a senior Amazon Advertising analyst for a seller. You audit one campaign at a time. '
            .'Use ONLY the data you are given; never invent numbers. If a data point is missing, say it is missing instead of guessing. '
            .'Answer in plain, short English that a non-expert can act on. Return JSON only, no markdown.';
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function userPrompt(array $facts): string
    {
        return "Question: what could be the possible reasons this campaign is not getting enough clicks?\n\n"
            ."Campaign data (JSON):\n".json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)."\n\n"
            ."Rank the reasons from most to least likely. For each one give the evidence (cite the numbers above), one concrete fix, and a category: \n"
            ."- budget: daily budget too low or runs out early\n"
            ."- bid: bid / CPC too low versus what others pay, bidding strategy\n"
            ."- keywords: too few or weak keywords / targets, match types, negatives blocking traffic\n"
            ."- stock: ad paused or not eligible, no or low inventory, buy box\n"
            ."- listing: price, rating / reviews, page views, conversion, title / images\n"
            ."- other: anything else, such as a sudden recent drop or seasonality\n"
            ."Typical causes to check: ad not eligible or paused / no stock, budget runs out early, bid too low versus the CPC others pay, "
            ."too few or weak keywords / targets, wrong match types, negatives blocking traffic, low page views, price or rating hurting the listing, "
            ."and a sudden drop in the last few days.\n"
            ."If clicks are actually healthy, say so and set severity to \"ok\".\n\n"
            ."Return exactly this JSON shape:\n"
            .'{"verdict": "one sentence, max 160 characters", "severity": "high|medium|low|ok", '
            .'"reasons": [{"category": "budget|bid|keywords|stock|listing|other", "title": "short title", "evidence": "numbers from the data", "fix": "one concrete action", "impact": "high|medium|low"}], '
            .'"next_steps": ["max 4 short action items"]}'."\n"
            .'Give at most 8 reasons. Only list real problems; an aspect with no problem gets no reason.';
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array{verdict: string, severity: string, reasons: list<array<string, string>>, next_steps: list<string>}
     */
    private function normalizeReport(array $report): array
    {
        $severity = strtolower(trim((string) ($report['severity'] ?? '')));
        if (! in_array($severity, self::SEVERITIES, true)) {
            $severity = 'medium';
        }
        $reasons = [];
        foreach ((array) ($report['reasons'] ?? []) as $r) {
            if (! is_array($r)) {
                continue;
            }
            $impact = strtolower(trim((string) ($r['impact'] ?? '')));
            $category = strtolower(trim((string) ($r['category'] ?? '')));
            $reasons[] = [
                'category' => in_array($category, self::ASPECTS, true) ? $category : 'other',
                'title' => mb_substr(trim((string) ($r['title'] ?? '')), 0, 200),
                'evidence' => mb_substr(trim((string) ($r['evidence'] ?? '')), 0, 600),
                'fix' => mb_substr(trim((string) ($r['fix'] ?? '')), 0, 600),
                'impact' => in_array($impact, ['high', 'medium', 'low'], true) ? $impact : 'medium',
            ];
            if (count($reasons) >= 8) {
                break;
            }
        }
        $steps = [];
        foreach ((array) ($report['next_steps'] ?? []) as $step) {
            $step = mb_substr(trim((string) (is_scalar($step) ? $step : '')), 0, 300);
            if ($step !== '') {
                $steps[] = $step;
            }
            if (count($steps) >= 4) {
                break;
            }
        }
        $verdict = mb_substr(trim((string) ($report['verdict'] ?? '')), 0, 300);
        if ($verdict === '' && $reasons !== []) {
            $verdict = $reasons[0]['title'];
        }

        return [
            'verdict' => $verdict,
            'severity' => $severity,
            'reasons' => $reasons,
            'next_steps' => $steps,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keep  keys that stay as strings
     * @return array<string, mixed>
     */
    private function cleanRow(array $row, array $keep): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $out[$k] = (! in_array($k, $keep, true) && is_numeric($v)) ? round((float) $v, 4) : $v;
        }

        return $out;
    }
}
