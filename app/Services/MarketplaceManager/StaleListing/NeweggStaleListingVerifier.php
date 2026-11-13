<?php

namespace App\Services\MarketplaceManager\StaleListing;

use App\Models\NeweggItem;
use App\Models\NeweggMetric;
use App\Models\NeweggPricing;
use App\Services\MarketplaceManager\NeweggLiveListingsService;
use App\Services\NeweggApiService;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingChannelCounts;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Newegg B2C and B2B share one catalog. "Listed" is ChannelListingRegistry::listedNewegg: a real Newegg item
 * number (9SI...) on the SKU in newegg_items.newegg_item_number, newegg_pricing.newegg_item_number or
 * newegg_metric.product_id (or the NeweggLiveListingsService cache, which is built from newegg_pricing).
 *
 * Every distinct SKU that holds such an id is asked about on Newegg. A cheap Get Batch Inventory pass (100 SKUs
 * per call) confirms the SKUs Newegg returns; every other SKU gets a single Get Item Inventory lookup
 * (NeweggApiService::sellerItemState). Only a clear "not found" answer for every spelling of the SKU counts as
 * gone: then the id is blanked in all three tables (rows stay) and the live listings cache is dropped.
 *
 * Errors, Cloudflare, rate limits and unrecognised answers keep the SKU. SKUs the item report marks as
 * B2B-only are not checked (the inventory endpoint used here is the B2C one).
 * The two Newegg pages run this same class, so a run less than 5 minutes old is reused by the other page.
 */
class NeweggStaleListingVerifier implements StaleListingVerifier
{
    private const BATCH_SIZE = 100;

    private const MAX_SINGLE_LOOKUPS = 1500;

    private const RESULT_KEY = 'stale_verify:newegg:last_result';

    private const LOCK_KEY = 'stale_verify:newegg:lock';

    public function verify(): array
    {
        $out = ['ok' => false, 'message' => '', 'checked' => 0, 'cleared' => 0, 'kept' => 0, 'errors' => 0];

        if (! Schema::hasTable('newegg_pricing') && ! Schema::hasTable('newegg_items') && ! Schema::hasTable('newegg_metric')) {
            $out['message'] = 'Newegg: catalog tables not found.';

            return $out;
        }
        $api = app(NeweggApiService::class);
        if (! $api->isConfigured()) {
            $out['message'] = 'Newegg: API credentials missing, nothing checked.';

            return $out;
        }

        $last = Cache::get(self::RESULT_KEY);
        if (is_array($last) && isset($last['at'], $last['result']) && time() - (int) $last['at'] < 300 && is_array($last['result'])) {
            $reused = $last['result'];
            $reused['message'] = 'Already checked '.max(1, (int) round((time() - (int) $last['at']) / 60)).' min ago for the other Newegg page. '.(string) ($reused['message'] ?? '');

            return $reused;
        }

        $lock = Cache::lock(self::LOCK_KEY, 3600);
        if (! $lock->get()) {
            $out['ok'] = true;
            $out['message'] = 'Newegg check is already running for the other Newegg page, skipped.';

            return $out;
        }

        try {
            $out = $this->run($api, $out);
        } finally {
            optional($lock)->release();
        }

        if ($out['ok']) {
            Cache::put(self::RESULT_KEY, ['at' => time(), 'result' => $out], now()->addMinutes(5));
        }

        return $out;
    }

    /**
     * @param  array{ok: bool, message: string, checked: int, cleared: int, kept: int, errors: int}  $out
     * @return array{ok: bool, message: string, checked: int, cleared: int, kept: int, errors: int}
     */
    private function run(NeweggApiService $api, array $out): array
    {
        $groups = $this->collect();
        $skipped = 0;
        $todo = [];
        foreach ($groups as $key => $group) {
            if ($group['b2b']) {
                $skipped++;

                continue;
            }
            $todo[$key] = $group['sku'];
        }
        if ($todo === []) {
            $out['ok'] = true;
            $out['kept'] = $skipped;
            $out['message'] = 'Newegg: no listed SKU with a Newegg item number to check.';

            return $out;
        }

        // Pass 1: SKUs Newegg returns in a batch inventory answer are live, no single lookup needed.
        $present = [];
        foreach (array_chunk(array_values($todo), self::BATCH_SIZE) as $chunk) {
            try {
                $res = $api->getBatchInventory($chunk);
            } catch (\Throwable $e) {
                continue;
            }
            if (! empty($res['blocked_by_cloudflare']) || empty($res['ok']) || ! is_array($res['json'] ?? null)) {
                continue;
            }
            foreach (array_keys($api->indexBatchItemsBySellerPartNumber($api->extractBatchItemList($res['json']))) as $batchKey) {
                $present[$batchKey] = true;
            }
            usleep(150000);
        }

        // Pass 2: single lookup for the rest.
        $live = 0;
        $gone = [];
        $lookups = 0;
        $consecutiveErrors = 0;
        $aborted = false;
        $capped = 0;
        foreach ($todo as $key => $sku) {
            $out['checked']++;
            if (isset($present[$api->batchSellerPartKey($sku)])) {
                $live++;

                continue;
            }
            if ($aborted || $lookups >= self::MAX_SINGLE_LOOKUPS) {
                $out['checked']--;
                $capped++;

                continue;
            }

            $lookups++;
            try {
                $res = $api->sellerItemState($sku);
            } catch (\Throwable $e) {
                $res = ['state' => 'unknown', 'message' => $e->getMessage(), 'item_number' => ''];
            }
            usleep(120000);

            if ($res['state'] === 'live') {
                $live++;
                $consecutiveErrors = 0;
            } elseif ($res['state'] === 'gone') {
                $gone[$key] = $sku;
                $consecutiveErrors = 0;
            } else {
                $out['errors']++;
                $consecutiveErrors++;
                Log::warning('NeweggStaleListingVerifier: could not verify SKU', ['sku' => $sku, 'message' => $res['message']]);
                if ($consecutiveErrors >= 5) {
                    $aborted = true;
                }
            }
        }

        // A wrong "not found" for every SKU (API change, wrong seller) must not wipe the catalog.
        if (count($gone) >= 20 && count($gone) > (int) floor(count($todo) * 0.8)) {
            $out['errors'] += count($gone);
            $out['message'] = 'Newegg says '.count($gone).' of '.count($todo).' listed SKUs do not exist, too many to trust - nothing removed.';

            return $out;
        }

        foreach ($gone as $sku) {
            try {
                $this->clearSku($sku);
                $out['cleared']++;
                Log::info('NeweggStaleListingVerifier: SKU is no longer on Newegg, item number cleared', ['sku' => $sku]);
            } catch (\Throwable $e) {
                $out['errors']++;
                Log::warning('NeweggStaleListingVerifier: update failed', ['sku' => $sku, 'error' => $e->getMessage()]);
            }
        }
        if ($out['cleared'] > 0) {
            $this->clearListingCaches();
        }

        $out['kept'] = $live + $skipped + $capped;
        $out['ok'] = ! $aborted;
        $out['message'] = "Checked {$out['checked']} listed SKU(s) on Newegg: {$out['cleared']} no longer on Newegg"
            .($out['cleared'] > 0 ? ' (now Missing L)' : '').", {$live} still live, {$out['errors']} could not be verified"
            .($skipped > 0 ? ", {$skipped} B2B-only SKU(s) not checked" : '')
            .($aborted ? '. Five failures in a row (Cloudflare, rate limit or credentials); stopped, run again later' : '')
            .($capped > 0 ? ". {$capped} SKU(s) left for the next run (lookup limit)" : '').'.';

        return $out;
    }

    /**
     * Distinct SKUs (upper case, trimmed) that hold a live-looking Newegg item number in any Listed source.
     *
     * @return array<string, array{sku: string, b2b: bool}>
     */
    private function collect(): array
    {
        $groups = [];
        $add = static function (string $sku, string $id, bool $b2b = false) use (&$groups): void {
            $sku = trim($sku);
            $id = trim($id);
            if ($sku === '' || stripos($sku, 'PARENT') !== false || ! ChannelListingRegistry::isLiveNeweggListingId($id, $sku)) {
                return;
            }
            $key = strtoupper($sku);
            $groups[$key] ??= ['sku' => $sku, 'b2b' => false];
            if ($b2b) {
                $groups[$key]['b2b'] = true;
            }
        };

        if (Schema::hasTable('newegg_items')) {
            NeweggItem::query()
                ->whereNotNull('seller_part_number')->where('seller_part_number', '!=', '')
                ->whereNotNull('newegg_item_number')->where('newegg_item_number', '!=', '')
                ->select(['id', 'seller_part_number', 'newegg_item_number', 'platform'])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($add) {
                    foreach ($rows as $row) {
                        $platform = strtolower((string) ($row->platform ?? ''));
                        $add((string) $row->seller_part_number, (string) $row->newegg_item_number, str_contains($platform, 'b2b') || str_contains($platform, 'business'));
                    }
                });
        }
        if (Schema::hasTable('newegg_pricing')) {
            NeweggPricing::query()
                ->whereNotNull('seller_part_number')->where('seller_part_number', '!=', '')
                ->whereNotNull('newegg_item_number')->where('newegg_item_number', '!=', '')
                ->select(['id', 'seller_part_number', 'newegg_item_number'])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($add) {
                    foreach ($rows as $row) {
                        $add((string) $row->seller_part_number, (string) $row->newegg_item_number);
                    }
                });
        }
        if (Schema::hasTable('newegg_metric')) {
            NeweggMetric::query()
                ->whereNotNull('sku')->where('sku', '!=', '')
                ->whereNotNull('product_id')->where('product_id', '!=', '')
                ->select(['id', 'sku', 'product_id'])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($add) {
                    foreach ($rows as $row) {
                        $add((string) $row->sku, (string) $row->product_id);
                    }
                });
        }

        return $groups;
    }

    /**
     * Blank the item number of the SKU in every table the Listed rule reads, in one transaction.
     * None of the three tables is unique on the id column (newegg_items on SKU, newegg_pricing on SKU and country,
     * newegg_metric on SKU), so blanking cannot collide and no row is deleted.
     */
    private function clearSku(string $sku): void
    {
        $upper = strtoupper(trim($sku));

        DB::transaction(function () use ($upper) {
            if (Schema::hasTable('newegg_items')) {
                NeweggItem::query()->whereRaw('UPPER(TRIM(seller_part_number)) = ?', [$upper])
                    ->whereNotNull('newegg_item_number')->update(['newegg_item_number' => null]);
            }
            if (Schema::hasTable('newegg_pricing')) {
                NeweggPricing::query()->whereRaw('UPPER(TRIM(seller_part_number)) = ?', [$upper])
                    ->whereNotNull('newegg_item_number')->update(['newegg_item_number' => null]);
            }
            if (Schema::hasTable('newegg_metric')) {
                NeweggMetric::query()->whereRaw('UPPER(TRIM(sku)) = ?', [$upper])
                    ->whereNotNull('product_id')->update(['product_id' => null]);
            }
        });
    }

    /** Same caches NeweggListingPublishService::forgetListingCaches drops, for both Newegg pages. */
    private function clearListingCaches(): void
    {
        try {
            app(NeweggLiveListingsService::class)->clearCache();
            Cache::forget(ListingChannelCounts::TOTAL_CACHE_KEY);
            foreach (['neweggb2c', 'neweggb2b'] as $channel) {
                Cache::forget('listing_channel_counts_v1:inv:'.$channel);
                Cache::forget('listing_channel_counts_v1:cp:'.$channel);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
