<?php

namespace App\Console\Commands;

use App\Services\AmazonAdsLiveBidBgtSyncService;
use App\Support\AmazonAdsSbgt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AmazonAdsLiveBidBgtSync extends Command
{
    protected $signature = 'amazon:ads-live-bid-bgt-sync
        {--channel=all : sp, sb, or all}
        {--campaign-id= : Only this campaign ID}
        {--failed-only : Retry rows that are not verified}
        {--limit=200 : Max report campaigns to verify in this run}';

    protected $description = 'Pull live Amazon BGT/BID, compare Lbgt to SBGT and Lbid to SBID, push mismatches, verify before marking synced';

    public function handle(AmazonAdsLiveBidBgtSyncService $sync): int
    {
        if (! Schema::hasTable('amazon_ads_live_sync_states')) {
            $this->error('amazon_ads_live_sync_states is missing. Run migrations.');

            return self::FAILURE;
        }

        $channelOpt = strtolower((string) $this->option('channel'));
        $onlyCid = trim((string) $this->option('campaign-id'));
        $limit = max(1, min(500, (int) $this->option('limit')));

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
            ->whereNotNull('r.last_sbid')
            ->where('r.last_sbid', '!=', '')
            ->whereRaw('ABS((r.sbid + 0) - (r.last_sbid + 0)) > ?', [AmazonAdsLiveBidBgtSyncService::BID_TOLERANCE]);
        if ($onlyCid !== '') {
            $q->where('r.campaign_id', $onlyCid);
        }
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
     * Saved SBGT vs Lbgt (campaignBudgetAmount), same comparison as SBID vs Lbid.
     * SBGT 0 is included so the campaign is paused instead of left on the old budget.
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
            ->whereRaw("UPPER(TRIM(r.campaignStatus)) = 'ENABLED'")
            ->whereNotNull('r.sbgt')
            ->where('r.sbgt', '!=', '')
            ->whereNotNull('r.campaignBudgetAmount')
            ->whereRaw('ABS((r.sbgt + 0) - (r.campaignBudgetAmount + 0)) > ?', [0.015]);
        if ($onlyCid !== '') {
            $q->where('r.campaign_id', $onlyCid);
        }
        $found = $q->orderBy('r.id')
            ->limit($limit)
            ->get(['r.campaign_id', 'r.campaignName', 'r.sbgt']);
        $out = [];
        $seen = [];
        foreach ($found as $row) {
            $cid = (string) $row->campaign_id;
            if ($cid === '' || isset($seen[$cid])) {
                continue;
            }
            $seen[$cid] = true;
            $raw = $row->sbgt ?? null;
            if (AmazonAdsSbgt::isExplicitZero($raw)) {
                $sbgt = 0.0;
            } else {
                $parsed = AmazonAdsSbgt::parsePushableBudget($raw);
                if ($parsed === null) {
                    continue;
                }
                $sbgt = (float) $parsed;
            }
            $out[] = [
                'campaign_id' => $cid,
                'channel' => $channel,
                'campaign_name' => (string) ($row->campaignName ?? ''),
                'sbgt' => $sbgt,
            ];
        }

        return $out;
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
