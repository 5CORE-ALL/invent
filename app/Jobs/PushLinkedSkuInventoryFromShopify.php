<?php

namespace App\Jobs;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\AlibabaInventorySyncService;
use App\Services\MarketplaceManager\AliexpressInventorySyncService;
use App\Services\MarketplaceManager\MarketplaceListingQtyMatchService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\FaireInventorySyncService;
use App\Services\MarketplaceManager\NeweggInventorySyncService;
use App\Services\MarketplaceManager\ReverbInventorySyncService;
use App\Services\MarketplaceManager\SheinInventorySyncService;
use App\Services\MarketplaceManager\Ebay3InventorySyncService;
use App\Services\MarketplaceManager\TikTok2InventorySyncService;
use App\Services\MarketplaceManager\TikTokInventorySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Fast-path: push SKUs from live Shopify → one marketplace.
 *
 * Deduped per marketplace: page refreshes / webhooks merge SKUs into a pending
 * set and at most one job waits on that channel's queue.
 *
 * Unique only until processing starts: with a lock held for the whole push,
 * the "still pending" re-dispatch at the end of handle() was silently dropped
 * and SKUs that arrived mid-push sat in cache until the next webhook.
 */
class PushLinkedSkuInventoryFromShopify implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MAX_SKU_ATTEMPTS = 3;

    /** Minutes to wait before attempt 2, 3, ... for a SKU whose push failed. */
    public const RETRY_BACKOFF_MINUTES = [5, 15, 45];

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 1800;

    /**
     * @param  array<int, string>  $skus  Optional seed list; pending cache is also drained in handle()
     */
    public function __construct(
        public array $skus = [],
        public ?int $availableHint = null,
        public ?string $inventoryItemId = null,
        public string $marketplace = 'reverb',
    ) {
        $this->marketplace = strtolower(trim($this->marketplace));
        $this->onQueue(MarketplaceManagerRegistry::queueFor($this->marketplace));
        $this->skus = self::normalizeSkus($skus);
    }

    /**
     * Merge SKUs into the pending set and ensure exactly one unique job is queued.
     *
     * @param  array<int, string>  $skus
     * @return int number of SKUs now pending for this marketplace
     */
    public static function enqueue(
        string $marketplace,
        array $skus,
        ?int $availableHint = null,
        ?string $inventoryItemId = null,
    ): int {
        $marketplace = strtolower(trim($marketplace));
        $skus = self::normalizeSkus($skus);
        if ($skus === [] && ! $inventoryItemId) {
            return 0;
        }

        $pending = self::mergePending($marketplace, $skus);

        try {
            // Unique per marketplace — second dispatch is dropped while lock held;
            // SKUs already sit in the pending cache for the running/queued job.
            static::dispatch([], $availableHint, $inventoryItemId, $marketplace);
        } catch (\Throwable $e) {
            Log::warning('PushLinkedSkuInventoryFromShopify: dispatch failed (SKUs kept pending)', [
                'marketplace' => $marketplace,
                'pending' => count($pending),
                'error' => $e->getMessage(),
            ]);
        }

        return count($pending);
    }

    /**
     * Fan-out to every marketplace with inventory_sync enabled (parallel queues).
     *
     * @param  array<int, string>  $skus
     * @return int number of marketplaces enqueued
     */
    public static function dispatchToEnabled(
        array $skus,
        ?int $availableHint = null,
        ?string $inventoryItemId = null,
    ): int {
        $enqueued = 0;
        foreach (MarketplaceManagerRegistry::slugs() as $slug) {
            $settings = MarketplaceSyncSettings::getFor($slug);
            if (! ($settings['inventory']['inventory_sync'] ?? false)) {
                continue;
            }
            if (self::enqueue($slug, $skus, $availableHint, $inventoryItemId) > 0 || $inventoryItemId) {
                $enqueued++;
            }
        }

        return $enqueued;
    }

    public function uniqueId(): string
    {
        // One SKU-push job per marketplace — never one job per page refresh / SKU batch.
        return 'mm-push-inv-'.$this->marketplace;
    }

    public function handle(
        ReverbInventorySyncService $reverb,
        AliexpressInventorySyncService $aliexpress,
        AlibabaInventorySyncService $alibaba,
        NeweggInventorySyncService $newegg,
        SheinInventorySyncService $shein,
        Ebay3InventorySyncService $ebay3,
        FaireInventorySyncService $faire,
        TikTok2InventorySyncService $tiktok2,
        TikTokInventorySyncService $tiktok,
    ): void {
        $skus = self::normalizeSkus(array_merge(
            $this->skus,
            self::pullPending($this->marketplace)
        ));

        if ($skus === []) {
            Log::info('PushLinkedSkuInventoryFromShopify: no SKUs pending', [
                'marketplace' => $this->marketplace,
                'inventory_item_id' => $this->inventoryItemId,
            ]);

            return;
        }

        if (! $this->inventorySyncEnabled($this->marketplace)) {
            Log::info('PushLinkedSkuInventoryFromShopify: inventory_sync off — skip', [
                'marketplace' => $this->marketplace,
                'sku_count' => count($skus),
            ]);

            return;
        }

        $service = match ($this->marketplace) {
            'reverb' => $reverb,
            'aliexpress' => $aliexpress,
            'alibaba' => $alibaba,
            'newegg' => $newegg,
            'shein' => $shein,
            'ebay1' => app(\App\Services\MarketplaceManager\Ebay1InventorySyncService::class),
            'ebay2' => app(\App\Services\MarketplaceManager\Ebay2InventorySyncService::class),
            'ebay3' => $ebay3,
            'faire' => $faire,
            'tiktok2' => $tiktok2,
            'tiktok' => $tiktok,
            'temu' => app(\App\Services\MarketplaceManager\TemuInventorySyncService::class),
            'temu2' => app(\App\Services\MarketplaceManager\Temu2InventorySyncService::class),
            'temu3' => app(\App\Services\MarketplaceManager\Temu3InventorySyncService::class),
            'topdawg' => app(\App\Services\MarketplaceManager\TopDawgInventorySyncService::class),
            'purchasingpower' => app(\App\Services\MarketplaceManager\PurchasingPowerInventorySyncService::class),
            'wayfair' => app(\App\Services\MarketplaceManager\WayfairInventorySyncService::class),
            'bestbuy' => app(\App\Services\MarketplaceManager\BestBuyInventorySyncService::class),
            'macy' => app(\App\Services\MarketplaceManager\MacyInventorySyncService::class),
            'doba' => app(\App\Services\MarketplaceManager\DobaInventorySyncService::class),
            'amazon' => app(\App\Services\MarketplaceManager\AmazonInventorySyncService::class),
            'pls' => app(\App\Services\MarketplaceManager\PlsInventorySyncService::class),
            'b5cb2b' => app(\App\Services\MarketplaceManager\B5cB2bInventorySyncService::class),
            default => null,
        };

        if ($service === null) {
            Log::warning('PushLinkedSkuInventoryFromShopify: unknown marketplace', [
                'marketplace' => $this->marketplace,
            ]);

            return;
        }

        try {
            $result = $service->syncSkusFromShopify($skus);
        } catch (\Throwable $e) {
            // Put SKUs back so a retry / next enqueue can pick them up.
            self::mergePending($this->marketplace, $skus);
            Log::error('PushLinkedSkuInventoryFromShopify: marketplace push failed', [
                'marketplace' => $this->marketplace,
                'sku_count' => count($skus),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        Log::info('PushLinkedSkuInventoryFromShopify: done', [
            'marketplace' => $this->marketplace,
            'sku_count' => count($skus),
            'available_hint' => $this->availableHint,
            'inventory_item_id' => $this->inventoryItemId,
            'result' => $result,
        ]);

        $this->scheduleRetryForFailedSkus($skus, is_array($result) ? $result : []);

        // SKUs may have arrived while we were pushing — schedule one more unique job.
        $stillPending = self::pendingCount($this->marketplace);
        if ($stillPending > 0) {
            static::dispatch([], null, null, $this->marketplace);
        }
    }

    /**
     * A SKU whose push failed is re-queued with backoff (5, 15, 45 min), at most
     * MAX_SKU_ATTEMPTS times. Only SKUs still out of tolerance are retried, so
     * a partially failed batch does not re-push the SKUs that succeeded.
     *
     * @param  list<string>  $skus
     * @param  array<string, mixed>  $result
     */
    protected function scheduleRetryForFailedSkus(array $skus, array $result): void
    {
        $failed = (int) ($result['failed'] ?? 0);
        $rateLimited = ! empty($result['rate_limited']);
        if ($failed <= 0 && ! $rateLimited) {
            self::forgetAttempts($this->marketplace, $skus);

            return;
        }

        try {
            $retry = app(MarketplaceListingQtyMatchService::class)->stillMismatched($this->marketplace, $skus);
        } catch (\Throwable $e) {
            $retry = $skus;
        }
        // Local channel qty may lag the push; when the check reports nothing but
        // the service did fail, retry the whole (small) batch.
        if ($retry === []) {
            $retry = $skus;
        }

        $attempts = self::attempts($this->marketplace);
        $succeeded = array_diff($skus, $retry);
        foreach ($succeeded as $sku) {
            unset($attempts[strtoupper($sku)]);
        }

        $byAttempt = [];
        $gaveUp = [];
        foreach ($retry as $sku) {
            $key = strtoupper($sku);
            $n = (int) ($attempts[$key] ?? 1);
            if ($n >= self::MAX_SKU_ATTEMPTS) {
                $gaveUp[] = $sku;
                unset($attempts[$key]);

                continue;
            }
            $attempts[$key] = $n + 1;
            $byAttempt[$n + 1][] = $sku;
        }
        self::storeAttempts($this->marketplace, $attempts);

        foreach ($byAttempt as $attempt => $list) {
            $minutes = self::RETRY_BACKOFF_MINUTES[$attempt - 2] ?? 45;
            if ($rateLimited) {
                $minutes = max($minutes, 30);
            }
            RetryLinkedSkuInventoryPush::dispatch($this->marketplace, array_values($list), $attempt)
                ->delay(now()->addMinutes($minutes));
        }

        Log::warning('PushLinkedSkuInventoryFromShopify: SKUs failed — retry scheduled', [
            'marketplace' => $this->marketplace,
            'failed_reported' => $failed,
            'rate_limited' => $rateLimited,
            'retry' => $byAttempt,
            'gave_up' => $gaveUp,
        ]);
    }

    protected static function attemptsKey(string $marketplace): string
    {
        return 'mm.sku_push.attempts.'.strtolower(trim($marketplace));
    }

    /**
     * @return array<string, int> upper SKU => attempts so far
     */
    protected static function attempts(string $marketplace): array
    {
        $map = Cache::get(self::attemptsKey($marketplace), []);

        return is_array($map) ? $map : [];
    }

    /**
     * @param  array<string, int>  $map
     */
    protected static function storeAttempts(string $marketplace, array $map): void
    {
        if ($map === []) {
            Cache::forget(self::attemptsKey($marketplace));

            return;
        }
        Cache::put(self::attemptsKey($marketplace), $map, now()->addHours(6));
    }

    /**
     * @param  list<string>  $skus
     */
    protected static function forgetAttempts(string $marketplace, array $skus): void
    {
        $map = self::attempts($marketplace);
        if ($map === []) {
            return;
        }
        foreach ($skus as $sku) {
            unset($map[strtoupper($sku)]);
        }
        self::storeAttempts($marketplace, $map);
    }

    protected function inventorySyncEnabled(string $marketplace): bool
    {
        $settings = MarketplaceSyncSettings::getFor($marketplace);

        return (bool) ($settings['inventory']['inventory_sync'] ?? false);
    }

    /**
     * @param  array<int, string>  $skus
     * @return list<string>
     */
    protected static function normalizeSkus(array $skus): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ), static fn ($s) => $s !== '')));
    }

    protected static function pendingKey(string $marketplace): string
    {
        return 'mm.sku_push.pending.'.strtolower(trim($marketplace));
    }

    /**
     * @param  array<int, string>  $skus
     * @return list<string>
     */
    protected static function mergePending(string $marketplace, array $skus): array
    {
        $key = self::pendingKey($marketplace);
        $existing = Cache::get($key, []);
        if (! is_array($existing)) {
            $existing = [];
        }
        $merged = self::normalizeSkus(array_merge($existing, $skus));
        Cache::put($key, $merged, now()->addHours(2));

        return $merged;
    }

    /**
     * @return list<string>
     */
    protected static function pullPending(string $marketplace): array
    {
        $key = self::pendingKey($marketplace);
        $pending = Cache::pull($key, []);

        return self::normalizeSkus(is_array($pending) ? $pending : []);
    }

    protected static function pendingCount(string $marketplace): int
    {
        $pending = Cache::get(self::pendingKey($marketplace), []);

        return is_array($pending) ? count(self::normalizeSkus($pending)) : 0;
    }
}
