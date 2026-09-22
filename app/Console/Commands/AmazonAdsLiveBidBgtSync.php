<?php

namespace App\Console\Commands;

use App\Services\AmazonAdsLiveBidBgtSyncService;
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

    protected $description = 'Pull live Amazon BGT/BID, compare to rule SBGT/SBID, push mismatches, verify before marking synced';

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
            $this->info('No Enabled calendar-day campaigns have a live bid that differs from SBID.');

            return self::SUCCESS;
        }

        $this->info('Enabled calendar-day mismatches: '.count($rows).'. Pull live bid, then push only rows that still differ.');
        $out = $sync->syncRows($rows, 'cron-live-sync');
        $this->info('Synced '.$out['synced'].' | Failed '.$out['failed'].' | Skipped '.$out['skipped'].' | In progress '.$out['in_progress']);

        return $out['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Enabled campaigns on the latest calendar day whose live bid differs from SBID.
     * Same Stat + calendar window as the grid. L30 history and paused rows are not included.
     *
     * @return list<array<string, mixed>>
     */
    private function rowsFromReports(string $channelOpt, int $limit, string $onlyCid = ''): array
    {
        $channels = $channelOpt === 'sp' || $channelOpt === 'sb' ? [$channelOpt] : ['sp', 'sb'];
        $out = [];
        foreach ($channels as $channel) {
            $table = $channel === 'sb' ? 'amazon_sb_campaign_reports' : 'amazon_sp_campaign_reports';
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'last_sbid') || ! Schema::hasColumn($table, 'sbid') || ! Schema::hasColumn($table, 'campaignStatus')) {
                continue;
            }
            $day = $this->latestReportDay($table);
            if ($day === null) {
                continue;
            }
            $mismatch = AmazonAdsLiveBidBgtSyncService::BID_TOLERANCE;
            $q = DB::table($table.' as r')
                ->where('r.report_date_range', $day)
                ->whereRaw("UPPER(TRIM(r.campaignStatus)) = 'ENABLED'")
                ->whereNotNull('r.sbid')
                ->where('r.sbid', '!=', '')
                ->where('r.sbid', '!=', '0')
                ->whereNotNull('r.last_sbid')
                ->where('r.last_sbid', '!=', '')
                ->whereRaw('ABS((r.sbid + 0) - (r.last_sbid + 0)) > ?', [$mismatch]);
            if ($onlyCid !== '') {
                $q->where('r.campaign_id', $onlyCid);
            }
            $found = $q->orderBy('r.id')
                ->limit($limit)
                ->get(['r.campaign_id', 'r.campaignName', 'r.last_sbid', 'r.sbid']);
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
        }

        return $out;
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
