<?php

namespace App\Console\Commands;

use App\Http\Controllers\AmazonAds\AmazonFbmTargetingController;
use App\Services\AmazonAdsService;
use Illuminate\Console\Command;

/**
 * Reads every SP campaign with its live state from Amazon for /amazon-ads/fbm-targeting.
 * The report tables only know a campaign's status from its last report row, which goes stale once a campaign is
 * paused / archived (no more rows), and they miss campaigns that never had a report row. The page starts this in
 * the background when its copy is older than 30 minutes.
 */
class AmazonFbmCampaignsSync extends Command
{
    protected $signature = 'app:amazon-fbm-campaigns-sync';

    protected $description = 'Refresh live SP campaign states from Amazon for the FBM targeting page';

    public function handle(AmazonFbmTargetingController $page, AmazonAdsService $ads): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        try {
            $n = $page->syncLiveCampaigns($ads);
            $this->info('Read '.$n.' SP campaigns from Amazon.');

            return 0;
        } catch (\Throwable $e) {
            report($e);
            $this->error($e->getMessage());

            return 1;
        }
    }
}
