<?php

namespace App\Console\Commands;

use App\Services\GoogleAdsSbidService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Removes Item ID units that are excluded (grey "−" in Google Ads) or paused from every Shopping campaign,
 * so only serving Item IDs stay in the campaign. Runs hourly.
 */
class RemoveInactiveShoppingItemsCommand extends Command
{
    protected $signature = 'google:remove-inactive-shopping-items
                            {--dry-run : List what would be removed without changing Google Ads}
                            {--campaign-ids= : Comma-separated campaign IDs (limits the run)}';

    protected $description = 'Remove excluded / paused Item IDs from Google Shopping campaigns';

    public function handle(GoogleAdsSbidService $sbidService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $customerId = (string) config('services.google_ads.login_customer_id');
        if ($customerId === '') {
            $this->error('Google Ads customer ID is not configured.');

            return self::FAILURE;
        }

        $maxDate = DB::table('google_ads_campaigns')->where('advertising_channel_type', 'SHOPPING')->max('date');
        if (! $maxDate) {
            $this->warn('No Shopping campaign rows found.');

            return self::SUCCESS;
        }

        $only = [];
        foreach (preg_split('/\s*,\s*/', (string) $this->option('campaign-ids'), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $p) {
            $d = preg_replace('/\D/', '', $p);
            if ($d !== '') {
                $only[$d] = true;
            }
        }

        $rows = DB::table('google_ads_campaigns')
            ->where('advertising_channel_type', 'SHOPPING')
            ->whereRaw("UPPER(campaign_name) NOT LIKE '% SEARCH%'")
            ->whereRaw("UPPER(campaign_name) NOT LIKE '% YT'")
            ->where('date', '>=', \Carbon\Carbon::parse($maxDate)->subDays(10)->toDateString())
            ->orderByDesc('date')
            ->get(['campaign_id', 'campaign_name', 'campaign_status']);
        $campaigns = [];
        foreach ($rows as $r) {
            $cid = (string) $r->campaign_id;
            if (isset($campaigns[$cid])) {
                continue;
            }
            if (in_array(strtoupper((string) $r->campaign_status), ['REMOVED', 'ARCHIVED'], true)) {
                $campaigns[$cid] = null;

                continue;
            }
            $campaigns[$cid] = (string) $r->campaign_name;
        }
        $campaigns = array_filter($campaigns, static fn ($n) => $n !== null);
        if ($only !== []) {
            $campaigns = array_intersect_key($campaigns, $only);
        }
        $this->info('Checking '.count($campaigns).' Shopping campaign(s)…');

        try {
            $nodesByCampaign = $sbidService->fetchListingGroupNodesByCampaign($customerId, array_keys($campaigns));
        } catch (\Throwable $e) {
            $this->error('Could not read listing groups: '.GoogleAdsSbidService::briefGoogleError($e));

            return self::FAILURE;
        }

        $total = 0;
        $failed = 0;
        $changed = 0;
        foreach ($campaigns as $cid => $name) {
            $res = $sbidService->removeInactiveShoppingItems($customerId, (string) $cid, $nodesByCampaign[$cid] ?? [], $dryRun);
            if ($res['status'] === 'error') {
                $failed++;
                $this->error("{$cid} ({$name}): {$res['message']}");

                continue;
            }
            if ($res['removed'] === []) {
                continue;
            }
            $changed++;
            $total += count($res['removed']);
            $list = implode(', ', array_map(static fn ($r) => $r['item'].' ['.$r['reason'].']', $res['removed']));
            $this->info(($dryRun ? '[DRY RUN] Would remove ' : 'Removed ')."from {$cid} ({$name}): {$list}");
        }

        if ($changed > 0 && ! $dryRun) {
            // Live Ads Item IDs are cached for the ID / Verification check — invalidate them.
            Cache::forever('gads_shopping_item_ids_gen', ((int) Cache::get('gads_shopping_item_ids_gen', 0)) + 1);
            foreach (['shopping', 'serp', 'youtube'] as $channel) {
                $k = 'gads_raw_grid_ver:'.$channel;
                if (! Cache::add($k, 2, now()->addYear())) {
                    Cache::increment($k);
                }
            }
        }

        $this->info(($dryRun ? 'Would remove ' : 'Removed ')."{$total} Item ID(s) from {$changed} campaign(s); {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
