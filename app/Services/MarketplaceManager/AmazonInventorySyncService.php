<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonListingStatus;
use App\Models\MarketplaceSyncSettings;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Services\AmazonSpApiService;
use App\Services\ShopifyApiService;
use App\Support\Marketplace\MappingChannelCounts;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AmazonInventorySyncService
{
    public function __construct(
        protected AmazonSpApiService $amazonApi,
        protected ShopifyApiService $shopifyApi
    ) {}

    public function syncFromShopify(bool $dryRun = false): array
    {
        return $this->syncAllFromShopify($dryRun);
    }

    /**
     * @param  array<int, string>  $skus
     * @param  array{store_url?: string, token?: string}|null  $shopifyConfig
     * @param  bool  $exactShopifyQty  When true (mismatch button), push listings Shopify qty with no percent/max cap.
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncSkusFromShopify(array $skus, ?array $shopifyConfig = null, bool $exactShopifyQty = false): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($sku) => trim((string) $sku),
            $skus
        ), static fn ($sku) => $sku !== '' && ! in_array($sku, ['__order__', '__unknown__'], true))));

        if ($skus === []) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'No SKUs to sync.'];
        }

        if (! $this->amazonApi->isConfigured()) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'Amazon SP-API credentials missing.'];
        }

        $settings = MarketplaceSyncSettings::getFor('amazon');
        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;

        $fetchSkus = $skus;
        foreach ($skus as $sku) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '' && $norm !== $sku) {
                $fetchSkus[] = $norm;
            }
        }
        $fetchSkus = array_values(array_unique($fetchSkus));

        $shopifyQty = app(ShopifyQtySource::class)->fetchQuantitiesForPush(
            $fetchSkus,
            fn (array $need) => $this->fetchLiveShopifyQuantities($need, $shopifyConfig)
        );
        $shopifyQty = MarketplaceLiveInventoryRules::applyConfirmedLocalZeros($shopifyQty, $fetchSkus);

        $coverage = MarketplaceLiveInventoryRules::shopifyLiveCoverageReport(
            $skus,
            fn (string $sku) => $this->resolveShopifyQty($shopifyQty, $sku)
        );
        Log::info('AmazonInventorySyncService: Shopify live coverage', $coverage);

        if ($exactShopifyQty) {
            foreach (MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($fetchSkus) as $key => $qty) {
                $shopifyQty[$key] = (int) $qty;
            }
        }

        $statusMap = AmazonListingStatusHelper::mapForSkus($skus);
        $metrics = [];
        $seen = [];
        foreach ($statusMap as $metric) {
            if (! $metric instanceof AmazonListingStatus) {
                continue;
            }
            $raw = trim((string) $metric->sku);
            $key = strtoupper($raw);
            if ($raw === '' || isset($seen[$key]) || ! AmazonListingStatusHelper::isLinked($metric)) {
                continue;
            }
            $seen[$key] = true;
            $metrics[] = $metric;
        }

        $inventoryRows = [];
        $skipped = 0;

        foreach ($metrics as $metric) {
            $sku = (string) $metric->sku;
            $productId = AmazonListingStatusHelper::inventoryProductId($metric);
            if (! MarketplaceLiveInventoryRules::isLinked($productId, $sku)
                && ! AmazonListingStatusHelper::isLinked($metric, $sku)) {
                $skipped++;
                continue;
            }

            $shopifyStock = $this->resolveShopifyQty($shopifyQty, $sku);
            foreach ($skus as $requested) {
                if ($shopifyStock !== null) {
                    break;
                }
                if (ShopifySku::normalizeSkuForShopifyLookup($requested)
                    === ShopifySku::normalizeSkuForShopifyLookup($sku)) {
                    $shopifyStock = $this->resolveShopifyQty($shopifyQty, $requested);
                }
            }

            // Confirmed Shopify 0 must go to Amazon as 0. Never keep leftover marketplace stock.
            // Only skip unconfirmed misses when coverage is too low (avoid mass-zeroing).
            if ($shopifyStock === null && ! $coverage['ok'] && ! $exactShopifyQty) {
                $skipped++;
                continue;
            }
            if ($shopifyStock !== null && (int) $shopifyStock <= 0) {
                $shopifyStock = 0;
            }

            $pushQty = $shopifyStock === null
                ? MarketplaceLiveInventoryRules::qtyWhenMissingFromShopify()
                : MarketplaceLiveInventoryRules::qtyFromLiveShopify($shopifyStock, $qtyPercent, $maxQty);

            $inventoryRows[] = [
                'product_id' => $productId,
                'sku_code' => $sku,
                'inventory' => MarketplaceLiveInventoryRules::clampPushQty($pushQty, $shopifyStock ?? 0),
                'shopify_qty' => $shopifyStock ?? 0,
            ];
        }

        if ($inventoryRows === []) {
            return [
                'updated' => 0,
                'failed' => max(0, count($skus) - $skipped),
                'skipped' => $skipped,
                'message' => 'No linked Amazon SKUs found for inventory sync.',
            ];
        }

        $invResult = $this->pushInventoryRows($inventoryRows);
        $pushed = (int) ($invResult['pushed'] ?? 0);
        $failed = (int) ($invResult['failed'] ?? 0);
        $updatedSkus = $invResult['updated_skus'] ?? [];

        if ($pushed > 0) {
            $this->persistLocalStock($inventoryRows, $updatedSkus);
            $this->clearListingCaches();
        }

        return [
            'updated' => $pushed,
            'failed' => $failed,
            'skipped' => $skipped,
            'message' => $invResult['message']
                ?? ($pushed > 0
                    ? 'Synced '.$pushed.' SKU(s) to Amazon from live Shopify.'
                    : 'Amazon inventory update failed.'),
        ];
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncAllFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('amazon');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            $zeros = $this->syncConfirmedZerosFromShopify(400);

            return [
                'updated' => (int) ($zeros['updated'] ?? 0),
                'failed' => (int) ($zeros['failed'] ?? 0),
                'skipped' => (int) ($zeros['skipped'] ?? 0),
                'message' => 'Full inventory sync is Off; still flushed Shopify-zero SKUs to Amazon. '
                    .($zeros['message'] ?? ''),
            ];
        }

        if (! $this->amazonApi->isConfigured()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'Amazon SP-API credentials missing.',
            ];
        }

        if (! Schema::hasTable('amazon_listing_statuses')) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'amazon_listing_statuses table missing.',
            ];
        }

        $skus = AmazonListingStatus::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->pluck('sku')
            ->map(static fn ($s) => trim((string) $s))
            ->filter(static fn (string $sku) => $sku !== '')
            ->unique()
            ->values()
            ->all();

        $skus = array_values(array_filter($skus, static function (string $sku) {
            $row = AmazonListingStatus::query()->where('sku', $sku)->first();

            return $row && AmazonListingStatusHelper::isLinked($row);
        }));

        if ($skus === []) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'No linked Amazon SKUs. Run Sync Amazon link map first.',
            ];
        }

        if ($dryRun) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => '[dry-run] Would sync '.count($skus).' linked SKU(s).',
            ];
        }

        $zeros = $this->syncConfirmedZerosFromShopify(400);
        $result = $this->syncSkusFromShopify($skus);
        $pass = app(MarketplaceMismatchInventoryPass::class)->run('amazon');
        $result['updated'] = (int) ($result['updated'] ?? 0) + (int) ($zeros['updated'] ?? 0);
        $result['failed'] = (int) ($result['failed'] ?? 0) + (int) ($zeros['failed'] ?? 0);
        $result['message'] = trim(
            ($zeros['message'] ?? '').' '.($result['message'] ?? '').' '.($pass['message'] ?? '')
        );

        return $result;
    }

    /**
     * Safety flush: every linked Amazon SKU that is 0 on Shopify and still
     * positive on Amazon is pushed to 0. Independent of live-coverage abort.
     *
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncConfirmedZerosFromShopify(int $limit = 250): array
    {
        $empty = ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'No Amazon leftovers with Shopify 0.'];
        if (! $this->amazonApi->isConfigured() || ! Schema::hasTable('amazon_listing_statuses')) {
            return $empty;
        }

        $linked = AmazonListingStatusHelper::linkedSkus();
        if ($linked === []) {
            return $empty;
        }

        $shopify = MarketplaceLiveInventoryRules::applyConfirmedLocalZeros(
            [],
            $linked
        );
        $amazon = MarketplaceListingStockResolver::stockMapForSkus(
            MarketplaceListingStockResolver::CHANNEL_AMAZON,
            $linked
        );

        $zeros = [];
        foreach ($linked as $sku) {
            $shopifyQty = $this->resolveShopifyQty($shopify, $sku);
            if ($shopifyQty === null || (int) $shopifyQty > 0) {
                continue;
            }
            $amazonQty = $this->resolveShopifyQty($amazon, $sku);
            if ($amazonQty === null || (int) $amazonQty <= 0) {
                continue;
            }
            $zeros[] = $sku;
            if (count($zeros) >= max(1, $limit)) {
                break;
            }
        }

        if ($zeros === []) {
            return $empty;
        }

        Log::info('AmazonInventorySyncService: pushing confirmed Shopify zeros', ['count' => count($zeros)]);

        return $this->syncSkusFromShopify($zeros, null, true);
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $inventoryRows
     * @return array{success: bool, pushed: int, failed: int, message?: string, updated_skus: list<string>}
     */
    protected function pushInventoryRows(array $inventoryRows): array
    {
        $pushed = 0;
        $failed = 0;
        $errors = [];
        $updatedSkus = [];

        foreach ($inventoryRows as $row) {
            $sku = (string) $row['sku_code'];
            $qty = (int) $row['inventory'];
            $result = $this->amazonApi->updateInventoryBySku($sku, $qty);
            if (! empty($result['success'])) {
                $pushed++;
                $updatedSkus[] = $sku;
            } else {
                $failed++;
                if (! empty($result['message'])) {
                    $errors[] = $sku.': '.$result['message'];
                }
            }
        }

        return [
            'success' => $pushed > 0 || $failed === 0,
            'pushed' => $pushed,
            'failed' => $failed,
            'updated_skus' => $updatedSkus,
            'message' => $errors !== []
                ? 'SP-API: '.implode('; ', array_slice($errors, 0, 3)).($failed > 3 ? ' (+'.($failed - 3).' more)' : '')
                : null,
        ];
    }

    /**
     * @param  array<int, string>  $skus
     * @param  array{store_url?: string, token?: string}|null  $shopifyConfig
     * @return array<string, int>
     */
    protected function fetchLiveShopifyQuantities(array $skus, ?array $shopifyConfig = null): array
    {
        $live = [];
        try {
            // Signature is SKU list only; $shopifyConfig kept for call-site compatibility.
            unset($shopifyConfig);
            $live = $this->shopifyApi->getInventoryQuantitiesBySku($skus);
        } catch (\Throwable $e) {
            Log::warning('AmazonInventorySyncService: live Shopify fetch failed', ['error' => $e->getMessage()]);
            $live = [];
        }

        // Local fallback only for confirmed zeros. A stale positive must not keep Amazon in stock
        // after Shopify is already 0 and the Admin API missed the SKU.
        $local = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($skus);
        foreach ($skus as $sku) {
            if ($this->resolveShopifyQty($live, $sku) !== null) {
                continue;
            }
            $qty = $this->resolveShopifyQty($local, $sku);
            if ($qty === null || (int) $qty > 0) {
                continue;
            }
            $live[strtoupper(trim($sku))] = 0;
        }

        return $live;
    }

    /**
     * @param  array<string, int>  $map
     */
    protected function resolveShopifyQty(array $map, string $sku): ?int
    {
        $upper = strtoupper(trim($sku));
        if (array_key_exists($upper, $map)) {
            return (int) $map[$upper];
        }
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm !== '' && array_key_exists($norm, $map)) {
            return (int) $map[$norm];
        }

        return null;
    }

    /**
     * After Amazon accepts a push, store that qty as listings Amz Qty.
     *
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $rows
     * @param  list<string>  $updatedSkus
     */
    protected function persistLocalStock(array $rows, array $updatedSkus = []): void
    {
        $allow = [];
        foreach ($updatedSkus as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '') {
                $allow[strtoupper($sku)] = true;
            }
        }

        foreach ($rows as $row) {
            $sku = trim((string) $row['sku_code']);
            if ($sku === '') {
                continue;
            }
            if ($allow !== [] && ! isset($allow[strtoupper($sku)])) {
                continue;
            }
            $qty = max(0, (int) $row['inventory']);
            $upper = strtoupper($sku);

            if (Schema::hasTable('product_stock_mappings')
                && Schema::hasColumn('product_stock_mappings', 'inventory_amazon')) {
                ProductStockMapping::query()
                    ->where(function ($q) use ($sku, $upper) {
                        $q->where('sku', $sku)
                            ->orWhereRaw('UPPER(TRIM(sku)) = ?', [$upper]);
                    })
                    ->update(['inventory_amazon' => $qty]);
            }

            if (Schema::hasTable('amazon_listing_statuses')) {
                AmazonListingStatus::query()
                    ->where(function ($q) use ($sku, $upper) {
                        $q->where('sku', $sku)
                            ->orWhereRaw('UPPER(TRIM(sku)) = ?', [$upper]);
                    })
                    ->get()
                    ->each(function (AmazonListingStatus $status) use ($qty) {
                        $value = is_array($status->value) ? $status->value : [];
                        $value['quantity'] = $qty;
                        $status->value = $value;
                        $status->save();
                    });
            }

            if (Schema::hasTable('amazon_listings_raw') && Schema::hasColumn('amazon_listings_raw', 'quantity')) {
                DB::table('amazon_listings_raw')
                    ->where(function ($q) use ($sku, $upper) {
                        $q->where('seller_sku', $sku)
                            ->orWhereRaw('UPPER(TRIM(seller_sku)) = ?', [$upper]);
                    })
                    ->update(['quantity' => $qty]);
            }
        }
    }

    protected function clearListingCaches(): void
    {
        try {
            app(AmazonLiveListingsService::class)->clearCache();
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            Cache::forget(MarketplaceListingQtyMatchService::CACHE_PREFIX.'amazon');
            MappingChannelCounts::forgetMasterCaches();
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
