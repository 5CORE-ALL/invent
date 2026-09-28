<?php

namespace App\Console\Commands;

use App\Services\AmazonAdsLiveBidBgtSyncService;
use App\Support\AmazonAdsDesiredSbgtResolver;
use App\Support\AmazonAdsSbgt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AmazonAdsLiveBidBgtSync extends Command
{
    protected $signature = 'amazon:ads-live-bid-bgt-sync
        {--channel=all : sp, sb, or all}
        {--campaign-id= : Only this campaign ID}
        {--failed-only : Retry rows that are not verified}
        {--limit=2000 : Max report campaigns to verify in this run}';

    protected $description = 'Pull live Amazon BGT/BID, compare Lbgt to SBGT and Lbid to SBID, push mismatches, verify before marking synced';

    public function handle(AmazonAdsLiveBidBgtSyncService $sync): int
    {
        if (! Schema::hasTable('amazon_ads_live_sync_states')) {
            $this->error('amazon_ads_live_sync_states is missing. Run migrations.');

            return self::FAILURE;
        }

        $channelOpt = strtolower((string) $this->option('channel'));
        $onlyCid = trim((string) $this->option('campaign-id'));
        $limit = max(1, min(2000, (int) $this->option('limit')));

        $rows = $this->rowsFromReports($channelOpt, $limit, $onlyCid);

        if ($rows === []) {
            $this->info('No Enabled calendar-day campaigns have Lbgt different from SBGT or Lbid different from SBID.');

            return self::SUCCESS;
        }

        $this->info('Enabled calendar-day mismatches: '.count($rows).'. Pull live budget and bid, then push only rows that still differ.');
        $out = $sync->syncRows($rows, 'cron-live-sync');
        $this->info('Synced '.$out['synced'].' | Failed '.$out['failed'].' | Skipped '.$out['skipped'].' | In progress '.$out['in_progress']);

        return $out['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Enabled campaigns on the latest calendar day whose Lbid differs from SBID
     * or whose Lbgt differs from the saved SBGT (including SBGT 0, which pauses).
     * Same Stat + calendar window as the grid. L30 history and paused rows are not included.
     *
     * @return list<array<string, mixed>>
     */
    private function rowsFromReports(string $channelOpt, int $limit, string $onlyCid = ''): array
    {
        $channels = $channelOpt === 'sp' || $channelOpt === 'sb' ? [$channelOpt] : ['sp', 'sb'];
        $merged = [];
        foreach ($channels as $channel) {
            foreach ($this->bidMismatchRows($channel, $limit, $onlyCid) as $row) {
                $this->mergeSyncRow($merged, $row);
            }
            foreach ($this->budgetMismatchRows($channel, $limit, $onlyCid) as $row) {
                $this->mergeSyncRow($merged, $row);
            }
        }

        return array_values($merged);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bidMismatchRows(string $channel, int $limit, string $onlyCid): array
    {
        $table = $channel === 'sb' ? 'amazon_sb_campaign_reports' : 'amazon_sp_campaign_reports';
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'last_sbid') || ! Schema::hasColumn($table, 'sbid') || ! Schema::hasColumn($table, 'campaignStatus')) {
            return [];
        }
        $day = $this->latestReportDay($table);
        if ($day === null) {
            return [];
        }
        $q = DB::table($table.' as r')
            ->where('r.report_date_range', $day)
            ->whereRaw("UPPER(TRIM(r.campaignStatus)) = 'ENABLED'")
            ->whereNotNull('r.sbid')
            ->where('r.sbid', '!=', '')
            ->where('r.sbid', '!=', '0')
            ->where(function ($w) {
                $w->where(function ($diff) {
                    $diff->whereNotNull('r.last_sbid')
                        ->where('r.last_sbid', '!=', '')
                        ->whereRaw('ROUND((r.sbid + 0), 2) <> ROUND((r.last_sbid + 0), 2)');
                })->orWhere(function ($missing) {
                    $missing->whereNull('r.last_sbid')
                        ->orWhere('r.last_sbid', '=', '');
                });
            });
        if ($onlyCid !== '') {
            $q->where('r.campaign_id', $onlyCid);
        }
        $this->deprioritizeRecentFailures($q, $channel, 'bid');
        $found = $q->orderBy('r.id')
            ->limit($limit)
            ->get(['r.campaign_id', 'r.campaignName', 'r.sbid']);
        $out = [];
        $seen = [];
        foreach ($found as $row) {
            $cid = (string) $row->campaign_id;
            if ($cid === '' || isset($seen[$cid])) {
                continue;
            }
            $seen[$cid] = true;
            $bid = AmazonAdsLiveBidBgtSyncService::positiveNumber($row->sbid ?? null);
            if ($bid === null) {
                continue;
            }
            $out[] = [
                'campaign_id' => $cid,
                'channel' => $channel,
                'campaign_name' => (string) ($row->campaignName ?? ''),
                'sbid' => $bid,
            ];
        }

        return $out;
    }

    /**
     * Grid SBGT (six-part sum) vs Lbgt. A missing Lbgt is a mismatch.
     * SBGT 0 is included so an enabled campaign is paused instead of left on the old budget.
     *
     * @return list<array<string, mixed>>
     */
    private function budgetMismatchRows(string $channel, int $limit, string $onlyCid): array
    {
        $table = $channel === 'sb' ? 'amazon_sb_campaign_reports' : 'amazon_sp_campaign_reports';
        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'sbgt')
            || ! Schema::hasColumn($table, 'campaignBudgetAmount')
            || ! Schema::hasColumn($table, 'campaignStatus')) {
            return [];
        }
        $day = $this->latestReportDay($table);
        if ($day === null) {
            return [];
        }
        $q = DB::table($table.' as r')
            ->where('r.report_date_range', $day)
            ->whereRaw("UPPER(TRIM(r.campaignStatus)) = 'ENABLED'");
        if ($onlyCid !== '') {
            $q->where('r.campaign_id', $onlyCid);
        }
        $found = $q->orderBy('r.id')
            ->limit(2000)
            ->get(['r.campaign_id', 'r.campaignName', 'r.sbgt', 'r.campaignBudgetAmount', 'r.campaignStatus']);
        $resolved = $this->resolvedSbgtByCampaign($channel, $found);
        $out = [];
        $seen = [];
        foreach ($found as $row) {
            $cid = (string) $row->campaign_id;
            if ($cid === '' || isset($seen[$cid])) {
                continue;
            }
            $sbgt = $this->desiredBudget($resolved[$cid] ?? null, $row->sbgt ?? null);
            if ($sbgt === null) {
                continue;
            }
            $seen[$cid] = true;
            $status = strtoupper(trim((string) ($row->campaignStatus ?? '')));
            $live = is_numeric($row->campaignBudgetAmount) ? (float) $row->campaignBudgetAmount : null;
            $zeroPause = $sbgt === 0.0 && $status === 'ENABLED';
            $differs = $live === null || abs($sbgt - $live) > AmazonAdsLiveBidBgtSyncService::BGT_TOLERANCE;
            if (! $zeroPause && ! $differs) {
                continue;
            }
            $out[] = [
                'campaign_id' => $cid,
                'channel' => $channel,
                'campaign_name' => (string) ($row->campaignName ?? ''),
                'sbgt' => $sbgt,
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Grid SBGT (six-part sum). Stored sbgt is only the fallback when that sum is missing.
     *
     * @param  iterable<int, object>  $rows
     * @return array<string, int|null>
     */
    private function resolvedSbgtByCampaign(string $channel, iterable $rows): array
    {
        try {
            return AmazonAdsDesiredSbgtResolver::sbgtForCampaigns($rows, $channel);
        } catch (Throwable) {
            return [];
        }
    }

    private function desiredBudget(mixed $resolved, mixed $stored): ?float
    {
        if ($resolved !== null && is_numeric($resolved)) {
            $n = (float) $resolved;

            return $n >= 0 ? $n : null;
        }
        if (AmazonAdsSbgt::isExplicitZero($stored)) {
            return 0.0;
        }
        $parsed = AmazonAdsSbgt::parsePushableBudget($stored);

        return $parsed === null ? null : (float) $parsed;
    }

    private function deprioritizeRecentFailures($query, string $channel, string $field): void
    {
        if (! Schema::hasTable('amazon_ads_live_sync_states')) {
            $query->orderBy('r.id');

            return;
        }
        $query->leftJoin('amazon_ads_live_sync_states as s', function ($join) use ($channel, $field) {
            $join->on('s.campaign_id', '=', 'r.campaign_id')
                ->where('s.channel', '=', $channel)
                ->where('s.field', '=', $field);
        })->orderByRaw("CASE WHEN s.status = 'failed' THEN 1 ELSE 0 END");
    }

    /**
     * @param  array<string, array<string, mixed>>  $merged
     * @param  array<string, mixed>  $row
     */
    private function mergeSyncRow(array &$merged, array $row): void
    {
        $key = $row['channel']."\0".$row['campaign_id'];
        if (! isset($merged[$key])) {
            $merged[$key] = $row;

            return;
        }
        if (isset($row['sbid'])) {
            $merged[$key]['sbid'] = $row['sbid'];
        }
        if (array_key_exists('sbgt', $row)) {
            $merged[$key]['sbgt'] = $row['sbgt'];
        }
        if (($merged[$key]['campaign_name'] ?? '') === '' && ($row['campaign_name'] ?? '') !== '') {
            $merged[$key]['campaign_name'] = $row['campaign_name'];
        }
    }

    private function latestReportDay(string $table): ?string
    {
        $max = DB::table($table)
            ->where('report_date_range', '>=', '2010-01-01')
            ->where('report_date_range', '<=', '2099-12-31')
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
            ->max('report_date_range');
        if ($max === null || $max === '') {
            return null;
        }

        return (string) $max;
    }
}
