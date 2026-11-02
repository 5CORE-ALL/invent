<?php

namespace App\Http\Controllers\AmazonAds;

use App\Http\Controllers\AmazonAdsController;
use App\Http\Controllers\Controller;
use App\Models\AmazonKwTargetingKeyword;
use App\Services\AmazonAdsService;
use App\Services\AmazonKwCousinLinkService;
use App\Services\AmazonKwTargetingPushService;
use App\Support\AmazonAdsCampaignSkuMetrics;
use App\Support\AmazonAdsTargetCounts;
use App\Support\Marketplace\AmazonAdsMissingLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Amz FBM Targeting grid: campaign names from the same SP reports table as /amazon-ads/all,
 * targets from the Amazon Ads SP keywords + targeting APIs.
 */
class AmazonFbmTargetingController extends Controller
{
    private const SP_TABLE = 'amazon_sp_campaign_reports';

    private const KW_TABLE = 'amazon_sp_keyword_reports';

    private const STORE_TABLE = 'amazon_kw_targeting_keywords';

    private const NEG_TABLE = 'amazon_kw_targeting_negatives';

    private const SPECIFIC_TABLE = 'amazon_kw_targeting_specifics';

    private const SPECIFIC_NEG_TABLE = 'amazon_kw_targeting_specific_negatives';

    /** Per-target flags (deleted / ignored) set from the targets modal. */
    private const FLAG_TABLE = 'amazon_kw_targeting_flags';

    private const COUSIN_TABLE = 'amazon_kw_cousin_links';

    /** Set while a background pull of daily campaign reports (for the LT columns) is running. */
    private const LT_SYNC_LOCK = 'amz_fbm_lt_sync_lock';

    /** "Low keyword count" badge thresholds: campaigns with fewer than this many of each kind. */
    private const LOW_LIMITS = ['kw' => 50, 'neg' => 50, 'spec' => 10, 'sneg' => 10];

    private const COUSIN_NAME_TABLE = 'amazon_kw_cousin_names';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $campaignIndexMemo = null;

    /** @var array<string, list<string>>|null parent key => campaign names */
    private ?array $siblingsMemo = null;

    public function __construct(private AmazonAdsService $ads, private AmazonKwCousinLinkService $cousins)
    {
    }

    public function index()
    {
        return view('amazon_ads.fbm-targeting');
    }

    public function data(Request $request): JsonResponse
    {
        // Give a heavy production load room, and make any failure visible: caught errors come back as JSON with the
        // reason, and fatals (memory / time limit) are written to storage/logs/fbm-targeting-error.log.
        $limit = (string) ini_get('memory_limit');
        if ($limit !== '-1' && $this->iniBytes($limit) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
        @set_time_limit(90);
        register_shutdown_function(static function () {
            $e = error_get_last();
            if ($e && in_array($e['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
                @file_put_contents(
                    storage_path('logs/fbm-targeting-error.log'),
                    '['.date('Y-m-d H:i:s').'] FATAL '.$e['message'].' @ '.$e['file'].':'.$e['line']."\n",
                    FILE_APPEND
                );
            }
        });

        try {
            return $this->loadData($request);
        } catch (\Throwable $e) {
            report($e);
            @file_put_contents(
                storage_path('logs/fbm-targeting-error.log'),
                '['.date('Y-m-d H:i:s').'] '.get_class($e).': '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine()."\n",
                FILE_APPEND
            );

            return response()->json([
                'success' => false,
                'message' => mb_substr(class_basename($e).': '.$e->getMessage(), 0, 400).' ('.basename($e->getFile()).':'.$e->getLine().')',
            ], 500);
        }
    }

    private function iniBytes(string $v): int
    {
        $v = trim($v);
        $n = (int) $v;
        $u = strtolower(substr($v, -1));

        return match ($u) {
            'g' => $n * 1024 * 1024 * 1024,
            'm' => $n * 1024 * 1024,
            'k' => $n * 1024,
            default => $n,
        };
    }

    /** @var array<string, mixed> */
    private static array $schemaMemo = [];

    // Schema lookups hit information_schema (slow on a big database) and the answers rarely change, so they are
    // remembered for an hour. Only "yes" answers are cached, so a table created by a migration shows up straight away.
    protected function hasTable(string $table): bool
    {
        $k = 't:'.$table;
        if (self::$schemaMemo[$k] ?? false) {
            return true;
        }
        $yes = Cache::get('amz_kw_schema:'.$k) === 1 || Schema::hasTable($table);
        if ($yes) {
            self::$schemaMemo[$k] = true;
            Cache::put('amz_kw_schema:'.$k, 1, now()->addHour());
        }

        return $yes;
    }

    protected function hasColumn(string $table, string $column): bool
    {
        return in_array(strtolower($column), array_map('strtolower', $this->columnsOf($table)), true);
    }

    /** @return list<string> */
    protected function columnsOf(string $table): array
    {
        $k = 'c:'.$table;
        if (isset(self::$schemaMemo[$k])) {
            return self::$schemaMemo[$k];
        }
        $cols = Cache::get('amz_kw_schema:'.$k);
        if (! is_array($cols) || $cols === []) {
            $cols = Schema::getColumnListing($table);
            if ($cols !== []) {
                Cache::put('amz_kw_schema:'.$k, $cols, now()->addHour());
            }
        }

        return self::$schemaMemo[$k] = array_values($cols);
    }

    protected function loadData(Request $request): JsonResponse
    {
        $t0 = $tLast = microtime(true);
        $timing = [];
        $mark = function (string $name) use (&$timing, &$tLast): void {
            $now = microtime(true);
            $timing[$name] = (int) round(($now - $tLast) * 1000);
            $tLast = $now;
        };
        if (! $this->hasTable(self::SP_TABLE) || ! $this->hasColumn(self::SP_TABLE, 'campaignName')) {
            return response()->json(['success' => true, 'data' => [], 'last_page' => 1, 'total' => 0]);
        }

        $page = max(1, (int) $request->query('page', 1));
        $size = min(100, max(1, (int) $request->query('size', 50)));
        $search = trim((string) $request->query('campaign', ''));

        $all = $this->campaignsGroupedByParent();
        $mark('campaigns');
        // Badge figures are cached briefly and can never break the grid (they just show 0 if something fails).
        $badges = $this->badgeFigures($this->withoutArchived($all)); // archived campaigns are listed but not counted
        $missingTitles = $badges['missing_titles'];
        $missingCousins = $badges['missing_cousins'];
        $mark('badges');

        // "Low" badges: campaigns under the keyword-count thresholds (counted over every campaign, ignoring filters).
        $lowCounts = $badges['counts'];
        $lowTotals = $this->lowTotals($this->withoutArchived($all), $lowCounts);
        $low = strtolower(trim((string) $request->query('low', '')));

        // Sponsored Brands (HEAD / HL) campaigns are listed too, like /amazon-ads/all does. They are added after
        // the badge figures so the KW / HL work counts stay SP-only, and the "low" filter leaves them out.
        $all = $this->mergeSbCampaigns($all);
        $all = $this->withAdsAllStatus($all);
        if (isset(self::LOW_LIMITS[$low])) {
            $all = array_values(array_filter($all, static fn (array $c) => empty($c['listed_only'])));
        }

        // Status filter like /amazon-ads/all: ENABLED / PAUSED / ARCHIVED (comma separated); empty = all statuses.
        $statusWanted = array_values(array_filter(array_map(
            static fn (string $v) => strtoupper(trim($v)),
            explode(',', (string) $request->query('status', ''))
        ), static fn (string $v) => in_array($v, ['ENABLED', 'PAUSED', 'ARCHIVED'], true)));
        if ($statusWanted !== []) {
            $all = array_values(array_filter($all, static fn (array $c) => in_array($c['status'], $statusWanted, true)));
        }

        // Parent = "PARENT …" campaigns, SKU = single-SKU campaigns, all = both.
        $scope = strtolower(trim((string) $request->query('scope', 'all')));
        if ($scope === 'parent' || $scope === 'sku') {
            $all = array_values(array_filter($all, static fn (array $c) => $scope === 'parent' ? $c['is_parent'] : ! $c['is_parent']));
        }

        // Separate filters: campaign name, parent, and the parent's Product Master title (every word must match).
        if ($search !== '') {
            $needle = mb_strtolower($search);
            // A campaign matches by its name OR by a SKU advertised in it (the SKUs shown by "Campaign SKUs" on
            // /amazon-ads/all), e.g. "WF 10 140 PP 4OHM" finds "PARENT 10 CW KW" which advertises that SKU.
            // When the text is exactly a SKU, only the campaigns advertising that exact SKU are shown
            // ("WF 10 140 PP 4OHM" no longer pulls in the "… 4OHM 2PCS" SKU); otherwise it is a contains search.
            $exactSku = $this->campaignIdsAdvertisingSku($search, true);
            if ($exactSku !== []) {
                $all = array_values(array_filter($all, static fn (array $c) => $c['campaign_id'] !== '' && isset($exactSku[$c['campaign_id']])));
            } else {
                $bySku = $this->campaignIdsAdvertisingSku($search);
                $all = array_values(array_filter($all, static fn (array $c) => str_contains(mb_strtolower($c['campaign_name']), $needle)
                    || ($c['campaign_id'] !== '' && isset($bySku[$c['campaign_id']]))));
            }
        }
        $parentQ = mb_strtolower(trim((string) $request->query('parent', '')));
        if ($parentQ !== '') {
            $all = array_values(array_filter($all, static fn (array $c) => str_contains(mb_strtolower($c['parent']), $parentQ)));
        }
        $titleTerms = array_values(array_filter(preg_split('/\s+/', mb_strtolower(trim((string) $request->query('title', '')))) ?: []));
        if ($titleTerms !== []) {
            $titleMap = $this->parentTitles(array_map(static fn (array $c) => $c['parent'], $all));
            $all = array_values(array_filter($all, function (array $c) use ($titleMap, $titleTerms) {
                $title = mb_strtolower($titleMap[$this->parentKey($c['parent'])] ?? '');
                foreach ($titleTerms as $t) {
                    if (! str_contains($title, $t)) {
                        return false;
                    }
                }

                return $title !== '';
            }));
        }

        if (isset(self::LOW_LIMITS[$low])) {
            $limit = self::LOW_LIMITS[$low];
            $all = array_values(array_filter($all, static fn (array $c) => ($lowCounts[$c['campaign_id']][$low] ?? 0) < $limit));
        }

        // KW cousin filter: matches the cousin group name, or any cousin campaign / cousin parent.
        $cousinSearch = mb_strtolower(trim((string) $request->query('cousin', '')));
        if ($cousinSearch !== '') {
            $groupsAll = $this->hasTable(self::COUSIN_TABLE) ? $this->cousins->groupsMap() : [];
            $namesAll = $this->cousinNameMap();
            $index = $this->campaignIndex();
            $terms = preg_split('/\s+/', $cousinSearch) ?: [];
            $all = array_values(array_filter($all, function (array $c) use ($groupsAll, $namesAll, $index, $terms) {
                $group = $this->effectiveGroup($c['campaign_name'], $groupsAll);
                $hay = mb_strtolower($this->groupNameFor($group, $c['campaign_name'], $namesAll));
                foreach ($this->cousinCampaignNames($group, $c['campaign_name']) as $member) {
                    $hay .= ' '.mb_strtolower($member).' '.mb_strtolower($index[$this->cousins->normalize($member)]['parent'] ?? '');
                }
                foreach ($terms as $t) {
                    if ($t !== '' && ! str_contains($hay, $t)) {
                        return false;
                    }
                }

                return true;
            }));
        }

        $groups = $this->hasTable(self::COUSIN_TABLE) ? $this->cousins->groupsMap() : [];

        // Sort by Clicks LT / Sold LT (cousin-group totals): ranked over every filtered campaign, then paged.
        $sortKey = strtolower(trim((string) $request->query('sort', '')));
        $ltAll = null;
        if ($sortKey === 'lt_clicks' || $sortKey === 'lt_sold') {
            $ltAll = $this->lifetimeForRows($all, $groups);
            $field = $sortKey === 'lt_clicks' ? 'clicks' : 'sold';
            $dirMul = strtolower(trim((string) $request->query('dir', 'desc'))) === 'asc' ? 1 : -1;
            // usort is stable, so equal totals keep their parent / name order.
            usort($all, static fn (array $a, array $b) => $dirMul * (($ltAll[$a['campaign_name']][$field] ?? 0) <=> ($ltAll[$b['campaign_name']][$field] ?? 0)));
        }

        $total = count($all);
        $rows = array_slice($all, ($page - 1) * $size, $size);

        $mark('filters');

        // Keep keywords / negatives in step across KW cousins before they are read below.
        $this->autoSyncCousins($rows);
        $mark('cousin_sync');

        $campaignIds = array_values(array_filter(array_map(
            static fn (array $r) => $r['campaign_id'],
            $rows
        ), static fn (string $id) => $id !== ''));

        $targetsByCampaign = $this->mergeStoredKeywords(
            $this->targetsForCampaignIds($campaignIds),
            $campaignIds
        );

        $negativesByCampaign = $this->mergeStoredKeywords([], $campaignIds, self::NEG_TABLE);
        // Targets deleted in the modal are hidden; ignored ones stay listed (flagged for the modal).
        [$targetsByCampaign, $ignoredTargetsByCampaign] = $this->applyFlags($targetsByCampaign, $campaignIds, 'kw');
        [$negativesByCampaign, $ignoredNegativesByCampaign] = $this->applyFlags($negativesByCampaign, $campaignIds, 'neg');
        $specificByCampaign = $this->specificByCampaign($campaignIds);
        $specificNegByCampaign = $this->specificByCampaign($campaignIds, self::SPECIFIC_NEG_TABLE);

        $mark('keywords');
        $titlesByParent = $this->parentTitles(array_map(static fn (array $r) => $r['parent'], $rows));
        $mark('titles');

        $nameMap = $this->cousinNameMap();

        // Targets / Negative KW counts come from the same source as the Targets / N Targets columns on
        // /amazon-ads/all (SP: L30 keyword report + synced negatives; SB: synced amazon_ads_target_counts).
        // The lists behind the counts (targets modal) are unchanged. Falls back to the list size on failure.
        $adsCounts = ['SP' => ['t' => [], 'n' => []], 'SB' => ['t' => [], 'n' => []]];
        try {
            foreach (['SP' => 'amazon_sp_campaign_reports', 'SB' => 'amazon_sb_campaign_reports'] as $type => $reportTable) {
                $ids = [];
                foreach ($rows as $r) {
                    if (($r['ad_type'] ?? 'SP') === $type && $r['campaign_id'] !== '') {
                        $ids[] = $r['campaign_id'];
                    }
                }
                if ($ids !== []) {
                    $adsCounts[$type]['t'] = AmazonAdsController::targetCountsForCampaigns($reportTable, $ids);
                    $adsCounts[$type]['n'] = AmazonAdsController::negativeTargetCountsForCampaigns($ids, $reportTable);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: target counts from Ads All failed', ['error' => $e->getMessage()]);
            $adsCounts = ['SP' => ['t' => [], 'n' => []], 'SB' => ['t' => [], 'n' => []]];
        }

        // Keywords copied from KW cousins that are not on Amazon yet (waiting for a push): shown as "+N" next to Targets.
        $pending = [];
        try {
            $spIds = [];
            foreach ($rows as $r) {
                if (($r['ad_type'] ?? 'SP') === 'SP' && $r['campaign_id'] !== '') {
                    $spIds[] = $r['campaign_id'];
                }
            }
            if ($spIds !== [] && $this->hasTable(self::STORE_TABLE)) {
                foreach (array_chunk(array_values(array_unique($spIds)), 1000) as $chunk) {
                    foreach (DB::table(self::STORE_TABLE)->whereIn('campaign_id', $chunk)->where('source', 'cousin')
                        ->selectRaw('campaign_id, COUNT(*) AS c')->groupBy('campaign_id')->get() as $o) {
                        $pending[(string) $o->campaign_id] = (int) $o->c;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: pending cousin keyword counts failed', ['error' => $e->getMessage()]);
        }

        // Lifetime clicks / sold of every campaign in each row's KW cousin group (own parent + cousin parents).
        $lt = $ltAll ?? $this->lifetimeForRows($rows, $groups);
        $mark('lifetime');

        $data = [];
        $prevParent = null;
        foreach ($rows as $row) {
            $id = $row['campaign_id'];
            $name = $row['campaign_name'];
            $targets = $targetsByCampaign[$id] ?? ($targetsByCampaign[strtolower($name)] ?? []);
            $negatives = $negativesByCampaign[$id] ?? [];
            $ignoredTargets = $ignoredTargetsByCampaign[$id] ?? [];
            $ignoredNegatives = $ignoredNegativesByCampaign[$id] ?? [];
            $specific = $specificByCampaign[$id] ?? [];
            $specificNeg = $specificNegByCampaign[$id] ?? [];

            // Campaigns under the same parent share the same KW cousins.
            $group = $this->effectiveGroup($name, $groups);
            $cousinNames = $this->cousinCampaignNames($group, $name);

            $cousinParents = $cousinNames === [] ? [] : $this->cousinParents($cousinNames);

            $ltRow = $lt[$name] ?? ['clicks' => 0, 'sold' => 0, 'campaigns' => 0, 'with_data' => 0];
            $data[] = [
                'lt_clicks' => $ltRow['clicks'],
                'lt_sold' => $ltRow['sold'],
                'lt_campaigns' => $ltRow['campaigns'],
                'lt_campaigns_with_data' => $ltRow['with_data'],
                // id / name give the Auto Task Events (ATE) rules one trackable key + label per campaign row.
                'id' => $name !== '' ? $name : $id, // campaign names are unique per row (a campaign id can repeat across renamed campaigns)
                'name' => $name,
                'cousin_name' => $this->groupNameFor($group, $name, $nameMap),
                'cousins' => $cousinNames,
                'cousin_parents' => $cousinParents,
                'cousin_count' => count($cousinParents),
                'parent' => $row['parent'],
                'title' => $titlesByParent[$this->parentKey($row['parent'])] ?? '',
                // Parent is printed once per group (first row of the group on this page).
                'parent_first' => $prevParent === null || $prevParent !== $row['parent'],
                'campaign_id' => $id,
                'campaign_name' => $name,
                'ad_type' => $row['ad_type'] ?? 'SP',
                'status' => $row['status'],
                'targets' => $targets,
                // Same function as /amazon-ads/all (L30 report + keywords pulled from / pushed to Amazon), so both pages match.
                'target_count' => $adsCounts[$row['ad_type'] ?? 'SP']['t'][$id] ?? count($targets),
                'target_pending' => $pending[$id] ?? 0,
                'ignored_targets' => $ignoredTargets,
                'negatives' => $negatives,
                'negative_count' => $adsCounts[$row['ad_type'] ?? 'SP']['n'][$id] ?? count($negatives),
                'ignored_negatives' => $ignoredNegatives,
                'specific' => $specific,
                'specific_count' => count($specific),
                'specific_neg' => $specificNeg,
                'specific_neg_count' => count($specificNeg),
            ];
            $prevParent = $row['parent'];
        }

        $mark('rows');
        $timing['total'] = (int) round((microtime(true) - $t0) * 1000);
        if ($timing['total'] > 3000) {
            Log::warning('AmazonFbmTargetingController: slow grid load', $timing + ['query' => $request->query()]);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'last_page' => max(1, (int) ceil($total / $size)),
            'total' => $total,
            'missing_titles' => count($missingTitles),
            'missing_title_parents' => array_slice($missingTitles, 0, 40),
            'low_counts' => $lowTotals,
            'low_limits' => self::LOW_LIMITS,
            'missing_cousins' => count($missingCousins),
            'missing_cousin_parents' => array_slice($missingCousins, 0, 40),
            'lt_coverage' => $this->lifetimeCoverage(),
            'timing_ms' => $timing,
        ])->header('Server-Timing', implode(', ', array_map(static fn ($k, $v) => $k.';dur='.$v, array_keys($timing), $timing)));
    }

    /**
     * @param  list<array{campaign_id: string}>  $campaigns
     * @param  array<string, array<string, int>>  $counts
     * @return array<string, int>
     */
    protected function lowTotals(array $campaigns, array $counts): array
    {
        $totals = array_fill_keys(array_keys(self::LOW_LIMITS), 0);
        foreach ($campaigns as $c) {
            foreach (self::LOW_LIMITS as $k => $limit) {
                if (($counts[$c['campaign_id']][$k] ?? 0) < $limit) {
                    $totals[$k]++;
                }
            }
        }

        return $totals;
    }

    /**
     * The toolbar badge numbers for Auto Task Events (the server-side daily check). Keys / labels match the page's badges.
     *
     * @return list<array{key: string, label: string, value: int}>
     */
    public function aceBadgeValues(): array
    {
        $all = $this->withoutArchived($this->campaignsGroupedByParent());
        $b = $this->badgeFigures($all, true); // the daily Auto Task Event check always reads fresh numbers
        $low = $this->lowTotals($all, $b['counts']);

        return [
            ['key' => 'aft-missing-titles', 'label' => 'Missing Titles', 'value' => count($b['missing_titles'])],
            ['key' => 'aft-missing-cousins', 'label' => 'Missing KW cousins', 'value' => count($b['missing_cousins'])],
            ['key' => 'aft-low-kw', 'label' => 'KW < '.self::LOW_LIMITS['kw'], 'value' => $low['kw']],
            ['key' => 'aft-low-neg', 'label' => 'Neg KW < '.self::LOW_LIMITS['neg'], 'value' => $low['neg']],
            ['key' => 'aft-low-spec', 'label' => 'Specific KW < '.self::LOW_LIMITS['spec'], 'value' => $low['spec']],
            ['key' => 'aft-low-sneg', 'label' => 'Specific neg KW < '.self::LOW_LIMITS['sneg'], 'value' => $low['sneg']],
        ];
    }

    private const BADGE_CACHE_KEY = 'amz_kw_targeting_badges_v3';

    protected function forgetBadges(): void
    {
        Cache::forget(self::BADGE_CACHE_KEY);
    }

    /**
     * Figures behind the toolbar badges (missing titles / cousins, keyword counts per campaign). Cached for a minute
     * and each part is isolated so a failure only blanks that badge instead of failing the whole grid.
     *
     * @param  list<array{parent: string, campaign_name: string, campaign_id: string}>  $campaigns
     * @return array{missing_titles: list<string>, missing_cousins: list<string>, counts: array<string, array{kw: int, neg: int, spec: int, sneg: int}>}
     */
    protected function badgeFigures(array $campaigns, bool $fresh = false): array
    {
        $empty = ['missing_titles' => [], 'missing_cousins' => [], 'counts' => []];
        if ($fresh) {
            $this->forgetBadges();
        }
        try {
            // Cached for 15 minutes and dropped as soon as keywords / cousins change (forgetBadges), so a search never pays for them.
            return Cache::remember(self::BADGE_CACHE_KEY, now()->addMinutes(15), function () use ($campaigns, $empty) {
                $out = $empty;
                foreach ([
                    'missing_titles' => fn () => $this->missingTitleParents($campaigns),
                    'missing_cousins' => fn () => $this->missingCousinParents($campaigns),
                    'counts' => fn () => $this->keywordCountsByCampaign(),
                ] as $key => $fn) {
                    try {
                        $out[$key] = $fn();
                    } catch (\Throwable $e) {
                        Log::warning('AmazonFbmTargetingController: badge figure failed', ['badge' => $key, 'error' => $e->getMessage()]);
                    }
                }

                return $out;
            });
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: badge figures failed', ['error' => $e->getMessage()]);

            return $empty;
        }
    }

    /**
     * Keyword counts per campaign id for the "low" badges / filters: kw (reports + app store, distinct keyword + match),
     * neg (app negatives), spec (Specific KW), sneg (S Negative KW).
     *
     * @return array<string, array{kw: int, neg: int, spec: int, sneg: int}>
     */
    protected function keywordCountsByCampaign(): array
    {
        $out = [];
        $bump = static function (string $cid, string $k, int $n) use (&$out): void {
            $out[$cid] ??= ['kw' => 0, 'neg' => 0, 'spec' => 0, 'sneg' => 0];
            $out[$cid][$k] = $n;
        };

        // kw: union of the keyword reports and the app store, distinct by lower-cased "keyword (MATCH)" - aggregated in SQL
        // (the reports table can be large, so nothing is loaded into PHP).
        $parts = [];
        if ($this->hasTable(self::KW_TABLE)) {
            $cols = $this->columnsOf(self::KW_TABLE);
            $labelCol = in_array('keyword', $cols, true) ? 'keyword' : (in_array('targeting', $cols, true) ? 'targeting' : null);
            if ($labelCol !== null && in_array('campaign_id', $cols, true)) {
                $matchExpr = in_array('matchType', $cols, true) ? "TRIM(COALESCE(`matchType`, ''))" : "''";
                $parts[] = 'SELECT CONVERT(CAST(`campaign_id` AS CHAR) USING utf8mb4) AS cid, '
                    ."LOWER(CONVERT(CONCAT(TRIM(`{$labelCol}`), IF({$matchExpr} = '', '', CONCAT(' (', {$matchExpr}, ')'))) USING utf8mb4)) AS lbl "
                    .'FROM `'.self::KW_TABLE."` WHERE `{$labelCol}` IS NOT NULL AND TRIM(`{$labelCol}`) <> '' AND `campaign_id` IS NOT NULL";
            }
        }
        if ($this->hasTable(self::STORE_TABLE)) {
            $parts[] = 'SELECT CONVERT(CAST(`campaign_id` AS CHAR) USING utf8mb4) AS cid, '
                ."LOWER(CONVERT(CONCAT(`keyword`, IF(`match_type` = '', '', CONCAT(' (', `match_type`, ')'))) USING utf8mb4)) AS lbl "
                .'FROM `'.self::STORE_TABLE.'`';
        }
        if ($parts !== []) {
            // UNION (not UNION ALL) removes the duplicates between the two sources.
            // Targets deleted in the targets modal are left out.
            $skipDeleted = $this->hasTable(self::FLAG_TABLE)
                ? " LEFT JOIN `".self::FLAG_TABLE."` f ON f.kind = 'kw' AND f.status = 'deleted'"
                    .' AND f.campaign_id COLLATE utf8mb4_unicode_ci = u.cid COLLATE utf8mb4_unicode_ci'
                    .' AND f.label_lc COLLATE utf8mb4_unicode_ci = u.lbl COLLATE utf8mb4_unicode_ci WHERE f.id IS NULL'
                : '';
            foreach (DB::select('SELECT u.cid AS cid, COUNT(*) AS c FROM ('.implode(' UNION ', $parts).') u'.$skipDeleted.' GROUP BY u.cid') as $r) {
                $bump(trim((string) $r->cid), 'kw', (int) $r->c);
            }
        }

        if ($this->hasTable(self::NEG_TABLE)) {
            foreach (DB::table(self::NEG_TABLE)->selectRaw("campaign_id, COUNT(DISTINCT LOWER(CONCAT(keyword, '|', match_type))) as c")->groupBy('campaign_id')->get() as $r) {
                $bump((string) $r->campaign_id, 'neg', (int) $r->c);
            }
        }
        foreach (['spec' => self::SPECIFIC_TABLE, 'sneg' => self::SPECIFIC_NEG_TABLE] as $k => $table) {
            if ($this->hasTable($table)) {
                foreach (DB::table($table)->selectRaw('campaign_id, COUNT(*) as c')->groupBy('campaign_id')->get() as $r) {
                    $bump((string) $r->campaign_id, $k, (int) $r->c);
                }
            }
        }

        // kw / neg use the same source as the Targets / N Targets columns on /amazon-ads/all (and as the Targets /
        // Negative KW columns of this grid), so the "low" badges and filters agree with the numbers shown.
        // On any failure the counts above (reports + app store) stay as they were.
        try {
            $ids = [];
            foreach ($this->campaignsGroupedByParent() as $c) {
                if ($c['campaign_id'] !== '') {
                    $ids[$c['campaign_id']] = true;
                }
            }
            foreach (array_chunk(array_keys($ids), 1000) as $chunk) {
                $chunk = array_map('strval', $chunk);
                $t = AmazonAdsController::targetCountsForCampaigns('amazon_sp_campaign_reports', $chunk);
                $n = AmazonAdsController::negativeTargetCountsForCampaigns($chunk, 'amazon_sp_campaign_reports');
                foreach ($chunk as $cid) {
                    $bump($cid, 'kw', (int) ($t[$cid] ?? 0));
                    $bump($cid, 'neg', (int) ($n[$cid] ?? 0));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: low-badge counts from Ads All failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    /**
     * Parents (of every campaign on this page, ignoring filters) that have no KW cousins on any other parent.
     * Campaigns of one parent share cousins, so one campaign per parent is enough to decide.
     *
     * @param  list<array{parent: string, campaign_name: string}>  $campaigns
     * @return list<string>
     */
    protected function missingCousinParents(array $campaigns): array
    {
        $groups = $this->hasTable(self::COUSIN_TABLE) ? $this->cousins->groupsMap() : [];
        $missing = [];
        $seen = [];
        foreach ($campaigns as $c) {
            $p = trim((string) $c['parent']);
            if ($p === '') {
                continue;
            }
            $key = $this->parentKey($p);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $group = $this->effectiveGroup($c['campaign_name'], $groups);
            if ($this->cousinCampaignNames($group, $c['campaign_name']) === []) {
                $missing[] = $p;
            }
        }
        sort($missing, SORT_NATURAL | SORT_FLAG_CASE);

        return $missing;
    }

    /**
     * Parents (of every campaign on this page, ignoring filters) that have no title in the Product Master.
     *
     * @param  list<array{parent: string}>  $campaigns
     * @return list<string>
     */
    protected function missingTitleParents(array $campaigns): array
    {
        $parents = [];
        foreach ($campaigns as $c) {
            $p = trim((string) $c['parent']);
            if ($p !== '') {
                $parents[$this->parentKey($p)] = $p;
            }
        }
        if ($parents === []) {
            return [];
        }

        $titles = $this->parentTitles(array_values($parents));
        $missing = [];
        foreach ($parents as $key => $label) {
            if (($titles[$key] ?? '') === '') {
                $missing[] = $label;
            }
        }
        sort($missing, SORT_NATURAL | SORT_FLAG_CASE);

        return $missing;
    }

    /**
     * Every distinct SP campaign of /amazon-ads/all (enabled, paused AND archived; PT campaigns excluded) with its parent
     * family, ordered so all campaigns of one parent sit together (parents A-Z, campaigns without a known parent last).
     * Base = the same report rows the Ads All page reads; live Amazon states correct stale statuses and add campaigns
     * the reports lack.
     *
     * @return list<array{parent: string, status: string, is_parent: bool, campaign_id: string, campaign_name: string}>
     */
    protected function campaignsGroupedByParent(): array
    {
        return Cache::remember('amz_kw_targeting_campaigns_by_parent_v8', now()->addMinutes(5), function () {
            // Base = the Ads All report rows (summary rows L1 / L7 / L15 / L30; L30 status first).
            $status = $this->summaryStatusMap();
            // Live Amazon states (refreshed in the background) fix statuses the reports have gone stale on and add
            // campaigns the reports have never seen. null until the first background sync has finished.
            $live = $this->liveCampaigns();
            $liveByName = $live['byName'] ?? [];
            $liveById = $live['byId'] ?? [];
            $stateOf = static function (string $name, string $id) use ($status, $liveByName, $liveById): string {
                $key = AmazonAdsMissingLinks::normalizeCampaignName($name);
                if (isset($liveByName[$key])) {
                    return (string) $liveByName[$key]['state'];
                }
                if ($id !== '' && isset($liveById[$id])) {
                    return (string) $liveById[$id]['state']; // renamed on Amazon: the id still has the live state
                }

                return (string) ($status[$key] ?? '');
            };
            // A report name Amazon no longer has, whose campaign id is live under a new name, is the old name of a
            // renamed campaign: a ghost duplicate with a stale status. The current name is listed instead.
            $isRenamedOldName = static function (string $name, string $id) use ($liveByName, $liveById): bool {
                if ($liveByName === [] || $id === '' || ! isset($liveById[$id])) {
                    return false;
                }
                $key = AmazonAdsMissingLinks::normalizeCampaignName($name);

                return ! isset($liveByName[$key])
                    && AmazonAdsMissingLinks::normalizeCampaignName((string) $liveById[$id]['name']) !== $key;
            };

            // This page is for KW / HL work only: product-targeting (PT) campaigns are left out everywhere.
            $rows = DB::table(self::SP_TABLE)
                ->where('report_date_range', 'like', 'L%')
                ->whereNotNull('campaignName')
                ->where('campaignName', '!=', '')
                ->selectRaw('campaignName AS campaign_name, MAX(campaign_id) AS campaign_id')
                ->groupBy('campaignName')
                ->get()
                ->reject(fn ($r) => $this->isProductTargetingCampaign((string) $r->campaign_name)
                    || $isRenamedOldName((string) $r->campaign_name, trim((string) ($r->campaign_id ?? ''))))
                ->values();

            // Live KW campaigns without report rows (never archived ones): listed so they can be pushed to.
            $known = [];
            foreach ($rows as $r) {
                $known[AmazonAdsMissingLinks::normalizeCampaignName((string) $r->campaign_name)] = true;
            }
            $extra = [];
            foreach ($liveByName as $key => $c) {
                if (isset($known[$key]) || $c['state'] === 'ARCHIVED' || $this->isProductTargetingCampaign((string) $c['name'])) {
                    continue;
                }
                $extra[] = (object) ['campaign_name' => (string) $c['name'], 'campaign_id' => (string) $c['id']];
            }
            if ($extra !== []) {
                $rows = $rows->concat($extra)->values();
            }

            $names = $rows->map(static fn ($r) => trim((string) $r->campaign_name))->all();
            $parents = AmazonAdsCampaignSkuMetrics::parentFamiliesForCampaignNames($names);

            $list = [];
            foreach ($rows as $r) {
                $name = trim((string) $r->campaign_name);
                $list[] = [
                    'parent' => (string) ($parents[$name] ?? ''),
                    'status' => $stateOf($name, trim((string) ($r->campaign_id ?? ''))),
                    // PARENT … campaigns vs single-SKU campaigns.
                    'is_parent' => AmazonAdsCampaignSkuMetrics::campaignKeyIsParent(
                        AmazonAdsCampaignSkuMetrics::skuKeyFromCampaignName($name)
                    ),
                    'campaign_id' => trim((string) ($r->campaign_id ?? '')),
                    'campaign_name' => $name,
                ];
            }

            usort($list, static function (array $a, array $b) {
                if (($a['parent'] === '') !== ($b['parent'] === '')) {
                    return $a['parent'] === '' ? 1 : -1;
                }

                return strnatcasecmp($a['parent'], $b['parent'])
                    ?: strnatcasecmp($a['campaign_name'], $b['campaign_name']);
            });

            return $list;
        });
    }

    /**
     * Campaign name as a lookup key, exact apart from case and spacing. normalizeCampaignName() also drops a trailing
     * dot, which would merge two different campaigns such as "PARENT HORN 508 4PCS PT" and "PARENT HORN 508 4PCS PT.".
     */
    protected function exactNameKey(string $name): string
    {
        return strtoupper(trim((string) preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $name))));
    }

    /**
     * Sponsored Brands campaigns (HEAD / HL, amazon_sb_campaign_reports) listed in the grid next to the SP ones, as on
     * /amazon-ads/all. Status comes from the SB summary rows (L30 first). Kept apart from campaignsGroupedByParent(),
     * which feeds the KW push, badges and cousin sync that only work on SP campaigns.
     *
     * @param  list<array<string, mixed>>  $sp  campaigns from campaignsGroupedByParent()
     * @return list<array<string, mixed>>
     */
    protected function mergeSbCampaigns(array $sp): array
    {
        try {
            $sb = Cache::remember('amz_kw_targeting_sb_campaigns_v3', now()->addMinutes(5), function () {
                $table = 'amazon_sb_campaign_reports';
                if (! $this->hasTable($table) || ! $this->hasColumn($table, 'campaignName')) {
                    return [];
                }
                $hasStatus = $this->hasColumn($table, 'campaignStatus');
                $status = [];
                $ids = [];
                foreach (['L30', 'L15', 'L7', 'L1'] as $range) { // first range with a row wins
                    $maxIds = DB::table($table)
                        ->where('report_date_range', $range)
                        ->whereNotNull('campaignName')
                        ->where('campaignName', '!=', '')
                        ->selectRaw('MAX(id) AS max_id')
                        ->groupBy('campaignName')
                        ->pluck('max_id')
                        ->filter()
                        ->all();
                    foreach (array_chunk($maxIds, 2000) as $chunk) {
                        $cols = ['campaignName', 'campaign_id'];
                        if ($hasStatus) {
                            $cols[] = 'campaignStatus';
                        }
                        foreach (DB::table($table)->whereIn('id', $chunk)->get($cols) as $r) {
                            $key = $this->exactNameKey((string) $r->campaignName);
                            if ($key !== '' && ! isset($status[$key])) {
                                $status[$key] = strtoupper(trim((string) ($r->campaignStatus ?? '')));
                                $ids[$key] = trim((string) ($r->campaign_id ?? ''));
                            }
                        }
                    }
                }
                $names = [];
                foreach (DB::table($table)->where('report_date_range', 'like', 'L%')->whereNotNull('campaignName')
                    ->where('campaignName', '!=', '')->distinct()->pluck('campaignName') as $n) {
                    $names[$this->exactNameKey((string) $n)] ??= trim((string) $n);
                }
                $parents = AmazonAdsCampaignSkuMetrics::parentFamiliesForCampaignNames(array_values($names));

                $list = [];
                foreach ($names as $key => $name) {
                    $list[] = [
                        'parent' => (string) ($parents[$name] ?? ''),
                        'status' => (string) ($status[$key] ?? ''),
                        'is_parent' => AmazonAdsCampaignSkuMetrics::campaignKeyIsParent(
                            AmazonAdsCampaignSkuMetrics::skuKeyFromCampaignName($name)
                        ),
                        'campaign_id' => (string) ($ids[$key] ?? ''),
                        'campaign_name' => $name,
                        'ad_type' => 'SB',
                        'listed_only' => true,
                    ];
                }

                return $list;
            });
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: SB campaigns could not be listed', ['error' => $e->getMessage()]);
            $sb = [];
        }
        try {
            $sb = array_merge($sb, $this->productTargetingCampaigns());
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: PT campaigns could not be listed', ['error' => $e->getMessage()]);
        }
        try {
            $sb = array_merge($sb, $this->renamedOldNameCampaigns());
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: renamed campaigns could not be listed', ['error' => $e->getMessage()]);
        }

        // A name already listed as an SP campaign is not repeated.
        $have = [];
        foreach ($sp as $c) {
            $have[$this->exactNameKey((string) $c['campaign_name'])] = true;
        }
        $merged = $sp;
        foreach ($sb as $c) {
            $k = $this->exactNameKey((string) $c['campaign_name']);
            if (! isset($have[$k])) {
                $have[$k] = true;
                $merged[] = $c;
            }
        }

        // Same order as campaignsGroupedByParent(): parents A-Z (unknown parent last), then campaign name.
        usort($merged, static function (array $a, array $b) {
            if (($a['parent'] === '') !== ($b['parent'] === '')) {
                return $a['parent'] === '' ? 1 : -1;
            }

            return strnatcasecmp($a['parent'], $b['parent'])
                ?: strnatcasecmp($a['campaign_name'], $b['campaign_name']);
        });

        return $merged;
    }

    /**
     * SP product-targeting (PT) campaigns, listed in the grid like /amazon-ads/all does. campaignsGroupedByParent()
     * leaves them out on purpose (the KW push, badges and cousin sync are for KW / HL campaigns), so they are added
     * only for display here. Status = live Amazon state when known, else the summary rows (L30 first).
     *
     * @return list<array<string, mixed>>
     */
    protected function productTargetingCampaigns(): array
    {
        return Cache::remember('amz_kw_targeting_pt_campaigns_v1', now()->addMinutes(5), function () {
            $status = $this->summaryStatusMap();
            $live = $this->liveCampaigns();
            $liveByName = $live['byName'] ?? [];
            $liveById = $live['byId'] ?? [];

            $rows = DB::table(self::SP_TABLE)
                ->where('report_date_range', 'like', 'L%')
                ->whereNotNull('campaignName')
                ->where('campaignName', '!=', '')
                ->selectRaw('campaignName AS campaign_name, MAX(campaign_id) AS campaign_id')
                ->groupBy('campaignName')
                ->get()
                ->filter(fn ($r) => $this->isProductTargetingCampaign((string) $r->campaign_name))
                ->values();

            $names = $rows->map(static fn ($r) => trim((string) $r->campaign_name))->all();
            $parents = AmazonAdsCampaignSkuMetrics::parentFamiliesForCampaignNames($names);

            $list = [];
            foreach ($rows as $r) {
                $name = trim((string) $r->campaign_name);
                $id = trim((string) ($r->campaign_id ?? ''));
                $key = AmazonAdsMissingLinks::normalizeCampaignName($name);
                // A report name Amazon no longer has, whose id is live under a new name, is a renamed ghost.
                if ($liveByName !== [] && $id !== '' && isset($liveById[$id]) && ! isset($liveByName[$key])
                    && AmazonAdsMissingLinks::normalizeCampaignName((string) $liveById[$id]['name']) !== $key) {
                    continue;
                }
                $state = isset($liveByName[$key])
                    ? (string) $liveByName[$key]['state']
                    : (($id !== '' && isset($liveById[$id])) ? (string) $liveById[$id]['state'] : (string) ($status[$key] ?? ''));
                $list[] = [
                    'parent' => (string) ($parents[$name] ?? ''),
                    'status' => $state,
                    'is_parent' => AmazonAdsCampaignSkuMetrics::campaignKeyIsParent(
                        AmazonAdsCampaignSkuMetrics::skuKeyFromCampaignName($name)
                    ),
                    'campaign_id' => $id,
                    'campaign_name' => $name,
                    'ad_type' => 'SP',
                    'listed_only' => true,
                ];
            }

            return $list;
        });
    }

    /**
     * Old names of renamed SP campaigns (KW or PT). /amazon-ads/all still lists them from the report rows, with the
     * status stored there; campaignsGroupedByParent() drops them as stale duplicates, so they are added back for
     * display only. Empty until the live campaign sync has run (renames are detected from it).
     *
     * @return list<array<string, mixed>>
     */
    protected function renamedOldNameCampaigns(): array
    {
        // Renames are detected from the live campaign list; without it nothing is cached (the list would stay
        // empty for the whole cache time even after the background sync has filled the live list).
        $live = $this->liveCampaigns();
        if (($live['byName'] ?? []) === []) {
            return [];
        }

        return Cache::remember('amz_kw_targeting_renamed_old_v2', now()->addMinutes(5), function () use ($live) {
            $liveByName = $live['byName'] ?? [];
            $liveById = $live['byId'] ?? [];
            $status = $this->summaryStatusMap();

            $rows = DB::table(self::SP_TABLE)
                ->where('report_date_range', 'like', 'L%')
                ->whereNotNull('campaignName')
                ->where('campaignName', '!=', '')
                ->selectRaw('campaignName AS campaign_name, MAX(campaign_id) AS campaign_id')
                ->groupBy('campaignName')
                ->get()
                ->filter(function ($r) use ($liveByName, $liveById) {
                    $id = trim((string) ($r->campaign_id ?? ''));
                    $key = AmazonAdsMissingLinks::normalizeCampaignName((string) $r->campaign_name);

                    return $id !== '' && isset($liveById[$id]) && ! isset($liveByName[$key])
                        && AmazonAdsMissingLinks::normalizeCampaignName((string) $liveById[$id]['name']) !== $key;
                })
                ->values();

            $names = $rows->map(static fn ($r) => trim((string) $r->campaign_name))->all();
            $parents = AmazonAdsCampaignSkuMetrics::parentFamiliesForCampaignNames($names);

            $list = [];
            foreach ($rows as $r) {
                $name = trim((string) $r->campaign_name);
                $list[] = [
                    'parent' => (string) ($parents[$name] ?? ''),
                    'status' => (string) ($status[AmazonAdsMissingLinks::normalizeCampaignName($name)] ?? ''),
                    'is_parent' => AmazonAdsCampaignSkuMetrics::campaignKeyIsParent(
                        AmazonAdsCampaignSkuMetrics::skuKeyFromCampaignName($name)
                    ),
                    'campaign_id' => trim((string) ($r->campaign_id ?? '')),
                    'campaign_name' => $name,
                    'ad_type' => 'SP',
                    'listed_only' => true,
                ];
            }

            return $list;
        });
    }

    /**
     * Shown / filtered status = the status /amazon-ads/all shows: the campaign's row on the latest report day, else its
     * summary rows (L30 first). The live Amazon state (which corrects stale reports) stays in use for the push and the
     * badges, which read campaignsGroupedByParent() directly, so an archived campaign is never pushed to.
     *
     * @param  list<array<string, mixed>>  $campaigns
     * @return list<array<string, mixed>>
     */
    protected function withAdsAllStatus(array $campaigns): array
    {
        try {
            $map = Cache::remember('amz_kw_targeting_ads_all_status_v2', now()->addMinutes(5), function () {
                $out = [];
                foreach ([self::SP_TABLE, 'amazon_sb_campaign_reports'] as $table) {
                    if (! $this->hasTable($table) || ! $this->hasColumn($table, 'campaignStatus')) {
                        continue;
                    }
                    $day = DB::table($table)->whereRaw('CHAR_LENGTH(report_date_range) = 10')->max('report_date_range');
                    if (! is_string($day) || $day === '') {
                        continue;
                    }
                    foreach (DB::table($table)->where('report_date_range', $day)->whereNotNull('campaignName')
                        ->get(['campaignName', 'campaignStatus']) as $r) {
                        $key = $this->exactNameKey((string) $r->campaignName);
                        $st = strtoupper(trim((string) $r->campaignStatus));
                        if ($key !== '' && $st !== '') {
                            $out[$key] = $st;
                        }
                    }
                }

                return $out;
            });
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: Ads All status map failed', ['error' => $e->getMessage()]);

            return $campaigns;
        }

        foreach ($campaigns as $i => $c) {
            $key = $this->exactNameKey((string) $c['campaign_name']);
            if (isset($map[$key])) {
                $campaigns[$i]['status'] = $map[$key];
            }
        }

        return $campaigns;
    }

    /**
     * Campaigns that can still be worked on (archived ones cannot be changed on Amazon).
     *
     * @param  list<array<string, mixed>>  $campaigns
     * @return list<array<string, mixed>>
     */
    protected function withoutArchived(array $campaigns): array
    {
        return array_values(array_filter($campaigns, static fn (array $c) => ($c['status'] ?? '') !== 'ARCHIVED'));
    }

    /**
     * Campaign status from the summary rows (what /amazon-ads/all shows), not from the newest row of any kind:
     * backfilled daily rows are newer by id but belong to old dates.
     *
     * @return array<string, string> normalized campaign name => ENABLED / PAUSED / ARCHIVED
     */
    protected function summaryStatusMap(): array
    {
        $map = [];
        foreach (['L30', 'L15', 'L7', 'L1'] as $range) { // first range with a row wins
            $ids = DB::table(self::SP_TABLE)
                ->where('report_date_range', $range)
                ->whereNotNull('campaignName')
                ->where('campaignName', '!=', '')
                ->selectRaw('MAX(id) AS max_id')
                ->groupBy('campaignName')
                ->pluck('max_id')
                ->filter()
                ->all();
            foreach (array_chunk($ids, 2000) as $chunk) {
                foreach (DB::table(self::SP_TABLE)->whereIn('id', $chunk)->get(['campaignName', 'campaignStatus']) as $r) {
                    $key = AmazonAdsMissingLinks::normalizeCampaignName((string) $r->campaignName);
                    if ($key !== '' && ! isset($map[$key])) {
                        $map[$key] = strtoupper(trim((string) $r->campaignStatus));
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Campaign ids whose ads advertise a SKU containing the search text (amazon_ads_campaign_skus, archived ads ignored).
     *
     * @return array<string, true> campaign_id => true
     */
    protected function campaignIdsAdvertisingSku(string $search, bool $exact = false): array
    {
        $search = trim(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $search)) ?? '');
        if (mb_strlen($search) < 3 || ! $this->hasTable('amazon_ads_campaign_skus')) {
            return [];
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        $out = [];
        foreach (DB::table('amazon_ads_campaign_skus')
            ->when($exact, fn ($q) => $q->whereRaw('TRIM(sku) = ?', [$search]), fn ($q) => $q->where('sku', 'like', $like))
            ->where(function ($q) {
                $q->whereNull('state')->orWhereRaw("UPPER(state) <> 'ARCHIVED'");
            })
            ->whereNotNull('campaign_id')
            ->distinct()
            ->limit(5000)
            ->pluck('campaign_id') as $cid) {
            $out[(string) $cid] = true;
        }

        return $out;
    }

    private const LIVE_CAMPAIGNS = 'amz_fbm_live_campaigns';

    private const LIVE_LOCK = 'amz_fbm_live_campaigns_lock';

    /** Live campaign states older than this are refreshed in the background. */
    private const LIVE_STALE_SECONDS = 1800;

    /**
     * Live SP campaign states from Amazon (name / id => state), refreshed in the background so the page never waits.
     *
     * @return array{at: int, byName: array<string, array{state: string, id: string, name: string}>, byId: array<string, array{state: string, name: string}>}|null
     */
    protected function liveCampaigns(): ?array
    {
        $live = Cache::get(self::LIVE_CAMPAIGNS);
        $live = is_array($live) ? $live : null;
        if ($live === null || (time() - (int) ($live['at'] ?? 0)) > self::LIVE_STALE_SECONDS) {
            // One refresh at a time, and not more often than every 10 minutes even if it keeps failing.
            if (Cache::add(self::LIVE_LOCK, 1, now()->addMinutes(10))) {
                try {
                    $this->spawnArtisan('app:amazon-fbm-campaigns-sync', 'fbm-targeting-live-campaigns.log');
                } catch (\Throwable $e) {
                    Log::warning('AmazonFbmTargetingController: live campaign sync spawn failed', ['error' => $e->getMessage()]);
                }
            }
        }

        return $live;
    }

    /**
     * Read every SP campaign (with its live state) from Amazon and store it for the page. Runs in the background command.
     *
     * @return int number of campaigns read
     */
    public function syncLiveCampaigns(AmazonAdsService $ads): int
    {
        $ads->reuseAccessToken(); // one access token for all the pages
        $campaigns = $ads->listAllSpCampaigns(['ENABLED', 'PAUSED', 'ARCHIVED']);
        $rank = ['ENABLED' => 3, 'PAUSED' => 2, 'ARCHIVED' => 1];
        $byName = [];
        $byId = [];
        foreach ($campaigns as $c) {
            $name = trim((string) ($c['name'] ?? ''));
            $id = trim((string) ($c['campaignId'] ?? ''));
            $state = strtoupper(trim((string) ($c['state'] ?? '')));
            if ($name === '' || $state === '') {
                continue;
            }
            $key = AmazonAdsMissingLinks::normalizeCampaignName($name);
            // The same name can exist more than once (an archived old campaign + a live one): the live one wins.
            if (! isset($byName[$key]) || ($rank[$state] ?? 0) > ($rank[$byName[$key]['state']] ?? 0)) {
                $byName[$key] = ['state' => $state, 'id' => $id, 'name' => $name];
            }
            if ($id !== '') {
                $byId[$id] = ['state' => $state, 'name' => $name];
            }
        }

        Cache::put(self::LIVE_CAMPAIGNS, ['at' => time(), 'byName' => $byName, 'byId' => $byId], now()->addHours(12));
        Cache::forget('amz_kw_targeting_campaigns_by_parent_v8');
        $this->forgetBadges();

        return count($campaigns);
    }

    /**
     * Push the app's keywords to Amazon: missing keywords go to every KW campaign (SP) and, per parent,
     * to its linked HL campaign (SB). dry_run=1 only reports what would be sent.
     */
    public function pushKeywords(Request $request, AmazonKwTargetingPushService $push): JsonResponse
    {
        $dryRun = filter_var($request->input('dry_run', true), FILTER_VALIDATE_BOOLEAN);

        // The real push sends tens of thousands of keywords - far longer than a web request may last - so it runs
        // in a background process; the page polls pushStatus() for progress.
        if (! $dryRun) {
            // Only the campaigns ticked in the check ("KW|<id>" / "HL|<id>") are pushed; none given = all of them.
            $v = $request->validate([
                'selected' => ['nullable', 'array', 'max:5000'],
                'selected.*' => ['string', 'regex:/^(KW|HL)\|\d{1,32}$/'],
                'campaign_ids' => ['nullable', 'array', 'max:5000'],
                'campaign_ids.*' => ['string', 'regex:/^\d{1,32}$/'],
            ]);
            $selected = array_key_exists('selected', $v) && $v['selected'] !== null ? array_values(array_unique($v['selected'])) : null;
            if ($selected === []) {
                return response()->json(['success' => false, 'message' => 'Select at least one campaign to push.'], 422);
            }
            // Bulk push from the grid: only these KW campaigns (and the HL campaigns of their parents) are looked at.
            $campaignIds = ! empty($v['campaign_ids']) ? array_values(array_unique($v['campaign_ids'])) : null;

            return $this->startBackgroundPush($selected, $campaignIds);
        }

        @set_time_limit(300);

        $v = $request->validate([
            'campaign_ids' => ['nullable', 'array', 'max:5000'],
            'campaign_ids.*' => ['string', 'regex:/^\d{1,32}$/'],
        ]);
        $campaignIds = ! empty($v['campaign_ids']) ? array_values(array_unique($v['campaign_ids'])) : null;

        // The check lists the live keywords of every campaign on Amazon (about a minute for hundreds of campaigns),
        // so it runs in the background too and the page polls checkStatus(); the modal can never hang on a request.
        $token = bin2hex(random_bytes(8));
        Cache::put(self::CHECK_PREFIX.$token, ['status' => 'running', 'heartbeat' => time(), 'message' => 'Starting the check…'], now()->addHour());
        Cache::put(self::CHECK_PREFIX.'ids_'.$token, $campaignIds, now()->addHour());
        try {
            $this->spawnArtisan('app:amazon-kw-targeting-push --check='.$token, 'fbm-targeting-push.log');

            return response()->json(['success' => true, 'dry_run' => true, 'check' => true, 'token' => $token, 'message' => 'Check started in the background.']);
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: check spawn failed, running it inline', ['error' => $e->getMessage()]);
            Cache::forget(self::CHECK_PREFIX.$token);
        }

        $r = $push->run($this->kwCampaignsForPush($campaignIds), true);
        if ($r['errors'] !== [] && $r['rows'] === []) {
            return response()->json(['success' => false, 'message' => implode(' ', $r['errors']), 'dry_run' => true], 502);
        }

        return response()->json($this->pushPayload($r, true));
    }

    private const CHECK_PREFIX = 'amz_fbm_check_';

    /** The check is treated as dead when its background process has not reported for this long. */
    private const CHECK_STALE_SECONDS = 180;

    /** Result of a background check started by pushKeywords(dry_run). */
    public function checkStatus(Request $request): JsonResponse
    {
        $token = (string) $request->query('token', '');
        if (! preg_match('/^[a-f0-9]{16}$/', $token)) {
            return response()->json(['success' => false, 'status' => 'error', 'message' => 'Unknown check.'], 422);
        }
        $s = Cache::get(self::CHECK_PREFIX.$token);
        if (! is_array($s)) {
            return response()->json(['success' => false, 'status' => 'error', 'message' => 'The check expired. Close this window and try again.']);
        }
        if (($s['status'] ?? '') === 'running' && (time() - (int) ($s['heartbeat'] ?? 0)) >= self::CHECK_STALE_SECONDS) {
            $s = ['status' => 'error', 'message' => 'The background check stopped (see storage/logs/fbm-targeting-push.log). Try again.'];
        }
        unset($s['heartbeat']);

        return response()->json(['success' => ($s['status'] ?? '') !== 'error'] + $s);
    }

    /** Background worker side of the check: campaign ids picked for a given token (null = every campaign). */
    public function checkCampaignIds(string $token): ?array
    {
        $ids = Cache::get(self::CHECK_PREFIX.'ids_'.$token);

        return is_array($ids) && $ids !== [] ? array_values($ids) : null;
    }

    /** @param  array<string, mixed>  $state */
    public function recordCheckState(string $token, array $state): void
    {
        Cache::put(self::CHECK_PREFIX.$token, $state + ['heartbeat' => time()], now()->addHour());
    }

    /** Non-PT SP campaigns that can receive keywords. */
    public function kwCampaignsForPush(?array $onlyCampaignIds = null, bool $withCousins = false): array
    {
        $only = $onlyCampaignIds === null ? null : array_flip(array_map('strval', $onlyCampaignIds));

        $eligible = array_values(array_filter(
            $this->withoutArchived($this->campaignsGroupedByParent()), // archived campaigns cannot take keywords
            fn (array $c) => $c['campaign_id'] !== '' && ! $this->isProductTargetingCampaign($c['campaign_name'])
        ));
        if ($only === null) {
            return $eligible;
        }

        // Picked campaigns also push to the campaigns they are linked to as KW cousins (and only those).
        if ($withCousins) {
            $only = $this->withLinkedCousinCampaigns($only, $eligible);
        }

        return array_values(array_filter($eligible, static fn (array $c) => isset($only[$c['campaign_id']])));
    }

    /**
     * Adds the KW-cousin campaigns of the picked campaigns to the picked set, so linking a SKU campaign as a cousin
     * and pushing the picked campaign sends the targets to the linked SKU campaign too - and to no other campaign.
     * The cousins' keywords are first unioned in the app's keyword store (local data, no Amazon call), exactly as the
     * automatic cousin sync does, so the push has the linked keywords to send.
     *
     * @param  array<string, int>  $only  campaign id => index (array_flip)
     * @param  list<array{campaign_id: string, campaign_name: string}>  $eligible  non-PT, non-archived SP campaigns
     * @return array<string, int>
     */
    protected function withLinkedCousinCampaigns(array $only, array $eligible): array
    {
        if (! $this->hasTable(self::COUSIN_TABLE)) {
            return $only;
        }
        try {
            $groupsMap = $this->cousins->groupsMap();
            $index = $this->campaignIndex();
            $byId = [];
            foreach ($eligible as $c) {
                $byId[(string) $c['campaign_id']] = $c;
            }

            $synced = [];
            foreach (array_keys($only) as $cid) {
                $c = $byId[(string) $cid] ?? null;
                if ($c === null) {
                    continue;
                }
                $group = $this->effectiveGroup($c['campaign_name'], $groupsMap);
                $cousins = $this->cousinCampaignNames($group, $c['campaign_name']);
                if ($cousins === []) {
                    continue;
                }
                foreach ($cousins as $member) {
                    $hit = $index[$this->cousins->normalize((string) $member)] ?? null;
                    $mid = (string) ($hit['campaign_id'] ?? '');
                    if ($mid !== '' && isset($byId[$mid])) {
                        $only[$mid] = 1;
                    }
                }
                $norms = array_map(fn ($n) => $this->cousins->normalize((string) $n), $group);
                sort($norms);
                $key = md5(implode('|', $norms));
                if (! isset($synced[$key])) {
                    $synced[$key] = true;
                    $this->syncCousinKeywords($group, false, 'kw');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: linked cousin campaigns for push failed', ['error' => $e->getMessage()]);
        }

        return $only;
    }

    /**
     * @param  array{rows: list<array<string, mixed>>, totals: array<string, int>, errors: list<string>}  $r
     * @return array<string, mixed>
     */
    public function pushPayload(array $r, bool $dryRun): array
    {
        $t = $r['totals'];
        $message = $dryRun
            ? 'Ready to push '.$t['kw_to_add'].' keyword(s) to '.$t['kw_campaigns'].' KW campaign(s) and '
                .$t['hl_to_add'].' to '.$t['hl_campaigns'].' HL campaign(s).'
            : 'Pushed '.$t['added'].' keyword(s)'.($t['failed'] > 0 ? ', '.$t['failed'].' rejected/failed' : '').'.';

        return [
            'success' => true,
            'dry_run' => $dryRun,
            'message' => $message,
            'totals' => $t,
            'rows' => $r['rows'],
            'errors' => $r['errors'],
            'up_to_date' => $r['up_to_date'] ?? [],
        ];
    }

    private const PUSH_STATE = 'amz_fbm_push_state';

    private const PUSH_LOCK = 'amz_fbm_push_lock';

    private const PUSH_SELECTION = 'amz_fbm_push_selection';

    /** KW campaign ids picked in the grid for a bulk push (null = every campaign). */
    private const PUSH_CAMPAIGNS = 'amz_fbm_push_campaigns';

    /** The push is treated as dead when its background process has not reported progress for this long. */
    private const PUSH_STALE_SECONDS = 300;

    /**
     * Start the real push in the background (one at a time) and return at once.
     */
    protected function startBackgroundPush(?array $selected = null, ?array $campaignIds = null): JsonResponse
    {
        $state = Cache::get(self::PUSH_STATE);
        $alive = is_array($state) && ($state['status'] ?? '') === 'running'
            && (time() - (int) ($state['heartbeat'] ?? 0)) < self::PUSH_STALE_SECONDS;
        if ($alive) {
            return response()->json(['success' => true, 'started' => true, 'already' => true, 'message' => 'A push is already running.'] + $this->pushStatusBody());
        }

        Cache::forget(self::PUSH_LOCK);
        Cache::put(self::PUSH_LOCK, 1, now()->addHours(3));
        Cache::put(self::PUSH_SELECTION, $selected, now()->addHours(6)); // read by the background command
        Cache::put(self::PUSH_CAMPAIGNS, $campaignIds, now()->addHours(6));
        Cache::put(self::PUSH_STATE, [
            'status' => 'running', 'started_at' => now()->toDateTimeString(), 'heartbeat' => time(),
            'by' => Auth::user()?->name,
            'message' => 'Starting…', 'totals' => null, 'rows' => [], 'errors' => [],
        ], now()->addHours(6));

        try {
            $this->spawnArtisan('app:amazon-kw-targeting-push', 'fbm-targeting-push.log');
        } catch (\Throwable $e) {
            Cache::forget(self::PUSH_LOCK);
            Cache::forget(self::PUSH_STATE);
            Log::warning('AmazonFbmTargetingController: push spawn failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Could not start the push: '.$e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'started' => true, 'message' => 'Push started in the background.'] + $this->pushStatusBody());
    }

    /**
     * Progress of the background push (polled by the page).
     */
    public function pushStatus(): JsonResponse
    {
        return response()->json(['success' => true] + $this->pushStatusBody());
    }

    /**
     * @return array<string, mixed>
     */
    protected function pushStatusBody(): array
    {
        $s = Cache::get(self::PUSH_STATE);
        if (! is_array($s)) {
            return ['status' => 'idle'];
        }
        if (($s['status'] ?? '') === 'running' && (time() - (int) ($s['heartbeat'] ?? 0)) >= self::PUSH_STALE_SECONDS) {
            $s['status'] = 'error';
            $s['message'] = 'The background push stopped reporting progress (see storage/logs/fbm-targeting-push.log). Keywords already sent are kept; run the check again and push the rest.';
            Cache::put(self::PUSH_STATE, $s, now()->addHours(6));
            Cache::forget(self::PUSH_LOCK);
        }
        unset($s['heartbeat']);

        return $s;
    }

    /**
     * Run an artisan command detached from the web request.
     */
    protected function spawnArtisan(string $command, string $logFile): void
    {
        $php = PHP_BINARY ?: 'php';
        if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
            $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
            if ($cli !== '') {
                $php = $cli;
            }
        }
        $artisan = base_path('artisan');
        $log = storage_path('logs/'.$logFile);
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            pclose(popen('start /B '.escapeshellarg($php).' '.escapeshellarg($artisan).' '.$command, 'r'));

            return;
        }
        exec('nohup '.escapeshellarg($php).' '.escapeshellarg($artisan).' '.$command.' >> '.escapeshellarg($log).' 2>&1 &');
    }

    /**
     * Campaigns picked for the running push ("KW|<id>" / "HL|<id>"); null = all.
     *
     * @return list<string>|null
     */
    public function pushSelection(): ?array
    {
        $sel = Cache::get(self::PUSH_SELECTION);

        return is_array($sel) ? array_values($sel) : null;
    }

    /**
     * Grid-picked KW campaign ids of the running push; null = every campaign.
     *
     * @return list<string>|null
     */
    public function pushCampaignIds(): ?array
    {
        $ids = Cache::get(self::PUSH_CAMPAIGNS);

        return is_array($ids) && $ids !== [] ? array_values($ids) : null;
    }

    /**
     * Called by the background command: store progress / the final result where pushStatus() reads it.
     *
     * @param  array<string, mixed>  $patch
     */
    public function recordPushState(array $patch, bool $finished = false): void
    {
        $s = Cache::get(self::PUSH_STATE);
        $s = array_merge(is_array($s) ? $s : [], $patch, ['heartbeat' => time()]);
        Cache::put(self::PUSH_STATE, $s, now()->addHours(6));
        if ($finished) {
            Cache::forget(self::PUSH_LOCK);
        }
    }

    // ---------- Sync with Amazon: pull + push together, in chunks, in the background ----------

    private const SYNC_STATE = 'amz_fbm_sync_state';

    private const SYNC_IDS = 'amz_fbm_sync_ids';

    /** Ticked campaigns ("KW|id" / "HL|id") of the push check; null = everything found. */
    private const SYNC_ONLY = 'amz_fbm_sync_only';

    /** KW campaigns handled per chunk (whole parents stay together, so a parent's HL campaign is pushed once). */
    private const SYNC_CHUNK = 20;

    /**
     * POST: start the background sync (one at a time). campaign_ids = only those KW campaigns (and their linked
     * KW cousins); none = every KW campaign. Returns at once; the page polls syncStatus().
     */
    public function startSync(Request $request): JsonResponse
    {
        $v = $request->validate([
            'campaign_ids' => ['nullable', 'array', 'max:5000'],
            'campaign_ids.*' => ['string', 'regex:/^\d{1,32}$/'],
            // The ticks of the push check ("KW|<id>" / "HL|<id>"): only those campaigns are pushed, linked cousins are not added again.
            'selected' => ['nullable', 'array', 'max:5000'],
            'selected.*' => ['string', 'regex:/^(KW|HL)\|\d{1,32}$/'],
        ]);
        $ids = ! empty($v['campaign_ids']) ? array_values(array_unique($v['campaign_ids'])) : null;
        $selected = ! empty($v['selected']) ? array_values(array_unique($v['selected'])) : null;

        $push = Cache::get(self::PUSH_STATE);
        $pushAlive = is_array($push) && ($push['status'] ?? '') === 'running'
            && (time() - (int) ($push['heartbeat'] ?? 0)) < self::PUSH_STALE_SECONDS;
        $state = Cache::get(self::SYNC_STATE);
        $alive = is_array($state) && ($state['status'] ?? '') === 'running'
            && (time() - (int) ($state['heartbeat'] ?? 0)) < self::PUSH_STALE_SECONDS;
        if ($alive || $pushAlive) {
            return response()->json(['success' => true, 'started' => true, 'already' => true] + $this->syncStatusBody());
        }

        Cache::put(self::SYNC_IDS, $ids, now()->addHours(6));
        Cache::put(self::SYNC_ONLY, $selected, now()->addHours(6));
        Cache::put(self::SYNC_STATE, [
            'status' => 'running', 'phase' => 'start', 'percent' => 0, 'started_at' => now()->toDateTimeString(),
            'heartbeat' => time(), 'by' => Auth::user()?->name, 'message' => 'Starting…',
            'campaigns' => 0, 'done' => 0, 'chunk' => 0, 'chunks' => 0,
            'fetched' => 0, 'pulled' => 0, 'to_add' => 0, 'added' => 0, 'failed' => 0, 'rows' => [],
        ], now()->addHours(6));

        try {
            $this->spawnArtisan('app:amazon-kw-targeting-sync', 'fbm-targeting-sync.log');
        } catch (\Throwable $e) {
            Cache::forget(self::SYNC_STATE);
            Log::warning('AmazonFbmTargetingController: sync spawn failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Could not start the sync: '.$e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'started' => true] + $this->syncStatusBody());
    }

    public function syncStatus(): JsonResponse
    {
        return response()->json(['success' => true] + $this->syncStatusBody());
    }

    /** @return array<string, mixed> */
    protected function syncStatusBody(): array
    {
        $s = Cache::get(self::SYNC_STATE);
        if (! is_array($s)) {
            return ['status' => 'idle'];
        }
        if (($s['status'] ?? '') === 'running' && (time() - (int) ($s['heartbeat'] ?? 0)) >= self::PUSH_STALE_SECONDS) {
            $s['status'] = 'error';
            $s['message'] = 'The background sync stopped reporting progress (see storage/logs/fbm-targeting-sync.log). Keywords already sent are kept; start it again to continue.';
            Cache::put(self::SYNC_STATE, $s, now()->addHours(6));
        }
        unset($s['heartbeat']);

        return $s;
    }

    /** @param  array<string, mixed>  $patch */
    public function recordSyncState(array $patch): void
    {
        $s = Cache::get(self::SYNC_STATE);
        Cache::put(self::SYNC_STATE, array_merge(is_array($s) ? $s : [], $patch, ['heartbeat' => time()]), now()->addHours(6));
    }

    /**
     * The sync itself (background command): per chunk of campaigns, first pull their live keywords from Amazon, then -
     * after the KW cousins were synced - push what is missing. Progress is stored for syncStatus().
     *
     * @return array<string, mixed>
     */
    public function runSync(): array
    {
        $ids = Cache::get(self::SYNC_IDS);
        $ids = is_array($ids) && $ids !== [] ? array_values($ids) : null;

        $only = Cache::get(self::SYNC_ONLY);
        $only = is_array($only) && $only !== [] ? array_values($only) : null;

        // Only the campaigns that were asked for: linked KW cousins are never added behind the user's back (the push check
        // lists them and lets the user tick them).
        $camps = $this->kwCampaignsForPush($ids, false);
        if ($camps === []) {
            return ['message' => 'No KW campaigns to sync.', 'campaigns' => 0];
        }

        // Whole parents per chunk.
        $byParent = [];
        foreach ($camps as $c) {
            $key = strtoupper(trim((string) ($c['parent'] ?? '')));
            $byParent[$key !== '' ? $key : 'ID|'.$c['campaign_id']][] = $c;
        }
        $chunks = [];
        $cur = [];
        foreach ($byParent as $list) {
            $cur = array_merge($cur, $list);
            if (count($cur) >= self::SYNC_CHUNK) {
                $chunks[] = $cur;
                $cur = [];
            }
        }
        if ($cur !== []) {
            $chunks[] = $cur;
        }
        $total = count($camps);
        $n = count($chunks);
        $this->recordSyncState(['campaigns' => $total, 'chunks' => $n, 'phase' => 'pull', 'message' => 'Reading keywords from Amazon…']);

        // 1) Pull
        $fetched = 0;
        $pulled = 0;
        $done = 0;
        foreach ($chunks as $i => $chunk) {
            $names = [];
            foreach ($chunk as $c) {
                $names[$c['campaign_id']] = $c['campaign_name'];
            }
            $r = $this->ingestKeywords($names, 'kw');
            $fetched += $r['fetched'];
            $pulled += $r['added'];
            $done += count($chunk);
            $this->recordSyncState([
                'phase' => 'pull', 'chunk' => $i + 1, 'done' => $done, 'fetched' => $fetched, 'pulled' => $pulled,
                'percent' => (int) round(45 * $done / $total),
                'message' => 'Step 1/2 · Pulling from Amazon: '.$done.' / '.$total.' campaigns (chunk '.($i + 1).' of '.$n.')',
            ]);
        }

        // 2) Share keywords between KW cousins (local data) so the push has the linked keywords.
        $this->recordSyncState(['phase' => 'cousins', 'percent' => 47, 'message' => 'Syncing KW cousins…']);
        try {
            if ($only !== null) {
                // ticks are final: the keywords were shared between cousins when the check ran
            } elseif ($ids === null) {
                if ($this->hasTable(self::COUSIN_TABLE)) {
                    $seen = [];
                    foreach ($this->cousins->groupsMap() as $group) {
                        $k = implode('|', $group);
                        if (! isset($seen[$k])) {
                            $seen[$k] = true;
                            $this->syncCousinKeywords($group, false, 'kw');
                        }
                    }
                }
            } else {
                $this->withLinkedCousinCampaigns(array_flip(array_map(static fn (array $c) => (string) $c['campaign_id'], $camps)), $camps);
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: sync cousin step failed', ['error' => $e->getMessage()]);
        }

        // 3) Push
        $push = app(AmazonKwTargetingPushService::class);
        $rows = [];
        $errors = [];
        $tot = ['kw_campaigns' => 0, 'hl_campaigns' => 0, 'to_add' => 0, 'added' => 0, 'failed' => 0];
        $done = 0;
        foreach ($chunks as $i => $chunk) {
            $label = 'Step 2/2 · Pushing to Amazon: chunk '.($i + 1).' of '.$n;
            $this->recordSyncState(['phase' => 'push', 'chunk' => $i + 1, 'message' => $label]);
            $r = $push->run($chunk, false, function () use ($label): void {
                $this->recordSyncState(['message' => $label]); // heartbeat while a chunk is running
            }, $only);
            foreach ($r['errors'] as $e) {
                $errors[] = $e;
            }
            foreach ($r['rows'] as $row) {
                $tot[$row['type'] === 'HL' ? 'hl_campaigns' : 'kw_campaigns']++;
                $tot['to_add'] += (int) $row['to_add'];
                $tot['added'] += (int) $row['added'];
                $tot['failed'] += (int) $row['failed'];
                $rows[] = $row;
            }
            $done += count($chunk);
            $this->recordSyncState([
                'done' => $done, 'to_add' => $tot['to_add'], 'added' => $tot['added'], 'failed' => $tot['failed'],
                'percent' => 50 + (int) round(50 * $done / $total),
                'rows' => array_slice($rows, -200),
                'message' => $label.' · '.number_format($tot['added']).' keyword(s) pushed'.($tot['failed'] ? ', '.number_format($tot['failed']).' rejected' : ''),
            ]);
        }
        $this->forgetBadges();

        return [
            'message' => 'Synced '.$total.' KW campaign(s): '.number_format($pulled).' keyword(s) pulled from Amazon, '
                .number_format($tot['added']).' pushed'.($tot['failed'] ? ', '.number_format($tot['failed']).' rejected' : '')
                .' ('.$tot['kw_campaigns'].' KW + '.$tot['hl_campaigns'].' HL campaign(s) needed keywords).',
            'campaigns' => $total, 'fetched' => $fetched, 'pulled' => $pulled,
            'to_add' => $tot['to_add'], 'added' => $tot['added'], 'failed' => $tot['failed'],
            'rows' => array_slice($rows, -200), 'errors' => array_slice($errors, 0, 5),
        ];
    }

    // ---------- Undo a push: archive the keywords a push run sent ----------

    private const LOG_TABLE = 'amazon_kw_push_log';

    /** GET: the latest push runs that can be undone. */
    public function pushRuns(): JsonResponse
    {
        if (! $this->hasTable(self::LOG_TABLE)) {
            return response()->json(['success' => true, 'runs' => []]);
        }
        $runs = DB::table(self::LOG_TABLE)
            ->selectRaw('run_token, MIN(pushed_at) AS pushed_at, MAX(pushed_by) AS pushed_by, COUNT(DISTINCT campaign_id) AS campaigns, COUNT(*) AS keywords, SUM(undone_at IS NOT NULL) AS undone')
            ->groupBy('run_token')
            ->orderByDesc('pushed_at')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'run_token' => $r->run_token, 'pushed_at' => (string) $r->pushed_at, 'pushed_by' => $r->pushed_by,
                'campaigns' => (int) $r->campaigns, 'keywords' => (int) $r->keywords, 'undone' => (int) $r->undone,
            ])->all();

        return response()->json(['success' => true, 'runs' => $runs]);
    }

    /** POST: start undoing one push run in the background (progress through syncStatus()). */
    public function startUndo(Request $request): JsonResponse
    {
        $v = $request->validate(['run_token' => ['required', 'string', 'regex:/^[A-Za-z0-9\-]{6,40}$/']]);
        $token = $v['run_token'];
        if (! $this->hasTable(self::LOG_TABLE) || ! DB::table(self::LOG_TABLE)->where('run_token', $token)->whereNull('undone_at')->exists()) {
            return response()->json(['success' => false, 'message' => 'Nothing left to undo for that push.'], 422);
        }
        $state = Cache::get(self::SYNC_STATE);
        $alive = is_array($state) && ($state['status'] ?? '') === 'running'
            && (time() - (int) ($state['heartbeat'] ?? 0)) < self::PUSH_STALE_SECONDS;
        if ($alive) {
            return response()->json(['success' => true, 'started' => true, 'already' => true] + $this->syncStatusBody());
        }
        Cache::put(self::SYNC_STATE, [
            'status' => 'running', 'phase' => 'undo', 'percent' => 0, 'started_at' => now()->toDateTimeString(),
            'heartbeat' => time(), 'by' => Auth::user()?->name, 'message' => 'Starting the undo…',
            'campaigns' => 0, 'done' => 0, 'chunk' => 0, 'chunks' => 0, 'fetched' => 0, 'pulled' => 0, 'to_add' => 0, 'added' => 0, 'failed' => 0, 'rows' => [],
        ], now()->addHours(6));
        try {
            $this->spawnArtisan('app:amazon-kw-targeting-undo --run='.$token, 'fbm-targeting-sync.log');
        } catch (\Throwable $e) {
            Cache::forget(self::SYNC_STATE);

            return response()->json(['success' => false, 'message' => 'Could not start the undo: '.$e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'started' => true] + $this->syncStatusBody());
    }

    /**
     * The undo itself (background command): for each campaign of the run, find the live keywords that match what the push
     * sent AND were created at or after the push, archive exactly those, drop them from the app store, mark the log.
     *
     * @return array<string, mixed>
     */
    public function runUndo(string $token): array
    {
        $log = DB::table(self::LOG_TABLE)->where('run_token', $token)->whereNull('undone_at')->get();
        if ($log->isEmpty()) {
            return ['message' => 'Nothing left to undo for that push.', 'campaigns' => 0];
        }
        $byCampaign = [];
        foreach ($log as $row) {
            $byCampaign[(string) $row->campaign_id][] = $row;
        }
        $campaignIds = array_keys($byCampaign);
        $total = count($campaignIds);
        $this->recordSyncState(['phase' => 'undo', 'campaigns' => $total, 'chunks' => (int) ceil($total / 20), 'message' => 'Undo: reading the live keywords…']);

        $archived = 0;
        $failed = 0;
        $done = 0;
        foreach (array_chunk($campaignIds, 20) as $i => $chunk) {
            $live = $this->ads->listKeywordsWithCreationDates($chunk);
            $liveByCampaign = [];
            foreach ($live as $kw) {
                $liveByCampaign[(string) ($kw['campaignId'] ?? '')][] = $kw;
            }
            foreach ($chunk as $cid) {
                $since = null;
                $want = [];
                foreach ($byCampaign[$cid] as $row) {
                    $want[mb_strtolower(trim((string) $row->keyword_text)).'|'.strtoupper((string) $row->match_type)] = $row;
                    $t = strtotime((string) $row->pushed_at.' '.config('app.timezone', 'UTC'));
                    $since = $since === null ? $t : min($since, $t);
                }
                $ids = [];
                $hit = [];
                foreach ($liveByCampaign[$cid] ?? [] as $kw) {
                    $k = mb_strtolower(trim((string) ($kw['keywordText'] ?? ''))).'|'.strtoupper((string) ($kw['matchType'] ?? ''));
                    if (! isset($want[$k])) {
                        continue;
                    }
                    // only keywords created by the push - an older identical keyword is never touched
                    $created = strtotime((string) ($kw['extendedData']['creationDateTime'] ?? ''));
                    if ($created === false || $since === null || $created < $since - 600) {
                        continue;
                    }
                    $ids[] = (string) $kw['keywordId'];
                    $hit[] = $want[$k];
                }
                if ($ids === []) {
                    continue;
                }
                $r = $this->ads->archiveSpKeywords($ids);
                $archived += $r['archived'];
                $failed += $r['failed'];
                if ($r['failed'] === 0) {
                    $byMatch = [];
                    foreach ($hit as $h) {
                        $byMatch[strtoupper((string) $h->match_type)][] = $h;
                    }
                    foreach ($byMatch as $match => $hits) {
                        foreach (array_chunk($hits, 300) as $hitChunk) {
                            // only this match type's stored rows: the same text with another match type is a different keyword
                            DB::table(self::STORE_TABLE)->where('campaign_id', $cid)->where('match_type', $match)
                                ->whereIn('keyword_key', array_map(static fn ($h) => (string) $h->keyword_key, $hitChunk))->delete();
                            DB::table(self::LOG_TABLE)->whereIn('id', array_map(static fn ($h) => $h->id, $hitChunk))->update(['undone_at' => now()]);
                        }
                    }
                }
            }
            $done += count($chunk);
            $this->recordSyncState([
                'done' => $done, 'chunk' => $i + 1, 'added' => $archived, 'failed' => $failed,
                'percent' => (int) round(100 * $done / $total),
                'message' => 'Undo: archiving on Amazon, '.$done.' / '.$total.' campaigns · '.number_format($archived).' keyword(s) archived'.($failed ? ', '.number_format($failed).' failed' : ''),
            ]);
        }
        $this->forgetBadges();

        return [
            'message' => 'Undo finished: '.number_format($archived).' keyword(s) archived on Amazon in '.$total.' campaign(s)'.($failed ? ', '.number_format($failed).' could not be archived' : '').'.',
            'campaigns' => $total, 'added' => $archived, 'failed' => $failed,
        ];
    }

    /**
     * Manually added keywords per campaign (independent of imports and KW cousins).
     *
     * @param  list<string>  $campaignIds
     * @return array<string, list<array{id: int, keyword: string, match_type: string}>>
     */
    protected function specificByCampaign(array $campaignIds, string $table = self::SPECIFIC_TABLE): array
    {
        $campaignIds = array_values(array_unique(array_filter($campaignIds)));
        if ($campaignIds === [] || ! $this->hasTable($table)) {
            return [];
        }

        $out = [];
        $rows = DB::table($table)
            ->whereIn('campaign_id', $campaignIds)
            ->orderByRaw('LOWER(keyword)')
            ->get(['id', 'campaign_id', 'keyword', 'match_type']);
        foreach ($rows as $r) {
            $out[(string) $r->campaign_id][] = [
                'id' => (int) $r->id,
                'keyword' => (string) $r->keyword,
                'match_type' => (string) $r->match_type,
            ];
        }

        return $out;
    }

    /**
     * Add Specific KW by hand to one campaign. One keyword per line (or comma separated).
     * Existing keywords (same text + match type) are skipped.
     */
    public function specificAdd(Request $request): JsonResponse
    {
        $v = $request->validate([
            'campaign_id' => ['required', 'string', 'max:64'],
            'keywords' => ['required', 'string', 'max:20000'],
            'match_type' => ['nullable', 'string', 'max:32'],
            'kind' => ['nullable', 'string', 'in:kw,neg'],
        ]);

        $neg = ($v['kind'] ?? 'kw') === 'neg';
        $table = $neg ? self::SPECIFIC_NEG_TABLE : self::SPECIFIC_TABLE;
        $match = strtoupper(trim((string) ($v['match_type'] ?? '')));
        $allowed = $neg ? ['', 'NEGATIVE_EXACT', 'NEGATIVE_PHRASE'] : ['', 'BROAD', 'PHRASE', 'EXACT'];
        if (! in_array($match, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid match type.'], 422);
        }

        if (! $this->hasTable($table)) {
            return response()->json(['success' => false, 'message' => 'Table is missing. Run php artisan migrate.'], 500);
        }

        $cid = trim($v['campaign_id']);
        $campaign = null;
        foreach ($this->campaignsGroupedByParent() as $c) {
            if ($c['campaign_id'] === $cid) {
                $campaign = $c;
                break;
            }
        }
        if ($campaign === null) {
            return response()->json(['success' => false, 'message' => 'Unknown campaign.'], 422);
        }

        $now = now();
        $user = Auth::user()?->name;

        $seen = [];
        $rows = [];
        foreach (preg_split('/[\r\n,]+/', $v['keywords']) ?: [] as $raw) {
            $text = trim((string) preg_replace('/\s+/u', ' ', (string) $raw));
            if ($text === '') {
                continue;
            }
            $key = AmazonKwTargetingKeyword::normalizeKey($text);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = [
                'campaign_id' => $cid,
                'campaign_name' => $campaign['campaign_name'],
                'keyword' => mb_substr($text, 0, 512),
                'keyword_key' => $key,
                'match_type' => $match,
                'added_by' => $user,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows === []) {
            return response()->json(['success' => false, 'message' => 'Enter at least one keyword.'], 422);
        }

        $added = DB::table($table)->insertOrIgnore($rows);
        $skipped = count($rows) - $added;
        $this->forgetBadges();

        return response()->json([
            'success' => true,
            'message' => $added.' keyword'.($added === 1 ? '' : 's').' added'.($skipped > 0 ? ', '.$skipped.' already existed.' : '.'),
            'added' => $added,
            'skipped' => $skipped,
            'specific' => $this->specificByCampaign([$cid], $table)[$cid] ?? [],
        ]);
    }

    /**
     * Remove one Specific KW.
     */
    public function specificRemove(Request $request): JsonResponse
    {
        $v = $request->validate([
            'campaign_id' => ['required', 'string', 'max:64'],
            'id' => ['required', 'integer'],
            'kind' => ['nullable', 'string', 'in:kw,neg'],
        ]);

        $table = ($v['kind'] ?? 'kw') === 'neg' ? self::SPECIFIC_NEG_TABLE : self::SPECIFIC_TABLE;
        if (! $this->hasTable($table)) {
            return response()->json(['success' => false, 'message' => 'Table is missing. Run php artisan migrate.'], 500);
        }

        $cid = trim($v['campaign_id']);
        DB::table($table)->where('id', (int) $v['id'])->where('campaign_id', $cid)->delete();
        $this->forgetBadges();

        return response()->json([
            'success' => true,
            'message' => 'Keyword removed.',
            'specific' => $this->specificByCampaign([$cid], $table)[$cid] ?? [],
        ]);
    }

    /**
     * Split a displayed target label ("keyword text (MATCH)") into text + match type.
     *
     * @return array{text: string, match: string}
     */
    protected function parseTargetLabel(string $label): array
    {
        $raw = trim($label);
        if (preg_match('/^(.*)\s+\(([A-Za-z][A-Za-z0-9_\/ -]{1,20})\)$/u', $raw, $m)) {
            return ['text' => trim($m[1]), 'match' => strtoupper(trim($m[2]))];
        }

        return ['text' => $raw, 'match' => ''];
    }

    protected function flagLabel(string $key, string $match): string
    {
        return $match !== '' ? $key.' ('.strtolower($match).')' : $key;
    }

    /**
     * Flags (deleted / ignored) of a kind for campaigns: campaign_id => ['keyword_key|MATCH' => status].
     *
     * @param  list<string>  $campaignIds
     * @return array<string, array<string, string>>
     */
    protected function flagMap(string $kind, array $campaignIds): array
    {
        $campaignIds = array_values(array_unique(array_filter(array_map('strval', $campaignIds))));
        if ($campaignIds === [] || ! $this->hasTable(self::FLAG_TABLE)) {
            return [];
        }
        $out = [];
        foreach (array_chunk($campaignIds, 1000) as $chunk) {
            foreach (DB::table(self::FLAG_TABLE)->where('kind', $kind)->whereIn('campaign_id', $chunk)
                ->get(['campaign_id', 'keyword_key', 'match_type', 'status']) as $r) {
                $out[(string) $r->campaign_id][$r->keyword_key.'|'.strtoupper((string) $r->match_type)] = (string) $r->status;
            }
        }

        return $out;
    }

    /**
     * Set of "campaign_id|keyword_key|MATCH" for targets deleted in the modal.
     *
     * @param  list<string|int>  $campaignIds
     * @return array<string, true>
     */
    protected function deletedKeys(string $kind, array $campaignIds): array
    {
        $set = [];
        foreach ($this->flagMap($kind, array_map('strval', $campaignIds)) as $cid => $flags) {
            foreach ($flags as $k => $status) {
                if ($status === 'deleted') {
                    $set[$cid.'|'.$k] = true;
                }
            }
        }

        return $set;
    }

    /**
     * Drop deleted targets from per-campaign label lists and collect the ignored ones.
     *
     * @param  array<string, list<string>>  $map
     * @param  list<string>  $campaignIds
     * @return array{0: array<string, list<string>>, 1: array<string, list<string>>} [lists without deleted, ignored labels]
     */
    protected function applyFlags(array $map, array $campaignIds, string $kind): array
    {
        $flags = $this->flagMap($kind, $campaignIds);
        $ignored = [];
        if ($flags === []) {
            return [$map, $ignored];
        }
        foreach ($map as $cid => $labels) {
            $f = $flags[(string) $cid] ?? null;
            if ($f === null) {
                continue;
            }
            $keep = [];
            foreach ($labels as $label) {
                $p = $this->parseTargetLabel((string) $label);
                $status = $f[AmazonKwTargetingKeyword::normalizeKey($p['text']).'|'.$p['match']] ?? null;
                if ($status === 'deleted') {
                    continue;
                }
                if ($status === 'ignored') {
                    $ignored[$cid][] = $label;
                }
                $keep[] = $label;
            }
            $map[$cid] = $keep;
        }

        return [$map, $ignored];
    }

    /**
     * Campaigns that share keywords with the given one (its KW-cousin group + same-parent siblings), including itself.
     *
     * @return array<string, string> campaign_id => campaign_name
     */
    protected function groupMembers(string $campaignId, string $campaignName): array
    {
        $members = [$campaignId => $campaignName];
        try {
            $index = $this->campaignIndex();
            foreach ($this->effectiveGroup($campaignName) as $name) {
                $hit = $index[$this->cousins->normalize((string) $name)] ?? null;
                if ($hit && $hit['campaign_id'] !== '') {
                    $members[$hit['campaign_id']] = $hit['campaign_name'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: group members lookup failed', ['error' => $e->getMessage()]);
        }

        return $members;
    }

    /**
     * Current target / negative lists of one campaign (what the grid row would show).
     *
     * @return array<string, mixed>
     */
    protected function targetLists(string $campaignId): array
    {
        $targets = $this->mergeStoredKeywords($this->targetsForCampaignIds([$campaignId]), [$campaignId]);
        $negatives = $this->mergeStoredKeywords([], [$campaignId], self::NEG_TABLE);
        [$targets, $ignoredT] = $this->applyFlags($targets, [$campaignId], 'kw');
        [$negatives, $ignoredN] = $this->applyFlags($negatives, [$campaignId], 'neg');

        return [
            'targets' => $targets[$campaignId] ?? [],
            'ignored_targets' => $ignoredT[$campaignId] ?? [],
            'negatives' => $negatives[$campaignId] ?? [],
            'ignored_negatives' => $ignoredN[$campaignId] ?? [],
        ];
    }

    /**
     * Lifetime clicks / sold of each campaign's whole KW cousin group (the campaign, its same-parent siblings and its
     * cousins), keyed by campaign name. Group members are de-duplicated by campaign id.
     *
     * @param  list<array{campaign_id: string, campaign_name: string}>  $campaigns
     * @param  array<string, list<string>>  $groups  groupsMap()
     * @return array<string, array{clicks: int, sold: int, campaigns: int, with_data: int}>
     */
    protected function lifetimeForRows(array $campaigns, array $groups): array
    {
        $memberIds = [];
        $allIds = [];
        try {
            $index = $this->campaignIndex();
            foreach ($campaigns as $c) {
                $ids = $c['campaign_id'] !== '' ? [(string) $c['campaign_id'] => true] : [];
                foreach ($this->effectiveGroup($c['campaign_name'], $groups) as $member) {
                    $cid = $index[$this->cousins->normalize((string) $member)]['campaign_id'] ?? '';
                    if ($cid !== '') {
                        $ids[(string) $cid] = true;
                    }
                }
                $memberIds[$c['campaign_name']] = array_map('strval', array_keys($ids));
                foreach ($ids as $cid => $_) {
                    $allIds[(string) $cid] = true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: cousin group lookup for LT failed', ['error' => $e->getMessage()]);
        }

        $totals = $this->lifetimeCampaignTotals(array_map('strval', array_keys($allIds)));
        $out = [];
        foreach ($memberIds as $name => $ids) {
            $o = ['clicks' => 0, 'sold' => 0, 'campaigns' => count($ids), 'with_data' => 0];
            foreach ($ids as $mid) {
                if (isset($totals[$mid])) {
                    $o['clicks'] += $totals[$mid]['clicks'];
                    $o['sold'] += $totals[$mid]['sold'];
                    $o['with_data']++;
                }
            }
            $out[(string) $name] = $o;
        }

        return $out;
    }

    /**
     * Lifetime clicks and units sold per campaign, summed from the stored DAILY Amazon Ads SP campaign reports
     * (report_date_range = YYYY-MM-DD; the L1 / L7 / L15 / L30 summary rows overlap them and are left out).
     * Same rows and fields as the LT Sold / LT CVR columns on /amazon-ads/all (AmazonAdsController::
     * prefetchLifetimeSpendSalesForPageRows): clicks = SUM(clicks), sold = SUM(purchases30d).
     * Campaigns with no daily rows are absent from the result.
     *
     * @param  list<string>  $campaignIds
     * @return array<string, array{clicks: int, sold: int}>
     */
    protected function lifetimeCampaignTotals(array $campaignIds): array
    {
        $campaignIds = array_values(array_unique(array_filter($campaignIds, static fn ($id) => (string) $id !== '')));
        if ($campaignIds === [] || ! $this->hasTable(self::SP_TABLE)) {
            return [];
        }
        $cols = $this->columnsOf(self::SP_TABLE);
        if (! in_array('campaign_id', $cols, true) || ! in_array('report_date_range', $cols, true) || ! in_array('clicks', $cols, true)) {
            return [];
        }
        $soldCol = in_array('purchases30d', $cols, true) ? 'purchases30d' : (in_array('unitsSoldClicks30d', $cols, true) ? 'unitsSoldClicks30d' : null);

        sort($campaignIds);
        $key = 'amz_fbm_lt_totals_v3:'.md5(implode(',', $campaignIds));

        try {
            return Cache::remember($key, now()->addMinutes(5), function () use ($campaignIds, $soldCol) {
                $out = [];
                // Sponsored Brands (HEAD / HL) campaigns listed in the grid: same sums from the SB daily reports
                // (clicks, purchases), as /amazon-ads/all does for its SB rows.
                $sbTable = 'amazon_sb_campaign_reports';
                if ($this->hasTable($sbTable)) {
                    $sbCols = $this->columnsOf($sbTable);
                    if (in_array('campaign_id', $sbCols, true) && in_array('report_date_range', $sbCols, true) && in_array('clicks', $sbCols, true)) {
                        $sbSold = in_array('purchases', $sbCols, true) ? 'purchases' : (in_array('unitsSold', $sbCols, true) ? 'unitsSold' : null);
                        foreach (array_chunk($campaignIds, 1000) as $chunk) {
                            $sbRows = DB::table($sbTable)
                                ->whereIn('campaign_id', $chunk)
                                ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
                                ->selectRaw(
                                    'campaign_id, SUM(COALESCE(`clicks`, 0)) AS clicks, '
                                    .($sbSold !== null ? 'SUM(COALESCE(`'.$sbSold.'`, 0))' : '0').' AS sold'
                                )
                                ->groupBy('campaign_id')
                                ->get();
                            foreach ($sbRows as $r) {
                                $cid = trim((string) $r->campaign_id);
                                $out[$cid] ??= ['clicks' => 0, 'sold' => 0];
                                $out[$cid]['clicks'] += (int) $r->clicks;
                                $out[$cid]['sold'] += (int) $r->sold;
                            }
                        }
                    }
                }
                foreach (array_chunk($campaignIds, 1000) as $chunk) {
                    $rows = DB::table(self::SP_TABLE)
                        ->whereIn('campaign_id', $chunk)
                        ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
                        ->selectRaw(
                            'campaign_id, SUM(COALESCE(`clicks`, 0)) AS clicks, '
                            .($soldCol !== null ? 'SUM(COALESCE(`'.$soldCol.'`, 0))' : '0').' AS sold'
                        )
                        ->groupBy('campaign_id')
                        ->get();
                    foreach ($rows as $r) {
                        $cid = trim((string) $r->campaign_id);
                        $out[$cid] ??= ['clicks' => 0, 'sold' => 0];
                        $out[$cid]['clicks'] += (int) $r->clicks;
                        $out[$cid]['sold'] += (int) $r->sold;
                    }
                }

                return $out;
            });
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: lifetime totals failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Which days the stored daily SP campaign reports cover (what "lifetime" means here), plus whether a pull
     * from Amazon is already running.
     *
     * @return array{days: int, from: ?string, to: ?string, syncing: bool}
     */
    protected function lifetimeCoverage(): array
    {
        $out = ['days' => 0, 'from' => null, 'to' => null, 'syncing' => Cache::has(self::LT_SYNC_LOCK)];
        try {
            if ($this->hasTable(self::SP_TABLE) && $this->hasColumn(self::SP_TABLE, 'report_date_range')) {
                $c = Cache::remember('amz_fbm_lt_coverage', now()->addMinutes(5), function () {
                    $r = DB::table(self::SP_TABLE)
                        ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
                        ->selectRaw('COUNT(DISTINCT report_date_range) AS d, MIN(report_date_range) AS mn, MAX(report_date_range) AS mx')
                        ->first();

                    return ['days' => (int) ($r->d ?? 0), 'from' => $r->mn ?? null, 'to' => $r->mx ?? null];
                });
                $out = $c + $out;
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: lifetime coverage failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    /**
     * Pull the missing daily SP campaign report days from the Amazon Ads Reporting API (the most Amazon serves is
     * about 95 days) in a background process, so the LT clicks / sold columns fill in. Existing days are skipped.
     */
    public function syncLifetime(): JsonResponse
    {
        if (! Cache::add(self::LT_SYNC_LOCK, now()->toDateTimeString(), now()->addMinutes(60))) {
            return response()->json(['success' => true, 'message' => 'A pull from Amazon is already running (started within the last hour). Reload the page in a few minutes.', 'already' => true]);
        }

        try {
            $php = PHP_BINARY ?: 'php';
            if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
                $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
                if ($cli !== '') {
                    $php = $cli;
                }
            }
            $artisan = base_path('artisan');
            $log = storage_path('logs/fbm-targeting-lt-sync.log');
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                pclose(popen('start /B '.escapeshellarg($php).' '.escapeshellarg($artisan).' app:amazon-ads-backfill-daily --type=sp', 'r'));
            } else {
                exec('nohup '.escapeshellarg($php).' '.escapeshellarg($artisan)
                    .' app:amazon-ads-backfill-daily --type=sp >> '.escapeshellarg($log).' 2>&1 &');
            }
        } catch (\Throwable $e) {
            Cache::forget(self::LT_SYNC_LOCK);
            Log::warning('AmazonFbmTargetingController: LT sync spawn failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Could not start the pull: '.$e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pulling daily campaign reports from Amazon in the background (this can take several minutes). Reload the page when it finishes.',
        ]);
    }

    /**
     * Lifetime (LT) clicks and units sold per keyword of one campaign, accumulated from the stored DAILY Amazon Ads
     * keyword reports (rows whose report_date_range is a date). The L1 / L7 / L15 / L30 windows are left out because
     * they overlap the daily rows. Sold = purchases30d (same as LT Sold on /amazon-ads/all).
     * The figures are totals over every campaign in the campaign's KW cousin group (the campaign, its same-parent
     * siblings and its cousins), because they share the same keywords.
     * Keyed "keyword_key|MATCH" (keyword lower-cased, spaces collapsed) so the page can match its target labels.
     */
    public function targetMetrics(Request $request): JsonResponse
    {
        $cid = trim((string) $request->query('campaign_id', ''));
        $metrics = [];
        $campaignCount = 0;
        if ($cid !== '' && $this->hasTable(self::KW_TABLE)) {
            // Whole cousin group (ids de-duplicated; a campaign id shared by renamed campaigns counts once).
            $ids = [$cid => true];
            foreach ($this->campaignIndex() as $c) {
                if ($c['campaign_id'] === $cid) {
                    foreach (array_keys($this->groupMembers($cid, $c['campaign_name'])) as $mid) {
                        $ids[(string) $mid] = true;
                    }
                    break;
                }
            }
            $groupIds = array_map('strval', array_keys($ids));
            $campaignCount = count($groupIds);

            $cols = $this->columnsOf(self::KW_TABLE);
            $labelCol = in_array('keyword', $cols, true) ? 'keyword' : (in_array('targeting', $cols, true) ? 'targeting' : null);
            $soldCol = in_array('purchases30d', $cols, true) ? 'purchases30d' : (in_array('unitsSoldClicks30d', $cols, true) ? 'unitsSoldClicks30d' : null);
            if ($labelCol !== null && in_array('campaign_id', $cols, true) && in_array('report_date_range', $cols, true)) {
                $matchCol = in_array('matchType', $cols, true) ? 'matchType' : null;
                $clicks = in_array('clicks', $cols, true) ? 'SUM(COALESCE(`clicks`, 0))' : '0';
                $sold = $soldCol !== null ? 'SUM(COALESCE(`'.$soldCol.'`, 0))' : '0';
                $rows = DB::table(self::KW_TABLE)
                    ->whereIn('campaign_id', $groupIds)
                    ->where('report_date_range', 'like', '____-__-__')
                    ->whereNotNull($labelCol)
                    ->selectRaw("`{$labelCol}` as label, ".($matchCol ? "`{$matchCol}`" : "''")." as mt, {$clicks} as clicks, {$sold} as sold")
                    ->groupBy($labelCol)
                    ->when($matchCol, fn ($q) => $q->groupBy($matchCol))
                    ->get();
                foreach ($rows as $r) {
                    $text = trim((string) $r->label);
                    if ($text === '') {
                        continue;
                    }
                    $key = AmazonKwTargetingKeyword::normalizeKey($text).'|'.strtoupper(trim((string) $r->mt));
                    $metrics[$key] ??= ['clicks' => 0, 'sold' => 0];
                    $metrics[$key]['clicks'] += (int) $r->clicks;
                    $metrics[$key]['sold'] += (int) $r->sold;
                }
            }
        }

        return response()->json(['success' => true, 'metrics' => (object) $metrics, 'campaigns' => $campaignCount]);
    }

    /**
     * Bulk edit / delete / ignore on targets (or negative keywords) from the targets modal. The action is applied to
     * the campaign and to every campaign sharing its keywords (KW cousins + same-parent siblings), because the
     * KW-cousin sync would otherwise copy the keywords straight back.
     *  - delete: removed from the app and never re-imported
     *  - ignore / unignore: kept, but skipped when pushing keywords to Amazon
     *  - edit: new text and/or match type (find & replace for several at once)
     * Only the app's data is changed; nothing is sent to Amazon.
     */
    public function bulkTargets(Request $request): JsonResponse
    {
        $v = $request->validate([
            'kind' => ['required', 'string', 'in:kw,neg'],
            'action' => ['required', 'string', 'in:edit,delete,ignore,unignore'],
            'campaign_id' => ['required', 'string', 'max:64'],
            'labels' => ['required', 'array', 'min:1', 'max:5000'],
            'labels.*' => ['required', 'string', 'max:700'],
            'text' => ['nullable', 'string', 'max:512'],
            'find' => ['nullable', 'string', 'max:512'],
            'replace' => ['nullable', 'string', 'max:512'],
            'match_type' => ['nullable', 'string', 'max:32'],
        ]);

        if (! $this->hasTable(self::FLAG_TABLE)) {
            return response()->json(['success' => false, 'message' => 'Table is missing. Run php artisan migrate.'], 500);
        }

        $kind = $v['kind'];
        $action = $v['action'];
        $table = $this->storeTable($kind);
        if (! $this->hasTable($table)) {
            return response()->json(['success' => false, 'message' => 'Table is missing. Run php artisan migrate.'], 500);
        }

        $cid = trim($v['campaign_id']);
        $campaign = null;
        foreach ($this->campaignsGroupedByParent() as $c) {
            if ($c['campaign_id'] === $cid) {
                $campaign = $c;
                break;
            }
        }
        if ($campaign === null) {
            return response()->json(['success' => false, 'message' => 'Unknown campaign.'], 422);
        }

        $newMatch = null;
        if ($action === 'edit' && trim((string) ($v['match_type'] ?? '')) !== '') {
            $newMatch = strtoupper(trim((string) $v['match_type']));
            $allowed = $kind === 'neg' ? ['NEGATIVE_EXACT', 'NEGATIVE_PHRASE'] : ['BROAD', 'PHRASE', 'EXACT'];
            if (! in_array($newMatch, $allowed, true)) {
                return response()->json(['success' => false, 'message' => 'Invalid match type.'], 422);
            }
        }

        // Unique targets (text key + match type) out of the selected labels.
        $items = [];
        foreach ($v['labels'] as $label) {
            $p = $this->parseTargetLabel((string) $label);
            if ($p['text'] === '') {
                continue;
            }
            $items[AmazonKwTargetingKeyword::normalizeKey($p['text']).'|'.$p['match']] = $p;
        }
        if ($items === []) {
            return response()->json(['success' => false, 'message' => 'Nothing selected.'], 422);
        }

        // Edit: work out the new text / match for every item first.
        $edits = [];
        if ($action === 'edit') {
            $single = count($items) === 1;
            $text = trim((string) preg_replace('/\s+/u', ' ', (string) ($v['text'] ?? '')));
            $find = (string) ($v['find'] ?? '');
            $replace = (string) ($v['replace'] ?? '');
            if ($single && $text === '' && $find === '' && $newMatch === null) {
                return response()->json(['success' => false, 'message' => 'Enter a new keyword or choose a match type.'], 422);
            }
            if (! $single && $find === '' && $newMatch === null) {
                return response()->json(['success' => false, 'message' => 'Enter text to find and replace, or choose a match type.'], 422);
            }
            foreach ($items as $oldK => $p) {
                $nt = $p['text'];
                if ($single && $text !== '') {
                    $nt = $text;
                } elseif ($find !== '') {
                    $nt = trim((string) preg_replace('/\s+/u', ' ', str_ireplace($find, $replace, $p['text'])));
                }
                if ($nt === '') {
                    return response()->json(['success' => false, 'message' => 'The edit would leave an empty keyword.'], 422);
                }
                $nm = $newMatch ?? $p['match'];
                $newK = AmazonKwTargetingKeyword::normalizeKey($nt).'|'.$nm;
                if ($newK === $oldK) {
                    continue; // unchanged
                }
                $edits[$oldK] = ['old' => $p, 'text' => $nt, 'match' => $nm];
            }
            if ($edits === []) {
                return response()->json(['success' => true, 'message' => 'Nothing changed.', 'changed' => 0] + $this->targetLists($cid));
            }
        }

        $members = $this->groupMembers($cid, $campaign['campaign_name']);
        $memberIds = array_map('strval', array_keys($members));
        $now = now();
        $user = Auth::user()?->name;

        $flagRow = function (string $campaignId, string $text, string $match, string $status) use ($kind, $now, $user): array {
            $key = AmazonKwTargetingKeyword::normalizeKey($text);

            return [
                'kind' => $kind,
                'campaign_id' => $campaignId,
                'keyword_key' => $key,
                'match_type' => $match,
                'label_lc' => mb_substr($this->flagLabel($key, $match), 0, 320),
                'status' => $status,
                'flagged_by' => $user,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };
        $upsert = function (array $rows): void {
            foreach (array_chunk($rows, 500) as $batch) {
                DB::table(self::FLAG_TABLE)->upsert(
                    $batch,
                    ['kind', 'campaign_id', 'keyword_key', 'match_type'],
                    ['label_lc', 'status', 'flagged_by', 'updated_at']
                );
            }
        };
        $clearFlags = function (array $keys, ?string $onlyStatus = null) use ($kind, $memberIds): void {
            // $keys: list of [keyword_key, match]
            foreach ($keys as [$k, $m]) {
                $q = DB::table(self::FLAG_TABLE)->where('kind', $kind)->whereIn('campaign_id', $memberIds)
                    ->where('keyword_key', $k)->where('match_type', $m);
                if ($onlyStatus !== null) {
                    $q->where('status', $onlyStatus);
                }
                $q->delete();
            }
        };

        $changed = 0;
        DB::transaction(function () use ($action, $items, $edits, $members, $memberIds, $table, $kind, $now, $flagRow, $upsert, $clearFlags, &$changed) {
            if ($action === 'ignore') {
                $rows = [];
                foreach ($items as $p) {
                    foreach ($memberIds as $m) {
                        $rows[] = $flagRow($m, $p['text'], $p['match'], 'ignored');
                    }
                }
                $upsert($rows);
                $changed = count($items);

                return;
            }

            if ($action === 'unignore') {
                $clearFlags(array_map(static fn ($p) => [AmazonKwTargetingKeyword::normalizeKey($p['text']), $p['match']], array_values($items)), 'ignored');
                $changed = count($items);

                return;
            }

            // delete + edit both remove the old target from the store and flag it deleted (so reports / pulls don't revive it)
            $targets = $action === 'edit' ? array_map(static fn ($e) => $e['old'], array_values($edits)) : array_values($items);
            $flags = [];
            foreach ($targets as $p) {
                $key = AmazonKwTargetingKeyword::normalizeKey($p['text']);
                DB::table($table)->whereIn('campaign_id', $memberIds)->where('keyword_key', $key)->where('match_type', $p['match'])->delete();
                foreach ($memberIds as $m) {
                    $flags[] = $flagRow($m, $p['text'], $p['match'], 'deleted');
                }
            }
            $upsert($flags);
            $changed = count($targets);

            if ($action === 'edit') {
                $inserts = [];
                $revive = [];
                foreach ($edits as $e) {
                    $key = AmazonKwTargetingKeyword::normalizeKey($e['text']);
                    $revive[] = [$key, $e['match']];
                    foreach ($members as $mid => $mname) {
                        $row = [
                            'campaign_id' => (string) $mid,
                            'campaign_name' => $mname,
                            'ad_group_id' => null,
                            'keyword_id' => null,
                            'keyword' => mb_substr($e['text'], 0, 512),
                            'keyword_key' => $key,
                            'match_type' => $e['match'],
                            'state' => null,
                            'source' => 'manual',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        if ($kind === 'neg') {
                            $row['level'] = 'CAMPAIGN';
                        }
                        $inserts[] = $row;
                    }
                }
                // A previously deleted/ignored flag on the new text must not hide the edited target.
                $clearFlags($revive);
                foreach (array_chunk($inserts, 500) as $batch) {
                    DB::table($table)->insertOrIgnore($batch);
                }
            }
        });

        $this->forgetBadges();

        $n = $changed;
        $noun = ($kind === 'neg' ? 'negative keyword' : 'target').($n === 1 ? '' : 's');
        $verb = ['edit' => 'updated', 'delete' => 'deleted', 'ignore' => 'ignored', 'unignore' => 'un-ignored'][$action];
        $shared = count($members) > 1 ? ' (applied to '.count($members).' campaigns sharing these keywords)' : '';

        return response()->json([
            'success' => true,
            'message' => $n.' '.$noun.' '.$verb.'.'.$shared,
            'changed' => $n,
            'campaigns' => count($members),
        ] + $this->targetLists($cid));
    }

    private function storeTable(string $kind): string
    {
        return $kind === 'neg' ? self::NEG_TABLE : self::STORE_TABLE;
    }

    /**
     * Pull keywords from Amazon KW campaigns (SP campaigns that are not PT) and store the
     * ones the app does not have yet. Keywords already stored are left untouched and never duplicated.
     * Afterwards every KW-cousin group is synced so cousins share the same keywords.
     */
    public function pullKeywords(): JsonResponse
    {
        return $this->pullKind('kw');
    }

    /**
     * Same as pullKeywords() but for negative keywords.
     */
    public function pullNegatives(): JsonResponse
    {
        return $this->pullKind('neg');
    }

    private function pullKind(string $kind): JsonResponse
    {
        $table = $this->storeTable($kind);

        if (! $this->hasTable(self::SP_TABLE) || ! $this->hasTable($table)) {
            return response()->json([
                'success' => false,
                'message' => 'Required tables are missing. Run php artisan migrate.',
            ], 500);
        }

        // Reading every KW campaign from Amazon takes longer than a web request may last (nginx 120s),
        // so it runs in the background and the page polls pullStatus().
        $key = self::PULL_STATE.'_'.$kind;
        $state = Cache::get($key);
        $alive = is_array($state) && ($state['status'] ?? '') === 'running'
            && (time() - (int) ($state['heartbeat'] ?? 0)) < self::PUSH_STALE_SECONDS;
        if ($alive) {
            return response()->json(['success' => true, 'started' => true, 'already' => true] + $this->pullStatusBody($kind));
        }

        Cache::put($key, [
            'status' => 'running', 'started_at' => now()->toDateTimeString(), 'heartbeat' => time(),
            'message' => 'Starting…',
        ], now()->addHours(2));

        try {
            $this->spawnArtisan('app:amazon-kw-targeting-pull '.$kind, 'fbm-targeting-pull.log');
        } catch (\Throwable $e) {
            Cache::forget($key);
            Log::warning('AmazonFbmTargetingController: pull spawn failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Could not start the pull: '.$e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'started' => true] + $this->pullStatusBody($kind));
    }

    private const PULL_STATE = 'amz_fbm_pull_state';

    public function pullStatus(Request $request): JsonResponse
    {
        $kind = $request->query('kind') === 'neg' ? 'neg' : 'kw';

        return response()->json(['success' => true] + $this->pullStatusBody($kind));
    }

    /**
     * @return array<string, mixed>
     */
    protected function pullStatusBody(string $kind): array
    {
        $key = self::PULL_STATE.'_'.$kind;
        $s = Cache::get($key);
        if (! is_array($s)) {
            return ['status' => 'idle'];
        }
        if (($s['status'] ?? '') === 'running' && (time() - (int) ($s['heartbeat'] ?? 0)) >= self::PUSH_STALE_SECONDS) {
            $s['status'] = 'error';
            $s['message'] = 'The background pull stopped reporting progress (see storage/logs/fbm-targeting-pull.log).';
            Cache::put($key, $s, now()->addHours(2));
        }
        unset($s['heartbeat']);

        return $s;
    }

    public function recordPullState(string $kind, array $patch): void
    {
        $key = self::PULL_STATE.'_'.$kind;
        $s = Cache::get($key);
        Cache::put($key, array_merge(is_array($s) ? $s : [], $patch, ['heartbeat' => time()]), now()->addHours(2));
    }

    /**
     * The actual pull (runs in the background command). Returns the result summary.
     *
     * @return array<string, mixed>
     */
    public function runPull(string $kind): array
    {
        $label = $kind === 'neg' ? 'negative keyword' : 'keyword';

        $names = [];
        foreach ($this->withoutArchived($this->campaignsGroupedByParent()) as $c) {
            if ($c['campaign_id'] !== '' && ! $this->isProductTargetingCampaign($c['campaign_name'])) {
                $names[$c['campaign_id']] = $c['campaign_name'];
            }
        }
        if ($names === []) {
            return ['message' => 'No KW campaigns found.', 'campaigns' => 0, 'fetched' => 0, 'added' => 0, 'skipped' => 0];
        }

        $this->recordPullState($kind, ['message' => 'Reading '.$label.'s of '.count($names).' KW campaigns from Amazon…']);
        $r = $this->ingestKeywords($names, $kind);

        $this->recordPullState($kind, ['message' => 'Syncing KW cousins…']);
        $cousinAdded = 0;
        if ($this->hasTable(self::COUSIN_TABLE)) {
            $done = [];
            foreach ($this->cousins->groupsMap() as $group) {
                $key = implode('|', $group);
                if (isset($done[$key])) {
                    continue;
                }
                $done[$key] = true;
                $cousinAdded += $this->syncCousinKeywords($group, false, $kind);
            }
        }

        $message = $r['added'].' new '.$label.($r['added'] === 1 ? '' : 's').' added. '.$r['skipped'].' already in the app.';
        if ($cousinAdded > 0) {
            $message .= ' '.$cousinAdded.' copied between KW cousins.';
        }

        return [
            'message' => $message,
            'campaigns' => count($names),
            'fetched' => $r['fetched'],
            'added' => $r['added'],
            'skipped' => $r['skipped'],
            'cousin_added' => $cousinAdded,
        ];
    }

    /**
     * Fetch live keywords for the given campaigns from Amazon and insert the ones missing in the app.
     * Existing rows are never touched.
     *
     * @param  array<string, string>  $names  campaign_id => campaign name
     * @return array{fetched: int, added: int, skipped: int}
     */
    protected function ingestKeywords(array $names, string $kind = 'kw'): array
    {
        $table = $this->storeTable($kind);
        if ($kind === 'neg') {
            $ids = array_keys($names);
            $remote = array_merge(
                array_map(static fn (array $k) => $k + ['_level' => 'CAMPAIGN'], $this->ads->listSpCampaignNegativeKeywordsByCampaignIds($ids)),
                array_map(static fn (array $k) => $k + ['_level' => 'AD_GROUP'], $this->ads->listSpAdGroupNegativeKeywordsByCampaignIds($ids))
            );
        } else {
            $remote = $this->ads->listKeywordsByCampaignIdsBulk(array_keys($names));
        }

        // Active targets per campaign (enabled + paused keywords; archived ones are no longer counted), kept for the Targets
        // column of both pages. Only the keyword pull stores it.
        if ($kind === 'kw') {
            try {
                $totals = array_fill_keys(array_map('strval', array_keys($names)), 0);
                $seenIds = [];
                AmazonAdsTargetCounts::tally($remote, $totals, $seenIds);
                AmazonAdsTargetCounts::upsert(AmazonAdsTargetCounts::SP_KEYWORDS, $totals);
            } catch (\Throwable $e) {
                Log::warning('AmazonFbmTargetingController: could not store the active keyword totals', ['error' => $e->getMessage()]);
            }
        }

        // Keys already in the app, so they are retained as-is.
        $existing = [];
        foreach (array_chunk(array_keys($names), 1000) as $chunk) {
            foreach (DB::table($table)->whereIn('campaign_id', $chunk)->get(['campaign_id', 'keyword_key', 'match_type']) as $row) {
                $existing[$row->campaign_id.'|'.$row->keyword_key.'|'.$row->match_type] = true;
            }
        }

        // Targets deleted in the targets modal are not brought back.
        foreach ($this->deletedKeys($kind, array_keys($names)) as $k => $_) {
            $existing[$k] = true;
        }

        $now = now();
        $toInsert = [];
        $skipped = 0;
        $live = [];
        foreach ($remote as $kw) {
            $cid = trim((string) ($kw['campaignId'] ?? ''));
            $text = trim((string) ($kw['keywordText'] ?? $kw['keyword'] ?? ''));
            if ($cid === '' || $text === '' || ! isset($names[$cid])) {
                continue;
            }
            $match = strtoupper(trim((string) ($kw['matchType'] ?? '')));
            $key = AmazonKwTargetingKeyword::normalizeKey($text);
            $dedupe = $cid.'|'.$key.'|'.$match;
            if (isset($existing[$dedupe])) {
                $skipped++;
                $live[$cid][$match][$key] = true; // already stored: if it came from a cousin copy it is on Amazon now

                continue;
            }
            $existing[$dedupe] = true;
            $row = [
                'campaign_id' => $cid,
                'campaign_name' => $names[$cid],
                'ad_group_id' => ($kw['adGroupId'] ?? null) !== null ? (string) $kw['adGroupId'] : null,
                'keyword_id' => ($kw['keywordId'] ?? null) !== null ? (string) $kw['keywordId'] : null,
                'keyword' => mb_substr($text, 0, 512),
                'keyword_key' => $key,
                'match_type' => $match,
                'state' => isset($kw['state']) ? strtoupper((string) $kw['state']) : null,
                'source' => 'amazon',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($kind === 'neg') {
                $row['level'] = (string) ($kw['_level'] ?? 'CAMPAIGN');
            }
            $toInsert[] = $row;
        }

        $added = 0;
        foreach (array_chunk($toInsert, 500) as $batch) {
            $added += DB::table($table)->insertOrIgnore($batch);
        }
        // Keywords copied from a cousin that are now really on Amazon (pushed since) count as Amazon keywords.
        $promoted = 0;
        if ($kind === 'kw') {
            foreach ($live as $cid => $byMatch) {
                foreach ($byMatch as $match => $keys) {
                    foreach (array_chunk(array_keys($keys), 500) as $keyChunk) {
                        $promoted += DB::table($table)->where('campaign_id', $cid)->where('match_type', $match)
                            ->whereIn('keyword_key', $keyChunk)->where('source', 'cousin')->update(['source' => 'amazon']);
                    }
                }
            }
        }
        if ($added > 0 || $promoted > 0) {
            $this->forgetBadges();
        }

        return ['fetched' => count($remote), 'added' => $added, 'skipped' => $skipped];
    }

    /**
     * Copy keywords between the campaigns of one cousin group so every member ends up with the
     * union (keyword text + match type, no duplicates). Works on the app's keyword store.
     * Optionally pulls the members' live Amazon keywords first so nothing is missed.
     *
     * @param  list<string>  $memberNames
     * @return int number of keyword rows added across the group
     */
    protected function syncCousinKeywords(array $memberNames, bool $ingestLive = true, string $kind = 'kw'): int
    {
        $table = $this->storeTable($kind);
        if (! $this->hasTable($table)) {
            return 0;
        }

        $index = $this->campaignIndex();
        $members = [];
        foreach ($memberNames as $name) {
            $hit = $index[$this->cousins->normalize((string) $name)] ?? null;
            if ($hit && $hit['campaign_id'] !== '') {
                $members[$hit['campaign_id']] = $hit['campaign_name'];
            }
        }
        if (count($members) < 2) {
            return 0;
        }

        if ($ingestLive) {
            try {
                $this->ingestKeywords($members, $kind);
            } catch (\Throwable $e) {
                Log::warning('AmazonFbmTargetingController: cousin live pull failed', ['error' => $e->getMessage()]);
            }
        }

        $cols = ['campaign_id', 'keyword', 'keyword_key', 'match_type', 'state'];
        if ($kind === 'neg') {
            $cols[] = 'level';
        }
        $rows = DB::table($table)
            ->whereIn('campaign_id', array_keys($members))
            ->get($cols);

        $union = [];
        $have = [];
        foreach ($rows as $row) {
            $uk = $row->keyword_key.'|'.$row->match_type;
            $union[$uk] ??= $row;
            $have[$row->campaign_id][$uk] = true;
        }

        $deleted = $this->deletedKeys($kind, array_keys($members));
        $now = now();
        $toInsert = [];
        foreach ($members as $cid => $cname) {
            foreach ($union as $uk => $row) {
                if (isset($have[$cid][$uk]) || isset($deleted[$cid.'|'.$uk])) {
                    continue;
                }
                $new = [
                    'campaign_id' => (string) $cid,
                    'campaign_name' => $cname,
                    'ad_group_id' => null,
                    'keyword_id' => null,
                    'keyword' => $row->keyword,
                    'keyword_key' => $row->keyword_key,
                    'match_type' => $row->match_type,
                    'state' => $row->state,
                    'source' => 'cousin',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if ($kind === 'neg') {
                    // Copied negatives are app-level; they sit on the campaign (no ad group on the target).
                    $new['level'] = 'CAMPAIGN';
                }
                $toInsert[] = $new;
            }
        }

        $added = 0;
        foreach (array_chunk($toInsert, 500) as $batch) {
            $added += DB::table($table)->insertOrIgnore($batch);
        }
        if ($added > 0) {
            $this->forgetBadges();
        }

        return $added;
    }

    /**
     * Automatic KW-cousin sync for the campaigns on the page being loaded: one live pull per kind for all
     * members of the touched groups (so nothing on Amazon is missed), then a local union per group
     * (keyword + match type, no duplicates). Each group is re-synced at most every 5 minutes.
     * Only groups that have cousins on other parents are synced.
     *
     * @param  list<array{parent: string, campaign_name: string}>  $rows
     */
    protected function autoSyncCousins(array $rows): void
    {
        if (! $this->hasTable(self::COUSIN_TABLE) || ! $this->hasTable(self::STORE_TABLE)) {
            return;
        }

        try {
            $groupsMap = $this->cousins->groupsMap();
            $index = $this->campaignIndex();

            $todo = [];
            foreach ($rows as $row) {
                $group = $this->effectiveGroup($row['campaign_name'], $groupsMap);
                if ($this->cousinCampaignNames($group, $row['campaign_name']) === []) {
                    continue;
                }
                $norms = array_map(fn ($n) => $this->cousins->normalize((string) $n), $group);
                sort($norms);
                $key = 'amz_kw_cousin_autosync:'.md5(implode('|', $norms));
                if (isset($todo[$key]) || Cache::has($key)) {
                    continue;
                }
                $todo[$key] = $group;
            }
            if ($todo === []) {
                return;
            }
            $todo = array_slice($todo, 0, 12, true); // keep one page load light; the rest sync on later loads

            $members = [];
            foreach ($todo as $group) {
                foreach ($group as $name) {
                    $hit = $index[$this->cousins->normalize((string) $name)] ?? null;
                    if ($hit && $hit['campaign_id'] !== '') {
                        $members[$hit['campaign_id']] = $hit['campaign_name'];
                    }
                }
            }

            // Keywords already shown in the grid come from the synced keyword reports, so bring those into the store too.
            $this->ingestFromReports($members);

            // Local data only here (no live Amazon calls while the grid loads); live keywords come in with the
            // Pull buttons and "Sync keywords now".
            foreach (['kw', 'neg'] as $kind) {
                if ($kind === 'neg' && ! $this->hasTable(self::NEG_TABLE)) {
                    continue;
                }
                foreach ($todo as $group) {
                    $this->syncCousinKeywords($group, false, $kind);
                }
            }

            foreach (array_keys($todo) as $key) {
                Cache::put($key, 1, now()->addMinutes(5));
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonFbmTargetingController: auto cousin sync failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Copy the keywords of the synced SP keyword reports (what the Targets column already shows) into the keyword
     * store for the given campaigns, retaining what is there (dedupe by campaign + keyword + match type).
     *
     * @param  array<string, string>  $members  campaign_id => campaign_name
     */
    protected function ingestFromReports(array $members): void
    {
        if ($members === [] || ! $this->hasTable(self::KW_TABLE)) {
            return;
        }
        $cols = $this->columnsOf(self::KW_TABLE);
        $labelCol = in_array('keyword', $cols, true) ? 'keyword' : (in_array('targeting', $cols, true) ? 'targeting' : null);
        if ($labelCol === null || ! in_array('campaign_id', $cols, true)) {
            return;
        }
        $hasMatch = in_array('matchType', $cols, true);
        $select = ['campaign_id', $labelCol.' as label'];
        if ($hasMatch) {
            $select[] = 'matchType';
        }

        $now = now();
        $seen = $this->deletedKeys('kw', array_keys($members));
        $toInsert = [];
        foreach (array_chunk(array_keys($members), 1000) as $chunk) {
            $q = DB::table(self::KW_TABLE)->whereIn('campaign_id', $chunk)->whereNotNull($labelCol)->where($labelCol, '!=', '');
            foreach ($q->get($select) as $row) {
                $cid = trim((string) $row->campaign_id);
                $text = trim((string) $row->label);
                if ($cid === '' || $text === '' || ! isset($members[$cid])) {
                    continue;
                }
                $match = $hasMatch ? strtoupper(trim((string) ($row->matchType ?? ''))) : '';
                $key = AmazonKwTargetingKeyword::normalizeKey($text);
                $dedupe = $cid.'|'.$key.'|'.$match;
                if (isset($seen[$dedupe])) {
                    continue;
                }
                $seen[$dedupe] = true;
                $toInsert[] = [
                    'campaign_id' => $cid,
                    'campaign_name' => $members[$cid],
                    'ad_group_id' => null,
                    'keyword_id' => null,
                    'keyword' => mb_substr($text, 0, 512),
                    'keyword_key' => $key,
                    'match_type' => $match,
                    'state' => null,
                    'source' => 'report',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        $inserted = 0;
        foreach (array_chunk($toInsert, 500) as $batch) {
            $inserted += DB::table(self::STORE_TABLE)->insertOrIgnore($batch);
        }
        if ($inserted > 0) {
            $this->forgetBadges();
        }
    }

    /**
     * Normalized campaign name => campaign row, for resolving cousin names to campaign ids.
     *
     * @return array<string, array{parent: string, campaign_id: string, campaign_name: string}>
     */
    protected function campaignIndex(): array
    {
        if ($this->campaignIndexMemo !== null) {
            return $this->campaignIndexMemo;
        }
        $index = [];
        foreach ($this->campaignsGroupedByParent() as $c) {
            $index[$this->cousins->normalize($c['campaign_name'])] = $c;
        }

        return $this->campaignIndexMemo = $index;
    }

    /**
     * Parents for the cousin picker. Picking a parent brings in all of its campaigns automatically.
     * The campaign's own parent and parents that are already cousins are left out.
     */
    public function cousinCampaigns(Request $request): JsonResponse
    {
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        $campaign = (string) $request->query('exclude', '');
        $index = $this->campaignIndex();
        $ownParent = strtoupper($index[$this->cousins->normalize($campaign)]['parent'] ?? '');

        $inGroup = [];
        foreach ($this->effectiveGroup($campaign) as $name) {
            $inGroup[$this->cousins->normalize($name)] = true;
        }

        // parent (upper) => [display, campaigns total, campaigns already cousins]
        $parents = [];
        foreach ($this->campaignsGroupedByParent() as $c) {
            if ($c['parent'] === '') {
                continue;
            }
            $key = strtoupper($c['parent']);
            $parents[$key] ??= ['parent' => $c['parent'], 'campaigns' => 0, 'linked' => 0];
            $parents[$key]['campaigns']++;
            if (isset($inGroup[$this->cousins->normalize($c['campaign_name'])])) {
                $parents[$key]['linked']++;
            }
        }

        $out = [];
        foreach ($parents as $key => $p) {
            if ($key === $ownParent || $p['linked'] >= $p['campaigns']) {
                continue;
            }
            if ($q !== '' && ! str_contains(mb_strtolower($p['parent']), $q)) {
                continue;
            }
            $out[] = ['parent' => $p['parent'], 'campaigns' => $p['campaigns']];
        }

        return response()->json(['success' => true, 'parents' => $out]);
    }

    /**
     * Add parents as KW cousins of a campaign: every campaign under each chosen parent becomes a cousin,
     * then keywords are synced across the whole cousin group.
     */
    public function addCousins(Request $request): JsonResponse
    {
        $v = $request->validate([
            'campaign' => ['required', 'string', 'max:512'],
            'parents' => ['required', 'array', 'min:1'],
            'parents.*' => ['string', 'max:512'],
            'name' => ['nullable', 'string', 'max:191'],
        ]);

        if (! $this->hasTable(self::COUSIN_TABLE)) {
            return response()->json(['success' => false, 'message' => 'Cousin table is missing. Run php artisan migrate.'], 500);
        }

        $index = $this->campaignIndex();
        $campaign = trim($v['campaign']);
        $self = $index[$this->cousins->normalize($campaign)] ?? null;
        if (! $self) {
            return response()->json(['success' => false, 'message' => 'Unknown campaign.'], 422);
        }

        $wanted = [];
        foreach ($v['parents'] as $p) {
            $wanted[strtoupper(trim($p))] = true;
        }

        // The campaign's siblings (same parent) take part too, so the whole parent gets the same cousins.
        $names = [$self['campaign_name']];
        foreach ($this->siblingsByParent()[strtoupper($self['parent'])] ?? [] as $sib) {
            if ($self['parent'] !== '') {
                $names[] = $sib;
            }
        }
        $picked = 0;
        foreach ($this->campaignsGroupedByParent() as $c) {
            if ($c['parent'] !== '' && isset($wanted[strtoupper($c['parent'])])
                && strtoupper($c['parent']) !== strtoupper($self['parent'])) {
                $names[] = $c['campaign_name'];
                $picked++;
            }
        }
        if ($picked === 0) {
            return response()->json(['success' => false, 'message' => 'Pick at least one other parent.'], 422);
        }
        $names = array_values(array_unique($names));

        // Join with the existing group so everyone stays one connected set.
        $group = $this->uniqueByNorm(array_merge($this->effectiveGroup($campaign), $names));
        $this->cousins->syncFullyConnectedGroup($group, Auth::user()?->name ?? 'N/A');

        // Name: the typed one wins, else the campaign's own / an existing member's name; shared by the whole group.
        $typed = trim((string) ($v['name'] ?? ''));
        $nameMap = $this->cousinNameMap();
        $groupName = $typed !== '' ? $typed : $this->groupNameFor($group, $self['campaign_name'], $nameMap);
        $this->setGroupName($group, $groupName);

        @set_time_limit(300);
        $added = $this->syncCousinKeywords($group);
        $addedNeg = $this->syncCousinKeywords($group, true, 'neg');

        $this->forgetBadges(); // missing-cousin / keyword-count badges changed
        return response()->json([
            'success' => true,
            'message' => 'Cousins saved ('.$picked.' campaigns from '.count($wanted).' parent'.(count($wanted) === 1 ? '' : 's').'). Synced across '.count($group).' campaigns: '
                .$added.' keyword'.($added === 1 ? '' : 's').', '
                .$addedNeg.' negative keyword'.($addedNeg === 1 ? '' : 's').'.',
            'added' => $added,
            'added_negatives' => $addedNeg,
            'affected' => $this->cousinRows($group),
        ]);
    }

    /**
     * Remove a cousin parent (all of its campaigns) from the cousin group of a campaign.
     * Keywords already synced stay.
     */
    public function removeCousin(Request $request): JsonResponse
    {
        $v = $request->validate([
            'campaign' => ['required', 'string', 'max:512'],
            'parent' => ['nullable', 'string', 'max:512'],
            'cousin' => ['nullable', 'string', 'max:512'],
        ]);

        $campaign = $v['campaign'];
        $before = $this->effectiveGroup($campaign);

        $toRemove = [];
        $parent = strtoupper(trim((string) ($v['parent'] ?? '')));
        if ($parent !== '') {
            $index = $this->campaignIndex();
            foreach ($before as $name) {
                $hit = $index[$this->cousins->normalize($name)] ?? null;
                if ($hit && strtoupper($hit['parent']) === $parent
                    && $this->cousins->normalize($name) !== $this->cousins->normalize($campaign)) {
                    $toRemove[] = $name;
                }
            }
        } elseif (! empty($v['cousin'])) {
            $toRemove[] = $v['cousin'];
        }

        foreach ($toRemove as $name) {
            $this->cousins->unlinkFromGroup($name, $this->effectiveGroup($campaign), Auth::user()?->name ?? 'N/A');
        }
        // Campaigns that left the group no longer carry its name.
        $this->clearGroupName($toRemove);
        $this->forgetBadges();

        return response()->json([
            'success' => true,
            'message' => 'Cousin removed.',
            'affected' => $this->cousinRows($before),
        ]);
    }

    /**
     * Re-run the keyword sync for the cousin group of a campaign.
     */
    public function syncCousins(Request $request): JsonResponse
    {
        $v = $request->validate(['campaign' => ['required', 'string', 'max:512']]);

        $group = $this->effectiveGroup($v['campaign']);
        if ($this->cousinCampaignNames($group, $v['campaign']) === []) {
            return response()->json(['success' => false, 'message' => 'This campaign has no cousins yet.'], 422);
        }

        // Apply: make the parent-level cousins permanent for every campaign under the same parent.
        $this->cousins->syncFullyConnectedGroup($group, Auth::user()?->name ?? 'N/A');

        @set_time_limit(300);
        $added = $this->syncCousinKeywords($group);
        $addedNeg = $this->syncCousinKeywords($group, true, 'neg');

        return response()->json([
            'success' => true,
            'message' => 'Synced across '.count($group).' campaigns: '
                .$added.' keyword'.($added === 1 ? '' : 's').', '
                .$addedNeg.' negative keyword'.($addedNeg === 1 ? '' : 's').'.',
            'added' => $added,
            'added_negatives' => $addedNeg,
            'affected' => $this->cousinRows($group),
        ]);
    }

    /**
     * Campaign names per parent (PT / archived already excluded), keyed by upper-cased parent.
     *
     * @return array<string, list<string>>
     */
    protected function siblingsByParent(): array
    {
        if ($this->siblingsMemo !== null) {
            return $this->siblingsMemo;
        }
        $out = [];
        foreach ($this->campaignsGroupedByParent() as $c) {
            if ($c['parent'] !== '') {
                $out[strtoupper($c['parent'])][] = $c['campaign_name'];
            }
        }

        return $this->siblingsMemo = $out;
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    protected function uniqueByNorm(array $names): array
    {
        $seen = [];
        foreach ($names as $n) {
            $seen[$this->cousins->normalize((string) $n)] ??= trim((string) $n);
        }
        unset($seen['']);

        return array_values($seen);
    }

    /**
     * Cousin group of a campaign. Campaigns under the same parent share one group: it is the union of the
     * stored links of all campaigns of that parent (plus the siblings themselves).
     *
     * @param  array<string, list<string>>|null  $groupsMap  pre-loaded groupsMap()
     * @return list<string>
     */
    protected function effectiveGroup(string $campaign, ?array $groupsMap = null): array
    {
        $groupsMap ??= $this->hasTable(self::COUSIN_TABLE) ? $this->cousins->groupsMap() : [];
        $norm = $this->cousins->normalize($campaign);
        $members = $groupsMap[$norm] ?? [$campaign];

        $self = $this->campaignIndex()[$norm] ?? null;
        if ($self && $self['parent'] !== '') {
            foreach ($this->siblingsByParent()[strtoupper($self['parent'])] ?? [] as $sib) {
                foreach ($groupsMap[$this->cousins->normalize($sib)] ?? [$sib] as $m) {
                    $members[] = $m;
                }
            }
        }

        return $this->uniqueByNorm($members);
    }

    /**
     * The cousins of a campaign: its group minus itself and minus its own siblings (same parent).
     *
     * @param  list<string>  $group
     * @return list<string>
     */
    protected function cousinCampaignNames(array $group, string $campaign): array
    {
        $index = $this->campaignIndex();
        $selfNorm = $this->cousins->normalize($campaign);
        $ownParent = strtoupper($index[$selfNorm]['parent'] ?? '');

        return array_values(array_filter($group, function ($m) use ($index, $selfNorm, $ownParent) {
            $norm = $this->cousins->normalize((string) $m);
            if ($norm === $selfNorm || ! isset($index[$norm])) {
                return false; // itself, or hidden on this page (PT / archived)
            }

            return $ownParent === '' || strtoupper($index[$norm]['parent']) !== $ownParent;
        }));
    }

    private function parentKey(string $parent): string
    {
        return strtoupper(trim((string) preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $parent))));
    }

    /**
     * Product Master title for each parent (autofill). The parent's own "PARENT …" row wins when it has a
     * title; otherwise the first child SKU of that parent that has one. Prefers the 150-char title.
     *
     * @param  list<string>  $parents
     * @return array<string, string> normalized parent => title
     */
    protected function parentTitles(array $parents): array
    {
        $keys = [];
        foreach ($parents as $p) {
            $k = $this->parentKey($p);
            if ($k !== '') {
                $keys[$k] = true;
            }
        }
        if ($keys === [] || ! $this->hasTable('product_master')) {
            return [];
        }

        $cols = array_values(array_filter(
            ['title150', 'title100', 'title80', 'title60'],
            fn (string $c) => $this->hasColumn('product_master', $c)
        ));
        if ($cols === []) {
            return [];
        }

        $out = [];
        $rank = [];
        foreach (array_chunk(array_keys($keys), 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            $rows = DB::table('product_master')
                ->whereNull('deleted_at')
                // A plain bound string (U+00A0) takes the column's collation; CHAR(160) / UNHEX() would not convert or would clash.
                ->whereRaw('UPPER(TRIM(REPLACE(parent, ?, " "))) IN ('.$ph.')', array_merge(["\u{00A0}"], $chunk))
                ->orderBy('sku')
                ->get(array_merge(['parent', 'sku'], $cols));

            foreach ($rows as $r) {
                $title = '';
                foreach ($cols as $c) {
                    $t = trim((string) ($r->{$c} ?? ''));
                    if ($t !== '') {
                        $title = $t;
                        break;
                    }
                }
                if ($title === '') {
                    continue;
                }
                $key = $this->parentKey((string) $r->parent);
                $isParentRow = str_starts_with(strtoupper(trim((string) $r->sku)), 'PARENT');
                // 2 = the PARENT row itself, 1 = first child with a title.
                $score = $isParentRow ? 2 : 1;
                if (! isset($out[$key]) || $score > $rank[$key]) {
                    $out[$key] = $title;
                    $rank[$key] = $score;
                }
            }
        }

        return $out;
    }

    /**
     * Saved cousin names: normalized campaign name => name.
     *
     * @return array<string, string>
     */
    protected function cousinNameMap(): array
    {
        if (! $this->hasTable(self::COUSIN_NAME_TABLE)) {
            return [];
        }

        return DB::table(self::COUSIN_NAME_TABLE)->pluck('name', 'campaign_norm')->all();
    }

    /**
     * Name of the cousin group a campaign belongs to: its own saved name, else the first member's.
     *
     * @param  list<string>  $group
     * @param  array<string, string>  $nameMap
     */
    protected function groupNameFor(array $group, string $campaign, array $nameMap): string
    {
        $own = $nameMap[$this->cousins->normalize($campaign)] ?? '';
        if ($own !== '') {
            return $own;
        }
        foreach ($group as $member) {
            $n = $nameMap[$this->cousins->normalize($member)] ?? '';
            if ($n !== '') {
                return $n;
            }
        }

        return '';
    }

    /**
     * Save one name on every campaign of a cousin group (empty name removes it).
     *
     * @param  list<string>  $group
     */
    protected function setGroupName(array $group, string $name): void
    {
        if (! $this->hasTable(self::COUSIN_NAME_TABLE)) {
            return;
        }
        $name = mb_substr(trim($name), 0, 191);
        if ($name === '') {
            $this->clearGroupName($group);

            return;
        }

        $now = now();
        $user = Auth::user()?->name;
        foreach ($group as $member) {
            $norm = $this->cousins->normalize($member);
            if ($norm === '') {
                continue;
            }
            DB::table(self::COUSIN_NAME_TABLE)->updateOrInsert(
                ['campaign_norm' => $norm],
                ['campaign' => mb_substr(trim($member), 0, 512), 'name' => $name, 'updated_by' => $user, 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }

    /**
     * @param  list<string>  $campaigns
     */
    protected function clearGroupName(array $campaigns): void
    {
        if (! $this->hasTable(self::COUSIN_NAME_TABLE) || $campaigns === []) {
            return;
        }
        $norms = array_values(array_filter(array_map(fn ($c) => $this->cousins->normalize((string) $c), $campaigns)));
        if ($norms !== []) {
            DB::table(self::COUSIN_NAME_TABLE)->whereIn('campaign_norm', $norms)->delete();
        }
    }

    /**
     * Name (or rename) the cousin group of a campaign. The name is stored on every campaign in the group.
     */
    public function nameCousins(Request $request): JsonResponse
    {
        $v = $request->validate([
            'campaign' => ['required', 'string', 'max:512'],
            'name' => ['nullable', 'string', 'max:191'],
        ]);

        if (! $this->hasTable(self::COUSIN_NAME_TABLE)) {
            return response()->json(['success' => false, 'message' => 'Table is missing. Run php artisan migrate.'], 500);
        }

        $campaign = trim($v['campaign']);
        if (! isset($this->campaignIndex()[$this->cousins->normalize($campaign)])) {
            return response()->json(['success' => false, 'message' => 'Unknown campaign.'], 422);
        }

        $group = $this->effectiveGroup($campaign);
        $name = trim((string) ($v['name'] ?? ''));
        $this->setGroupName($group, $name);

        return response()->json([
            'success' => true,
            'message' => $name === '' ? 'Name removed.' : 'Name saved for '.count($group).' campaign'.(count($group) === 1 ? '' : 's').'.',
            'affected' => $this->cousinRows($group),
        ]);
    }

    /**
     * Cousin campaigns of one campaign, grouped by parent for display.
     *
     * @param  list<string>  $cousinNames
     * @return list<array{parent: string, campaigns: list<string>}>
     */
    protected function cousinParents(array $cousinNames): array
    {
        $index = $this->campaignIndex();
        $byParent = [];
        $loose = [];
        foreach ($cousinNames as $name) {
            $hit = $index[$this->cousins->normalize($name)] ?? null;
            if ($hit === null) {
                continue; // PT / archived campaigns are hidden on this page
            }
            $parent = $hit['parent'];
            if ($parent === '') {
                $loose[] = ['parent' => '', 'campaigns' => [$name]];

                continue;
            }
            $key = strtoupper($parent);
            $byParent[$key] ??= ['parent' => $parent, 'campaigns' => []];
            $byParent[$key]['campaigns'][] = $name;
        }
        ksort($byParent, SORT_NATURAL);

        return array_merge(array_values($byParent), $loose);
    }

    /**
     * @param  list<string>  $group
     * @return list<array{campaign: string, cousins: list<string>, cousin_parents: list<array>, cousin_count: int}>
     */
    protected function cousinRows(array $group): array
    {
        $nameMap = $this->cousinNameMap();
        $map = $this->hasTable(self::COUSIN_TABLE) ? $this->cousins->groupsMap() : [];
        $rows = [];
        foreach ($group as $member) {
            $current = $this->effectiveGroup($member, $map);
            $others = $this->cousinCampaignNames($current, $member);
            $parents = $this->cousinParents($others);
            $rows[] = [
                'campaign' => $member,
                'cousin_name' => $this->groupNameFor($current, $member, $nameMap),
                'cousins' => $others,
                'cousin_parents' => $parents,
                'cousin_count' => count($parents),
            ];
        }

        return $rows;
    }

    /**
     * KW campaigns are every campaign that is not product targeting (name contains " PT" / ends with PT).
     */
    protected function isProductTargetingCampaign(string $campaignName): bool
    {
        $n = preg_replace('/\s+/u', ' ', trim(str_replace("\xC2\xA0", ' ', $campaignName)));
        if ($n === '') {
            return false;
        }
        $u = strtoupper($n);

        return str_contains($u, ' PT') || preg_match('/PT\.?\s*$/u', $u) === 1;
    }

    /**
     * Add keywords stored in the app to the per-campaign target lists (no duplicates).
     *
     * @param  array<string, list<string>>  $map
     * @param  list<string>  $campaignIds
     * @return array<string, list<string>>
     */
    protected function mergeStoredKeywords(array $map, array $campaignIds, string $table = self::STORE_TABLE): array
    {
        $campaignIds = array_values(array_unique(array_filter($campaignIds)));
        if ($campaignIds === [] || ! $this->hasTable($table)) {
            return $map;
        }

        $seen = [];
        foreach ($map as $cid => $labels) {
            foreach ($labels as $label) {
                $seen[$cid][strtolower($label)] = true;
            }
        }

        $touched = [];
        foreach (DB::table($table)->whereIn('campaign_id', $campaignIds)->get(['campaign_id', 'keyword', 'match_type']) as $row) {
            $cid = (string) $row->campaign_id;
            $label = $row->match_type !== '' ? $row->keyword.' ('.$row->match_type.')' : $row->keyword;
            if (isset($seen[$cid][strtolower($label)])) {
                continue;
            }
            $seen[$cid][strtolower($label)] = true;
            $map[$cid][] = $label;
            $touched[$cid] = true;
        }

        foreach (array_keys($touched) as $cid) {
            sort($map[$cid], SORT_NATURAL | SORT_FLAG_CASE);
            $map[$cid] = array_values($map[$cid]);
        }

        return $map;
    }

    /**
     * @param  list<string>  $campaignIds
     * @return array<string, list<string>>
     */
    protected function targetsForCampaignIds(array $campaignIds): array
    {
        $campaignIds = array_values(array_unique(array_filter($campaignIds)));
        if ($campaignIds === []) {
            return [];
        }

        // Local data only (synced keyword reports; the keyword store is merged in by the caller). Live Amazon calls
        // made every search / page a multi-second wait - live keywords now come in through the Pull buttons.
        $map = $this->targetsFromKeywordReports($campaignIds);

        foreach ($map as $cid => $labels) {
            sort($labels, SORT_NATURAL | SORT_FLAG_CASE);
            $map[$cid] = array_values($labels);
        }

        return $map;
    }

    /**
     * Fallback from the same keyword reports used on Ads All (SP keywords table).
     *
     * @param  list<string>  $campaignIds
     * @return array<string, list<string>>
     */
    protected function targetsFromKeywordReports(array $campaignIds): array
    {
        if (! $this->hasTable(self::KW_TABLE)) {
            return [];
        }

        $cols = $this->columnsOf(self::KW_TABLE);
        $labelCol = in_array('keyword', $cols, true) ? 'keyword' : (in_array('targeting', $cols, true) ? 'targeting' : null);
        if ($labelCol === null || ! in_array('campaign_id', $cols, true)) {
            return [];
        }

        $q = DB::table(self::KW_TABLE)
            ->whereIn('campaign_id', $campaignIds)
            ->whereNotNull($labelCol)
            ->where($labelCol, '!=', '');

        $select = ['campaign_id', $labelCol.' as label'];
        if (in_array('matchType', $cols, true)) {
            $select[] = 'matchType';
        }

        $map = [];
        foreach ($q->get($select) as $row) {
            $cid = trim((string) ($row->campaign_id ?? ''));
            $label = trim((string) ($row->label ?? ''));
            $match = trim((string) ($row->matchType ?? ''));
            if ($cid === '' || $label === '') {
                continue;
            }
            if ($match !== '') {
                $label .= ' ('.$match.')';
            }
            $map[$cid] = $map[$cid] ?? [];
            if (! in_array($label, $map[$cid], true)) {
                $map[$cid][] = $label;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $kw
     */
    protected function formatKeywordTarget(array $kw): string
    {
        $text = trim((string) ($kw['keywordText'] ?? $kw['keyword'] ?? ''));
        if ($text === '') {
            return '';
        }
        $match = trim((string) ($kw['matchType'] ?? ''));

        return $match !== '' ? $text.' ('.$match.')' : $text;
    }

    /**
     * @param  array<string, mixed>  $target
     */
    protected function formatProductTarget(array $target): string
    {
        $expression = $target['expression'] ?? $target['resolvedExpression'] ?? [];
        if (is_string($expression) && trim($expression) !== '') {
            return trim($expression);
        }
        if (! is_array($expression)) {
            return trim((string) ($target['expressionType'] ?? ''));
        }

        $parts = [];
        foreach ($expression as $clause) {
            if (! is_array($clause)) {
                continue;
            }
            $type = trim((string) ($clause['type'] ?? ''));
            $value = trim((string) ($clause['value'] ?? ''));
            if ($type !== '' && $value !== '') {
                $parts[] = $type.': '.$value;
            } elseif ($type !== '') {
                $parts[] = $type;
            } elseif ($value !== '') {
                $parts[] = $value;
            }
        }

        if ($parts !== []) {
            return implode(' · ', $parts);
        }

        return trim((string) ($target['expressionType'] ?? ''));
    }
}
