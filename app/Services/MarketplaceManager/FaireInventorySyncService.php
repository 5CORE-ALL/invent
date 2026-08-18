<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\FaireMetric;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Services\FaireApiService;
use App\Services\ShopifyApiService;
use App\Support\Marketplace\MappingChannelCounts;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FaireInventorySyncService
{
    public function __construct(
        protected FaireApiService $faireApi,
        protected ShopifyApiService $shopifyApi
    ) {}

    /**
     * @param  array<int, string>  $skus
     * @param  array{store_url?: string, token?: string}|null  $shopifyConfig
     * @param  bool  $exactShopifyQty  When true (mismatch button / mismatch pass), push the
     *                                 listings Shopify qty with no percent or max cap.
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

        if (! $this->faireApi->isConfigured()) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'Faire API credentials missing.'];
        }

        $settings = MarketplaceSyncSettings::getFor('faire');
        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;

        // Include NBSP-normalized forms so live Shopify Admin lookup matches Admin SKUs.
        $fetchSkus = $skus;
        $wantedNorms = [];
        foreach ($skus as $sku) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '') {
                $wantedNorms[$norm] = true;
                if ($norm !== $sku) {
                    $fetchSkus[] = $norm;
                }
            }
        }
        $fetchSkus = array_values(array_unique($fetchSkus));

        $shopifyQty = app(ShopifyQtySource::class)->fetchQuantitiesForPush(
            $fetchSkus,
            fn (array $need) => $this->fetchLiveShopifyQuantities($need, $shopifyConfig)
        );

        if ($exactShopifyQty) {
            foreach (MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($fetchSkus) as $key => $qty) {
                $shopifyQty[$key] = (int) $qty;
            }
        }

        // Match Faire rows by normalized SKU, but do not also push hyphen
        // aliases (ND-58) when the exact requested SKU (ND 58) is already mapped.
        $exactMetricSkus = FaireMetric::query()
            ->whereIn('sku', $skus)
            ->pluck('sku')
            ->map(static fn ($s) => (string) $s)
            ->all();
        $exactSet = array_flip($exactMetricSkus);

        $metrics = FaireMetric::query()
            ->whereNotNull('product_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'product_id')
            ->get()
            ->filter(function (FaireMetric $metric) use ($wantedNorms, $skus, $exactSet) {
                $raw = (string) $metric->sku;
                if (in_array($raw, $skus, true) || isset($exactSet[$raw])) {
                    return true;
                }
                $norm = ShopifySku::normalizeSkuForShopifyLookup($raw);
                if ($norm === '' || ! isset($wantedNorms[$norm])) {
                    return false;
                }
                foreach ($skus as $requested) {
                    if (ShopifySku::normalizeSkuForShopifyLookup($requested) === $norm
                        && isset($exactSet[$requested])) {
                        return false;
                    }
                }

                return true;
            })
            ->values();

        $inventoryRows = [];
        $skipped = 0;

        foreach ($metrics as $metric) {
            $sku = (string) $metric->sku;
            $productId = (string) $metric->product_id;
            if (! MarketplaceLiveInventoryRules::isLinked($productId, $sku)) {
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
            $pushQty = $shopifyStock === null
                ? MarketplaceLiveInventoryRules::qtyWhenMissingFromShopify()
                : ($exactShopifyQty
                    ? MarketplaceLiveInventoryRules::pushQtyFromLiveShopify($shopifyStock)
                    : MarketplaceLiveInventoryRules::qtyFromLiveShopify($shopifyStock, $qtyPercent, $maxQty));

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
                'failed' => count($skus),
                'skipped' => $skipped,
                'message' => 'No linked Faire SKUs found for inventory sync.',
            ];
        }

        $invResult = $this->pushInventoryRows($inventoryRows);
        $pushed = (int) ($invResult['pushed'] ?? 0);
        $failed = (int) ($invResult['failed'] ?? ($pushed > 0 ? 0 : count($inventoryRows)));
        $updatedSkus = $invResult['updated_skus'] ?? [];

        if ($pushed > 0) {
            $this->persistFaireMetricInventory($inventoryRows, $updatedSkus);
            $this->updateLocalPlatformQuantities($inventoryRows);
            $this->clearListingCaches();

            return [
                'updated' => $pushed,
                'failed' => $failed,
                'skipped' => $skipped,
                'message' => $invResult['message'] ?? ('Synced '.$pushed.' SKU(s) to Faire from live Shopify.'),
            ];
        }

        $this->updateLocalPlatformQuantities($inventoryRows, false);

        return [
            'updated' => 0,
            'failed' => $failed > 0 ? $failed : count($inventoryRows),
            'skipped' => $skipped,
            'message' => $invResult['message'] ?? 'Faire inventory update failed.',
        ];
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, price_updated: int, message: string}
     */
    public function syncFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('faire');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'Inventory and price sync are disabled in settings.',
            ];
        }

        if (! $this->faireApi->isConfigured()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'Faire API credentials missing.',
            ];
        }

        if (! Schema::hasTable('faire_metric')) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'faire_metric table missing. Run Sync link map on Listings first.',
            ];
        }

        $metrics = FaireMetric::query()
            ->whereNotNull('sku')
            ->whereNotNull('product_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'product_id')
            ->get();

        if ($metrics->isEmpty()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'No Faire SKU mappings found. Run Sync link map on Listings first.',
            ];
        }

        $skus = $metrics->pluck('sku')->unique()->values()->all();
        Log::info('FaireInventorySyncService: fetching live Shopify inventory', ['sku_count' => count($skus)]);
        $shopifyQty = $this->shopifyApi->getInventoryQuantitiesBySku($skus);

        $missing = [];
        foreach ($skus as $sku) {
            if ($this->resolveShopifyQty($shopifyQty, (string) $sku) === null) {
                $missing[] = (string) $sku;
            }
        }
        if ($missing !== []) {
            Log::info('FaireInventorySyncService: live variant fallback for missing SKUs', ['count' => count($missing)]);
            foreach ($this->fetchLiveShopifyQuantities($missing) as $sku => $qty) {
                $shopifyQty[$sku] = $qty;
            }
        }

        $coverage = MarketplaceLiveInventoryRules::shopifyLiveCoverageReport(
            $skus,
            fn (string $sku) => $this->resolveShopifyQty($shopifyQty, $sku)
        );
        Log::info('FaireInventorySyncService: Shopify live coverage', $coverage);
        if (! $coverage['ok'] && ($settings['inventory']['inventory_sync'] ?? false) && ! $dryRun) {
            Log::error('FaireInventorySyncService: aborting inventory push — Shopify live coverage too low', $coverage);

            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => count($skus),
                'price_updated' => 0,
                'message' => $coverage['message'],
            ];
        }

        $shopifyDetails = ($settings['pricing']['price_sync'] ?? false)
            ? $this->shopifyApi->getProductDetailsBySkuMap($skus)
            : [];

        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;
        $useSalePrice = (bool) ($settings['pricing']['use_sale_price'] ?? false);

        $inventoryRows = [];
        $priceRows = [];
        $skipped = 0;

        foreach ($metrics as $metric) {
            $sku = (string) $metric->sku;
            $productId = (string) $metric->product_id;
            if (! MarketplaceLiveInventoryRules::isLinked($productId, $sku)) {
                $skipped++;
                continue;
            }

            if ($settings['inventory']['inventory_sync'] ?? false) {
                $shopifyStock = $this->resolveShopifyQty($shopifyQty, $sku);
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

            if ($settings['pricing']['price_sync'] ?? false) {
                $detail = $shopifyDetails[$sku] ?? null;
                $price = null;
                if (is_array($detail)) {
                    $price = $useSalePrice
                        ? ($detail['price'] ?? $detail['sale_price'] ?? null)
                        : ($detail['compare_at_price'] ?? $detail['price'] ?? null);
                }
                $price = $this->applyPriceAdjustment((float) ($price ?? 0), $settings['pricing'] ?? []);
                if ($price > 0) {
                    $priceRows[] = [
                        'product_id' => $productId,
                        'sku_code' => $sku,
                        'price' => $price,
                    ];
                }
            }
        }

        if ($dryRun) {
            return [
                'updated' => count($inventoryRows),
                'failed' => 0,
                'skipped' => $skipped,
                'price_updated' => count($priceRows),
                'message' => '[dry-run] Would update '.count($inventoryRows).' inventory row(s), '.count($priceRows).' price row(s).',
            ];
        }

        $updated = 0;
        $failed = 0;
        $priceUpdated = 0;

        if ($inventoryRows !== []) {
            $invResult = $this->pushInventoryRows($inventoryRows);
            $updated = (int) ($invResult['pushed'] ?? 0);
            $failed = (int) ($invResult['failed'] ?? 0);
            if ($updated > 0) {
                $this->persistFaireMetricInventory($inventoryRows, $invResult['updated_skus'] ?? []);
                $this->updateLocalPlatformQuantities($inventoryRows);
                $this->clearListingCaches();
            } elseif ($failed > 0) {
                Log::warning('FaireInventorySyncService: inventory push failed', $invResult);
            }
        }

        if ($priceRows !== []) {
            $bulk = $this->faireApi->updateItemPriceBulk(array_map(static fn ($r) => [
                'seller_part_number' => $r['sku_code'],
                'price' => $r['price'],
            ], $priceRows), 'USA');
            $priceUpdated = (int) ($bulk['pushed'] ?? 0);
            if ($priceUpdated > 0) {
                // Update faire_metric.price only when a Faire price API push exists.
                $this->updateLocalMetricPrices($priceRows);
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => $skipped,
            'price_updated' => $priceUpdated,
            'message' => "Updated {$updated} inventory, {$priceUpdated} price(s); failed {$failed}; skipped {$skipped}."
                .$this->appendMismatchPass(!$dryRun && ($settings['inventory']['inventory_sync'] ?? false)),
        ];
    }

    protected function appendMismatchPass(bool $run): string
    {
        if (! $run) {
            return '';
        }

        $pass = app(MarketplaceMismatchInventoryPass::class)->run('faire');

        return ' '.$pass['message'];
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $inventoryRows
     * @return array{success: bool, pushed: int, failed: int, message?: string, updated_skus?: list<string>}
     */
    protected function pushInventoryRows(array $inventoryRows): array
    {
        $items = [];
        foreach ($inventoryRows as $row) {
            $items[] = [
                'seller_part_number' => (string) $row['sku_code'],
                'quantity' => (int) $row['inventory'],
                'product_id' => (string) ($row['product_id'] ?? ''),
            ];
        }

        $bulk = $this->faireApi->updateItemInventoryBulk($items);

        return [
            'success' => ($bulk['pushed'] ?? 0) > 0 || ! empty($bulk['success']),
            'pushed' => (int) ($bulk['pushed'] ?? 0),
            'failed' => (int) ($bulk['failed'] ?? 0),
            'message' => $bulk['error_message'] ?? $bulk['message'] ?? null,
            'updated_skus' => $bulk['updated_skus'] ?? [],
        ];
    }

    /**
     * @param  array<int, string>  $skus
     * @param  array{store_url?: string, token?: string}|null  $shopifyConfig
     * @return array<string, int>
     */
    protected function fetchLiveShopifyQuantities(array $skus, ?array $shopifyConfig = null): array
    {
        try {
            if ($shopifyConfig) {
                return $this->shopifyApi->getInventoryQuantitiesBySku($skus, $shopifyConfig);
            }

            return $this->shopifyApi->getInventoryQuantitiesBySku($skus);
        } catch (\Throwable $e) {
            Log::warning('FaireInventorySyncService: live Shopify fetch failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<string, int>  $shopifyQty
     */
    protected function resolveShopifyQty(array $shopifyQty, string $sku): ?int
    {
        if (array_key_exists($sku, $shopifyQty)) {
            return (int) $shopifyQty[$sku];
        }

        $needle = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($needle !== '') {
            foreach ($shopifyQty as $key => $qty) {
                if (ShopifySku::normalizeSkuForShopifyLookup((string) $key) === $needle) {
                    return (int) $qty;
                }
            }
        }

        $needleUpper = strtoupper(trim($sku));
        foreach ($shopifyQty as $key => $qty) {
            if (strtoupper(trim((string) $key)) === $needleUpper) {
                return (int) $qty;
            }
        }

        return null;
    }

    /**
     * After Faire accepts a push, store that qty as Faire listings stock.
     *
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $rows
     * @param  list<string>  $updatedSkus
     */
    protected function persistFaireMetricInventory(array $rows, array $updatedSkus = []): void
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
            FaireMetric::query()->where('sku', $sku)->update([
                'inventory' => max(0, (int) $row['inventory']),
            ]);
        }
    }

    protected function clearListingCaches(): void
    {
        try {
            app(FaireLiveListingsService::class)->clearCache();
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            Cache::forget(MarketplaceListingQtyMatchService::CACHE_PREFIX.'faire');
            MappingChannelCounts::forgetMasterCaches();
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @deprecated Live Faire stock is written by FaireLinkMapSyncService / persistFaireMetricInventory.
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $rows
     */
    protected function updateLocalStock(array $rows): void
    {
        $this->persistFaireMetricInventory($rows);
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $rows
     */
    protected function updateLocalPlatformQuantities(array $rows, bool $updateNeStock = true): void
    {
        foreach ($rows as $row) {
            $sku = trim((string) $row['sku_code']);
            if ($sku === '') {
                continue;
            }

            $neQty = max(0, (int) $row['inventory']);
            $shopifyQty = array_key_exists('shopify_qty', $row) ? (int) $row['shopify_qty'] : $neQty;

            // Never overwrite shopify_skus.available_to_sell — owned by SyncShopifyLiveInventory.

            if (Schema::hasTable('product_stock_mappings')) {
                $payload = ['inventory_shopify' => $shopifyQty];
                if ($updateNeStock && Schema::hasColumn('product_stock_mappings', 'inventory_faire')) {
                    $payload['inventory_faire'] = $neQty;
                }
                ProductStockMapping::query()
                    ->where(function ($q) use ($sku) {
                        $q->where('sku', $sku)->orWhere('sku', strtoupper($sku));
                    })
                    ->update($payload);
            }
        }
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, price: float|string}>  $rows
     */
    protected function updateLocalMetricPrices(array $rows): void
    {
        foreach ($rows as $row) {
            $sku = trim((string) $row['sku_code']);
            if ($sku === '') {
                continue;
            }
            FaireMetric::query()->where('sku', $sku)->update(['price' => (float) $row['price']]);
        }
    }

    /**
     * @deprecated Pricing cache is Faire API–only (link-map).
     * @param  array<int, array{product_id: string, sku_code: string, price: float|string}>  $rows
     */
    protected function updateLocalPrices(array $rows): void
    {
        $this->updateLocalMetricPrices($rows);
    }

    /**
     * @param  array<string, mixed>  $pricing
     */
    protected function applyPriceAdjustment(float $price, array $pricing): float
    {
        if ($price <= 0) {
            return 0.0;
        }

        $value = (float) ($pricing['adjustment_value'] ?? 0);
        if ($value == 0.0) {
            return round($price, 2);
        }

        $method = (string) ($pricing['adjustment_method'] ?? 'percent');
        $type = (string) ($pricing['adjustment_type'] ?? 'increase');
        $delta = $method === 'fixed' ? $value : ($price * $value / 100);
        if ($type === 'decrease') {
            $price -= $delta;
        } else {
            $price += $delta;
        }

        return round(max(0, $price), 2);
    }
}
