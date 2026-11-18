<?php

namespace App\Console\Commands;

use App\Http\Controllers\AmazonAdsMissingController;
use App\Support\AmazonAdsEnabledCampaignSync;
use Illuminate\Console\Command;

/**
 * Keeps /amazon-ads/missing in step with Amazon: pulls every ENABLED SP/SB campaign into the report tables
 * (even with zero impressions), then links every live KW / PT / HL campaign to its parent. Before this ran
 * on a schedule, links were only created when someone opened the page (or pressed Import HL) and only for
 * campaigns that already had report rows, so new campaigns kept showing "Missing".
 */
class AmazonAdsSyncMissingLinks extends Command
{
    protected $signature = 'amazon:ads-sync-missing-links';

    protected $description = 'Fetch newly created Amazon SP/SB campaigns and link them to their parents on /amazon-ads/missing';

    public function handle(AmazonAdsEnabledCampaignSync $sync, AmazonAdsMissingController $missing): int
    {
        $failed = false;

        $profileId = (string) config('services.amazon_ads.profile_ids');
        if ($profileId === '') {
            $this->error('AMAZON_ADS_PROFILE_IDS is not set.');

            return self::FAILURE;
        }

        // 1. Campaigns -> report tables (so metrics / status exist for new campaigns).
        $day = now()->subDay()->toDateString();
        foreach (['syncSp' => 'SP', 'syncSb' => 'SB'] as $method => $label) {
            try {
                $r = $sync->{$method}($profileId, $day);
                $this->info("{$label} campaigns created={$r['created']} already_present={$r['skipped']}");
            } catch (\Throwable $e) {
                $failed = true;
                $this->error("{$label} campaign sync failed: ".$e->getMessage());
            }
        }

        // 2. Campaigns -> parent links.
        $result = $missing->syncLinksFromLiveCampaigns();
        $this->info($result['message']);

        return ($failed || ! $result['ok']) ? self::FAILURE : self::SUCCESS;
    }
}
