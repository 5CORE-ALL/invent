<?php

namespace App\Console\Commands;

use App\Support\AmazonAdsEnabledCampaignSync;
use Illuminate\Console\Command;

class AmazonAdsSyncEnabledCampaigns extends Command
{
    protected $signature = 'amazon:ads-sync-enabled-campaigns';

    protected $description = 'Insert ENABLED Amazon SP/SB campaigns that reports omitted (zero activity) so /amazon-ads/all matches Amazon';

    public function handle(AmazonAdsEnabledCampaignSync $sync): int
    {
        $profileId = (string) config('services.amazon_ads.profile_ids');
        if ($profileId === '') {
            $this->error('AMAZON_ADS_PROFILE_IDS is not set.');

            return 1;
        }

        $day = now()->subDay()->toDateString();
        $this->info("Syncing ENABLED campaigns missing from reports (daily {$day} + L30)…");

        $sp = $sync->syncSp($profileId, $day);
        $this->info("SP created={$sp['created']} already_present={$sp['skipped']}");

        $sb = $sync->syncSb($profileId, $day);
        $this->info("SB created={$sb['created']} already_present={$sb['skipped']}");

        return 0;
    }
}
