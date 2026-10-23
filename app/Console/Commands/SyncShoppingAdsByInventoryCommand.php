<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\GoogleShoppingCampaignsController;
use App\Services\GoogleAdsSbidService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * "Ads Needed" automation: inside every ENABLED Shopping campaign, create / enable the Item ID of each SKU that has
 * inventory (INV > 0) and take out the Item ID of each SKU without inventory (INV <= 0). Runs hourly.
 * Campaigns themselves are never enabled or paused here (the price / Dil / INV budget rules do that).
 */
class SyncShoppingAdsByInventoryCommand extends Command
{
    protected $signature = 'google:sync-shopping-ads-by-inventory
                            {--dry-run : List the changes without touching Google Ads}
                            {--campaign-ids= : Comma-separated campaign IDs (limits the run)}';

    protected $description = 'Create ads (Item IDs) for SKUs with inventory and pause/remove ads for SKUs without inventory';

    public function handle(GoogleAdsSbidService $sbidService, GoogleShoppingCampaignsController $grid): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $customerId = (string) config('services.google_ads.login_customer_id');
        if ($customerId === '') {
            $this->error('Google Ads customer ID is not configured.');

            return self::FAILURE;
        }

        $needed = $grid->adsNeededAllCampaigns(null, true);
        if ($needed['error'] !== '') {
            $this->error('Could not read live ads: '.$needed['error']);

            return self::FAILURE;
        }

        $only = [];
        foreach (preg_split('/\s*,\s*/', (string) $this->option('campaign-ids'), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $p) {
            $d = preg_replace('/\D/', '', $p);
            if ($d !== '') {
                $only[$d] = true;
            }
        }
        $todo = array_filter($needed['map'], static fn ($m) => $m['enable_items'] !== [] || $m['remove_items'] !== []);
        if ($only !== []) {
            $todo = array_intersect_key($todo, $only);
        }
        $this->info(count($todo).' campaign(s) need changes ('.$needed['total'].' SKU(s) with INV but no running ad).');
        if ($todo === []) {
            return self::SUCCESS;
        }

        try {
            $nodesBy = $sbidService->fetchListingGroupNodesByCampaign($customerId, array_map('strval', array_keys($todo)));
        } catch (\Throwable $e) {
            $this->error('Could not read listing groups: '.GoogleAdsSbidService::briefGoogleError($e));

            return self::FAILURE;
        }

        $changed = 0;
        $failed = 0;
        foreach ($todo as $cid => $m) {
            $cid = (string) $cid;
            $res = $sbidService->syncItemsByInventory($customerId, $cid, $nodesBy[$cid] ?? [], $m['enable_items'], $m['remove_items'], $dryRun);
            $label = "{$cid} ({$m['name']})";
            if ($res['status'] === 'error') {
                $failed++;
                $this->error("{$label}: {$res['message']}");
            } elseif ($res['status'] === 'skipped') {
                $this->warn("{$label}: skipped — {$res['message']}");
            } elseif ($res['status'] === 'ok') {
                $changed++;
                $this->info(($dryRun ? '[DRY RUN] ' : '')."{$label}: enable [".implode(', ', $m['skus'])."], remove (INV 0) [".implode(', ', $m['pause']).']');
            }
        }

        if ($changed > 0 && ! $dryRun) {
            Cache::forever('gads_shopping_item_ids_gen', ((int) Cache::get('gads_shopping_item_ids_gen', 0)) + 1);
            foreach (['shopping', 'serp', 'youtube'] as $channel) {
                $k = 'gads_raw_grid_ver:'.$channel;
                if (! Cache::add($k, 2, now()->addYear())) {
                    Cache::increment($k);
                }
            }
        }

        $this->info(($dryRun ? 'Would change ' : 'Changed ')."{$changed} campaign(s); {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
