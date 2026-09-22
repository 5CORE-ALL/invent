<?php

namespace App\Console\Commands;

use App\Models\AmazonAdsLiveSyncState;
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

        $rows = $this->rowsFromUnverifiedStates($channelOpt, $onlyCid);
        if ($onlyCid === '') {
            $rows = $this->mergeRows($rows, $this->rowsFromReports($channelOpt, $limit));
        } elseif ($rows === []) {
            $rows = $this->mergeRows($rows, $this->rowsFromReports($channelOpt, $limit, $onlyCid));
        }

        if ($rows === []) {
            $this->info('No unverified Amazon Ads bid/BGT rows to retry.');

            return self::SUCCESS;
        }

        $this->info('Pulling live bid/BGT for '.count($rows).' campaign(s). Push only where Amazon still differs from SBID/SBGT.');
        $out = $sync->syncRows($rows, 'cron-live-sync');
        $this->info('Synced '.$out['synced'].' | Failed '.$out['failed'].' | Skipped '.$out['skipped'].' | In progress '.$out['in_progress']);

        return $out['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rowsFromUnverifiedStates(string $channelOpt, string $onlyCid): array
    {
        $query = AmazonAdsLiveSyncState::query()->orderBy('id');
        if ($channelOpt === 'sp' || $channelOpt === 'sb') {
            $query->where('channel', $channelOpt);
        }
        if ($onlyCid !== '') {
            $query->where('campaign_id', $onlyCid);
        }
        $query->whereIn('status', ['failed', 'pending', 'in_progress']);

        $rows = [];
        foreach ($query->get() as $state) {
            $row = [
                'campaign_id' => $state->campaign_id,
                'channel' => $state->channel,
                'campaign_name' => $state->campaign_name,
            ];
            if ($state->field === 'bid') {
                $row['sbid'] = $state->desired_value;
            } else {
                $row['sbgt'] = $state->desired_value;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * L30 campaigns that are unverified, or whose live bid no longer matches SBID.
     * A previous synced mark does not skip a mismatch: the sync pulls Amazon, pushes SBID, then pulls again.
     *
     * @return list<array<string, mixed>>
     */
    private function rowsFromReports(string $channelOpt, int $limit, string $onlyCid = ''): array
    {
        $channels = $channelOpt === 'sp' || $channelOpt === 'sb' ? [$channelOpt] : ['sp', 'sb'];
        $out = [];
        foreach ($channels as $channel) {
            $table = $channel === 'sb' ? 'amazon_sb_campaign_reports' : 'amazon_sp_campaign_reports';
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'last_sbid')) {
                continue;
            }
            $mismatch = AmazonAdsLiveBidBgtSyncService::BID_TOLERANCE;
            $q = DB::table($table.' as r')
                ->leftJoin('amazon_ads_live_sync_states as s', function ($join) use ($channel) {
                    $join->on('s.campaign_id', '=', 'r.campaign_id')
                        ->where('s.channel', '=', $channel)
                        ->where('s.field', '=', 'bid');
                })
                ->where('r.report_date_range', 'L30')
                ->where(function ($w) use ($mismatch) {
                    $w->where(function ($unverified) {
                        $unverified->whereNotNull('r.last_sbid')
                            ->where('r.last_sbid', '!=', '')
                            ->where('r.last_sbid', '!=', '0')
                            ->where(function ($st) {
                                $st->whereNull('s.id')->orWhere('s.status', '!=', 'synced');
                            });
                    })->orWhere(function ($diff) use ($mismatch) {
                        // Previously synced, but the rule SBID no longer matches the live bid.
                        $diff->whereNotNull('r.sbid')
                            ->where('r.sbid', '!=', '')
                            ->where('r.sbid', '!=', '0')
                            ->whereNotNull('r.last_sbid')
                            ->where('r.last_sbid', '!=', '')
                            ->whereRaw('ABS((r.sbid + 0) - (r.last_sbid + 0)) > ?', [$mismatch]);
                    });
                })
                ->orderByRaw(
                    'CASE WHEN r.sbid IS NOT NULL AND r.last_sbid IS NOT NULL AND ABS((r.sbid + 0) - (r.last_sbid + 0)) > ? THEN 0 ELSE 1 END',
                    [$mismatch]
                );
            if ($onlyCid !== '') {
                $q->where('r.campaign_id', $onlyCid);
            }
            $found = $q->orderBy('r.id')
                ->limit($limit)
                ->get(['r.campaign_id', 'r.campaignName', 'r.last_sbid', 'r.sbid']);
            foreach ($found as $row) {
                $bid = AmazonAdsLiveBidBgtSyncService::positiveNumber($row->sbid ?? null)
                    ?? AmazonAdsLiveBidBgtSyncService::positiveNumber($row->last_sbid ?? null);
                if ($bid === null) {
                    continue;
                }
                $out[] = [
                    'campaign_id' => (string) $row->campaign_id,
                    'channel' => $channel,
                    'campaign_name' => (string) ($row->campaignName ?? ''),
                    'sbid' => $bid,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $base
     * @param  list<array<string, mixed>>  $extra
     * @return list<array<string, mixed>>
     */
    private function mergeRows(array $base, array $extra): array
    {
        $byCid = [];
        foreach (array_merge($base, $extra) as $row) {
            $cid = trim((string) ($row['campaign_id'] ?? ''));
            if ($cid === '') {
                continue;
            }
            $cur = $byCid[$cid] ?? [
                'campaign_id' => $cid,
                'channel' => $row['channel'] ?? 'sp',
                'campaign_name' => $row['campaign_name'] ?? '',
            ];
            if (isset($row['sbid'])) {
                $cur['sbid'] = $row['sbid'];
            }
            if (isset($row['sbgt'])) {
                $cur['sbgt'] = $row['sbgt'];
            }
            if (($cur['campaign_name'] ?? '') === '' && isset($row['campaign_name'])) {
                $cur['campaign_name'] = $row['campaign_name'];
            }
            $byCid[$cid] = $cur;
        }

        return array_values($byCid);
    }
}
