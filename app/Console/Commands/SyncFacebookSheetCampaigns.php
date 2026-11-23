<?php

namespace App\Console\Commands;

use App\Http\Controllers\FacebookAllAdsSheetController;
use Illuminate\Console\Command;

/**
 * Rebuilds the Campaign list of /facebook-all-ads-sheet from the Meta API tables
 * (meta_campaigns) so the sheet no longer depends on a manual Campaign upload.
 * Tags (Ad Type / CH / B2B-B2C) and Link are carried over by Campaign ID.
 */
class SyncFacebookSheetCampaigns extends Command
{
    protected $signature = 'facebook:sync-sheet-campaigns';

    protected $description = 'Build the Facebook all-ads sheet Campaign list from the Meta API (meta_campaigns)';

    public function handle(): int
    {
        $res = app(FacebookAllAdsSheetController::class)->syncCampaignBatchFromApi();

        $res['success'] ? $this->info($res['message']) : $this->error($res['message']);

        return $res['success'] ? 0 : 1;
    }
}
