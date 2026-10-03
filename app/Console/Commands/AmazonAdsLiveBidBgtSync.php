<?php

namespace App\Console\Commands;

use App\Services\AmazonAdsLiveBidBgtSyncService;
use App\Support\AmazonAdsDesiredSbgtResolver;
use App\Support\AmazonAdsLiveSyncFollowUp;
use App\Support\AmazonAdsSbgt;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AmazonAdsLiveBidBgtSync extends Command
{
    protected $signature = 'amazon:ads-live-bid-bgt-sync
        {--channel=all : sp, sb, or all}
        {--campaign-id= : Only this campaign ID}
        {--failed-only : Retry rows that are not verified}
        {--retry-failed : Also retry campaigns whose sync failed in the last 12 hours}
        {--limit=200 : Max report campaigns to verify in this run}';

    protected $description = 'Pull live Amazon BGT/BID, compare Lbgt to SBGT and Lbid to SBID, push mismatches, verify before marking synced';

    public function handle(AmazonAdsLiveBidBgtSyncService $sync): int
    {
        if (! Schema::hasTable('amazon_ads_live_sync_states')) {
            $this->error('amazon_ads_live_sync_states is missing. Run migrations.');

            return self::FAILURE;
        }

        if (! Cache::add(AmazonAdsLiveSyncFollowUp::RUN_KEY, 1, 1200)) {
            Cache::put(AmazonAdsLiveSyncFollowUp::DIRTY_KEY, 1, now()->addHours(6));
            $this->info('Another live bid/budget sync is already running.');

            return self::SUCCESS;
        }

        $channelOpt = strtolower((string) $this->option('channel'));
        $onlyCid = trim((string) $this->option('campaign-id'));
        $limit = max(1, min(500, (int) $this->option('limit')));
        $retryFailed = (bool) $this->option('retry-failed');
        $failed = 0;

        try {
            $this->refreshStoredSbgt($channelOpt, $onlyCid);
            $bidFilled = [];
            for ($pass = 0; $pass < 8; $pass++) {
                Cache::forget(AmazonAdsLiveSyncFollowUp::DIRTY_KEY);
                $rows = $this->rowsFromReports($channelOpt, $limit, $onlyCid, $retryFailed);
                [$pushRows, $pullIds] = $this->splitBidFills($rows);
                foreach ($this->blankLbidCampaignIds($channelOpt, $limit, $onlyCid, $bidFilled) as $channel => $ids) {
                    foreach ($ids as $id) {
                        $pullIds[$channel][$id] = $id;
                    }
                }
                foreach ($pullIds as $channel => $ids) {
                    if ($ids === []) {
                        continue;
                    }
                    $idList = array_keys($ids);
                    foreach ($idList as $id) {
                        $bidFilled[$channel][$id] = true;
                    }
                    $n = $sync->fillLiveBids($channel, $idList);
                    $this->info(strtoupper($channel).' Lbid filled for '.$n.' of '.count($idList).' campaigns that had no saved live bid.');
                }
                if ($pushRows === [] && $pullIds === []) {
                    $this->info('No Enabled calendar-day campaigns have Lbgt different from SBGT or Lbid different from SBID.');
                    break;
                }
                $out = ['synced' => 0, 'failed' => 0, 'skipped' => 0, 'in_progress' => 0];
                if ($pushRows !== []) {
                    $this->info('Enabled calendar-day mismatches: '.count($pushRows).'. Pull live budget and bid, then push only rows that still differ.');
                    $out = $sync->syncRows($pushRows, 'cron-live-sync');
                    $failed += (int) $out['failed'];
                    $this->info('Synced '.$out['synced'].' | Failed '.$out['failed'].' | Skipped '.$out['skipped'].' | In progress '.$out['in_progress']);
                }
                $dirty = Cache::has(AmazonAdsLiveSyncFollowUp::DIRTY_KEY);
                $moreBlank = $this->blankLbidCampaignIds($channelOpt, 1, $onlyCid, $bidFilled) !== [];
                $moreRemain = (count($pushRows) >= $limit && (int) $out['synced'] > 0) || $moreBlank;
                if (! $dirty && ! $moreRemain) {
                    break;
                }
                $this->info($dirty
                    ? 'Grid saved a newer SBID or SBGT during this run. Checking those rows.'
                    : 'This pass hit the limit. Checking the remaining mismatches.');
            }
        } finally {
            Cache::forget(AmazonAdsLiveSyncFollowUp::RUN_KEY);
            Cache::forget(AmazonAdsLiveSyncFollowUp::SPAWN_KEY);
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Write the same six-part SBGT the grid shows onto the latest enabled row,
     * then the mismatch query below can push it. Without this, 21:50 only sees
     * whatever was saved last time the page was opened.
     */
    private function refreshStoredSbgt(string $channelOpt, string $onlyCid): void
    {
        $channels = $channelOpt === 'sp' || $channelOpt === 'sb' ? [$channelOpt] : ['sp', 'sb'];
        foreach ($channels as $channel) {
            $table = $channel === 'sb' ? 'amazon_sb_campaign_reports' : 'amazon_sp_campaign_reports';
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sbgt') || ! Schema::hasColumn($table, 'campaignStatus')) {
                continue;
            }
            $day = $this->latestReportDay($table);
            if ($day === null) {
                continue;
            }
            $q = DB::table($table)
                ->where('report_date_range', $day)
                ->whereRaw("UPPER(TRIM(campaignStatus)) = 'ENABLED'")
                ->select('campaign_id', 'campaignName', 'sbgt');
            if ($onlyCid !== '') {
                $q->where('campaign_id', $onlyCid);
            }
            $campaigns = $q->get();
            if ($campaigns->isEmpty()) {
                continue;
            }
            try {
                $desired = AmazonAdsDesiredSbgtResolver::sbgtForCampaigns($campaigns, $channel);
            } catch (Throwable $e) {
                $this->warn(strtoupper($channel).' grid SBGT was not recalculated: '.$e->getMessage());

                continue;
            }
            $idToBgt = [];
            foreach ($desired as $cid => $sbgt) {
                if ($sbgt === null || $cid === '') {
                    continue;
                }
                $idToBgt[(string) $cid] = $sbgt;
            }
            if ($idToBgt === []) {
                continue;
            }
            AmazonAdsSbgt::persistByRowId($table, [], $idToBgt);
            $this->info(strtoupper($channel).' grid SBGT saved for '.count($idToBgt).' enabled campaigns on '.$day.'.');
        }
    }

    /**
     * Enabled campaigns on the latest calendar day whose Lbid differs from SBID
     * or whose Lbgt differs from the saved SBGT (including SBGT 0, which pauses).
     * Same Stat + calendar window as the grid, plus L30 rows for enabled campaigns
     * Amazon omitted from that day. Paused rows are not included.
     * Grid-detected gaps are merged last so the SBID on screen is what gets pushed.
     *
     * @return list<array<string, mixed>>
     */
    private function rowsFromReports(string $channelOpt, int $limit, string $onlyCid = '', bool $retryFailed = false): array
    {
        $channels = $channelOpt === 'sp' || $channelOpt === 'sb' ? [$channelOpt] : ['sp', 'sb'];
        $merged = [];
        foreach ($channels as $channel) {
            foreach ($this->bidMismatchRows($channel, $limit, $onlyCid, $retryFailed) as $row) {
                $this->mergeSyncRow($merged, $row);
            }
            foreach ($this->l30BidMismatchRows($channel, $limit, $onlyCid, $retryFailed) as $row) {
                $this->mergeSyncRow($merged, $row);
            }
            foreach ($this->budgetMismatchRows($channel, $limit, $onlyCid, $retryFailed) as $row) {
                $this->mergeSyncRow($merged, $row);
            }
        }
        $putBack = [];
        foreach (AmazonAdsLiveSyncFollowUp::pullDirtyRows() as $row) {
            $channel = (string) ($row['channel'] ?? '');
            $cid = trim((string) ($row['campaign_id'] ?? ''));
            if (($channelOpt === 'sp' || $channelOpt === 'sb') && $channel !== $channelOpt) {
                $putBack[] = $row;

                continue;
            }
            if ($onlyCid !== '' && $cid !== $onlyCid) {
                $putBack[] = $row;

                continue;
            }
            $this->mergeSyncRow($merged, $row);
        }
        if ($putBack !== []) {
            AmazonAdsLiveSyncFollowUp::storeDirtyRows($putBack);
        }

        return array_values($merged);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<array<string, mixed>>, 1: array<string, array<string, string>>}
     */
    private function splitBidFills(array $rows): array
    {
        $push = [];
        $pull = [];
        foreach ($rows as $row) {
            $channel = (string) ($row['channel'] ?? '');
            $cid = trim((string) ($row['campaign_id'] ?? ''));
            if (! empty($row['pull_bid']) && $cid !== '' && ($channel === 'sp' || $channel === 'sb') && ! isset($row['sbid'])) {
                $pull[$channel][$cid] = $cid;
            }
            if (isset($row['sbid']) || array_key_exists('sbgt', $row)) {
                unset($row['pull_bid']);
                $push[] = $row;
            }
        }

        return [$push, $pull];
    }

    /**
     * Campaign ids from $ids that already have a row on $day.
     * A second lookup, not a per-row subquery: this reports table is only indexed by id.
     *
     * @param  list<int|string>  $ids
     * @return array<string, true>
     */
    private function campaignIdsOnDay(string $table, string $day, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            $ids
        ), static fn (string $id) => $id !== '')));
        $present = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (DB::table($table)->where('report_date_range', $day)->whereIn('campaign_id', $chunk)->pluck('campaign_id') as $id) {
                $id = trim((string) $id);
                if ($id !== '') {
                    $present[$id] = true;
                }
            }
        }

        return $present;
    }

    /**
     * Enabled campaigns whose Lbid cell is still empty. SBID is not required.
     *
     * @param  array<string, array<string, true>>  $skip
     * @return array<string, array<string, string>>
     */
    private function blankLbidCampaignIds(string $channelOpt, int $limit, string $onlyCid, array $skip): array
    {
        $channels = $channelOpt === 'sp' || $channelOpt === 'sb' ? [$channelOpt] : ['sp', 'sb'];
        $out = [];
        foreach ($channels as $channel) {
            $table = $channel === 'sb' ? 'amazon_sb_campaign_reports' : 'amazon_sp_campaign_reports';
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'last_sbid') || ! Schema::hasColumn($table, 'campaignStatus')) {
                continue;
            }
            $day = $this->latestReportDay($table);
            if ($day === null) {
                continue;
            }
            $skipIds = array_keys($skip[$channel] ?? []);
            $take = function (Builder $q) use (&$out, $channel, $limit, $skipIds, $onlyCid): void {
                $room = $limit - count($out[$channel] ?? []);
                if ($room <= 0) {
                    return;
                }
                $q->whereRaw("UPPER(TRIM(campaignStatus)) = 'ENABLED'")
                    ->where(function ($w) {
                        $w->whereNull('last_sbid')->orWhere('last_sbid', '=', '');
                    });
                if ($skipIds !== []) {
                    $q->whereNotIn('campaign_id', array_slice($skipIds, 0, 1000));
                }
                if ($onlyCid !== '') {
                    $q->where('campaign_id', $onlyCid);
                }
                foreach ($q->orderBy('id')->limit($room)->pluck('campaign_id') as $id) {
                    $id = trim((string) $id);
                    if ($id !== '') {
                        $out[$channel][$id] = $id;
                    }
                }
            };
            $take(DB::table($table)->where('report_date_range', $day));
            if (count($out[$channel] ?? []) < $limit) {
                $lq = DB::table($table)
                    ->where('report_date_range', 'L30')
                    ->whereRaw("UPPER(TRIM(campaignStatus)) = 'ENABLED'")
                    ->where(function ($w) {
                        $w->whereNull('last_sbid')->orWhere('last_sbid', '=', '');
                    });
                if ($skipIds !== []) {
                    $lq->whereNotIn('campaign_id', array_slice($skipIds, 0, 1000));
                }
                if ($onlyCid !== '') {
                    $lq->where('campaign_id', $onlyCid);
                }
                $candidates = [];
                foreach ($lq->orderBy('id')->pluck('campaign_id') as $id) {
                    $id = trim((string) $id);
                    if ($id !== '' && ! isset($out[$channel][$id])) {
                        $candidates[] = $id;
                    }
                }
                $onDay = $this->campaignIdsOnDay($table, $day, $candidates);
                foreach ($candidates as $id) {
                    if (isset($onDay[$id])) {
                        continue;
                    }
                    $out[$channel][$id] = $id;
                    if (count($out[$channel]) >= $limit) {
                        break;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bidMismatchRows(string $channel, int $limit, string $onlyCid, bool $retryFailed = false): array
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
        $this->skipRecentFailures($q, $channel, 'bid', $retryFailed);
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
     * Enabled campaign that the latest calendar day omitted (zero impressions).
     * The grid still shows its L30 row, so a Lbid/SBID gap there has to be pushed too.
     *
     * @return list<array<string, mixed>>
     */
    private function l30BidMismatchRows(string $channel, int $limit, string $onlyCid, bool $retryFailed = false): array
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
            ->where('r.report_date_range', 'L30')
            ->whereRaw("UPPER(TRIM(r.campaignStatus)) = 'ENABLED'")
            ->whereNotNull('r.sbid')
            ->where('r.sbid', '!=', '')
            ->where('r.sbid', '!=', '0')
            ->whereNotNull('r.last_sbid')
            ->where('r.last_sbid', '!=', '')
            ->whereRaw('ROUND((r.sbid + 0), 2) <> ROUND((r.last_sbid + 0), 2)');
        if ($onlyCid !== '') {
            $q->where('r.campaign_id', $onlyCid);
        }
        $this->skipRecentFailures($q, $channel, 'bid', $retryFailed);
        $found = $q->orderBy('r.id')->get(['r.campaign_id', 'r.campaignName', 'r.sbid']);
        $onDay = $this->campaignIdsOnDay($table, $day, $found->pluck('campaign_id')->all());
        $out = [];
        $seen = [];
        foreach ($found as $row) {
            $cid = (string) $row->campaign_id;
            if ($cid === '' || isset($seen[$cid]) || isset($onDay[$cid])) {
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
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Saved SBGT vs Lbgt (campaignBudgetAmount), same comparison as SBID vs Lbid.
     * SBGT 0 is included so the campaign is paused instead of left on the old budget.
     *
     * @return list<array<string, mixed>>
     */
    private function budgetMismatchRows(string $channel, int $limit, string $onlyCid, bool $retryFailed = false): array
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
        $this->skipRecentFailures($q, $channel, 'bgt', $retryFailed);
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
     * A follow-up run should push a newly saved SBID/SBGT. It should not
     * hammer Amazon again for a failure from the last 12 hours. The 21:50
     * run passes --retry-failed and includes those.
     */
    private function skipRecentFailures(Builder $query, string $channel, string $field, bool $retryFailed): void
    {
        if ($retryFailed || ! Schema::hasTable('amazon_ads_live_sync_states')) {
            return;
        }
        $query->leftJoin('amazon_ads_live_sync_states as s', function ($join) use ($channel, $field) {
            $join->on('s.campaign_id', '=', 'r.campaign_id')
                ->where('s.channel', '=', $channel)
                ->where('s.field', '=', $field);
        })->where(function ($w) {
            $w->whereNull('s.id')
                ->orWhere('s.status', '!=', 'failed')
                ->orWhere('s.updated_at', '<', now()->subHours(12));
        });
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
        $today = Carbon::now(config('app.timezone'))->format('Y-m-d');
        $max = DB::table($table)
            ->where('report_date_range', '>=', '2010-01-01')
            ->where('report_date_range', '<=', $today)
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
            ->max('report_date_range');
        if ($max === null || $max === '') {
            return null;
        }

        return (string) $max;
    }
}
