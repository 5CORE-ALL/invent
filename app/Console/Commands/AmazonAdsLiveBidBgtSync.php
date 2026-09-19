<?php

namespace App\Console\Commands;

use App\Models\AmazonAdsLiveSyncState;
use App\Services\AmazonAdsLiveBidBgtSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class AmazonAdsLiveBidBgtSync extends Command
{
    protected $signature = 'amazon:ads-live-bid-bgt-sync
        {--channel=all : sp, sb, or all}
        {--campaign-id= : Only this campaign ID}
        {--failed-only : Retry rows that are not verified}';

    protected $description = 'Pull live Amazon BGT/BID, compare to rule SBGT/SBID, push mismatches, verify before marking synced';

    public function handle(AmazonAdsLiveBidBgtSyncService $sync): int
    {
        if (! Schema::hasTable('amazon_ads_live_sync_states')) {
            $this->error('amazon_ads_live_sync_states is missing. Run migrations.');

            return self::FAILURE;
        }

        $channelOpt = strtolower((string) $this->option('channel'));
        $onlyCid = trim((string) $this->option('campaign-id'));
        $query = AmazonAdsLiveSyncState::query()->orderBy('id');
        if ($channelOpt === 'sp' || $channelOpt === 'sb') {
            $query->where('channel', $channelOpt);
        }
        if ($onlyCid !== '') {
            $query->where('campaign_id', $onlyCid);
        }
        if ($this->option('failed-only')) {
            $query->whereIn('status', ['failed', 'pending', 'in_progress']);
        } else {
            $query->whereIn('status', ['failed', 'pending', 'in_progress']);
        }

        $states = $query->get();
        if ($states->isEmpty()) {
            $this->info('No unverified Amazon Ads bid/BGT rows to retry.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($states as $state) {
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

        $this->info('Retrying '.$states->count().' unverified live sync row(s).');
        $out = $sync->syncRows($rows, 'cron-live-sync');
        $this->info('Synced '.$out['synced'].' | Failed '.$out['failed'].' | Skipped '.$out['skipped'].' | In progress '.$out['in_progress']);

        return $out['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
