<?php

namespace App\Services\MarketplaceManager;

use App\Models\EbayMetric;
use App\Models\MarketplaceSyncSettings;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Services\EbayApiService;
use App\Services\ShopifyApiService;
use App\Support\Marketplace\MappingChannelCounts;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Ebay1InventorySyncService
{
    public function __construct(
        protected EbayApiService $ebay1Api,
        protected ShopifyApiService $shopifyApi
    ) {}

    /**
     * @param  array<int, string>  $skus
     * @param  array{store_url?: string, token?: string}|null  $shopifyConfig
     * @param  bool  $exactShopifyQty  When true (mismatch button / mismatch pass), push listings
     *                                 Shopify qty with no percent/max cap, qty-only (no price).
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

        if (! $this->ebay1Api->isConfigured()) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'eBay 1 API credentials missing.'];
        }

        $settings = MarketplaceSyncSettings::getFor('ebay1');
        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;

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

        $metrics = EbayMetric::query()
            ->whereNotNull('item_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'item_id')
            ->where(function ($q) use ($skus, $wantedNorms) {
                $q->whereIn('sku', $skus);
                $normSkus = array_keys($wantedNorms);
                if ($normSkus !== []) {
                    // Normalized lookup covers hyphen/case variants stored differently than requested SKU.
                    $q->orWhereIn('sku', $normSkus);
                }
            })
            ->get()
            ->filter(function (EbayMetric $metric) use ($wantedNorms, $skus) {
                $raw = (string) $metric->sku;
                if (in_array($raw, $skus, true)) {
                    return true;
                }
                $norm = ShopifySku::normalizeSkuForShopifyLookup($raw);

                return $norm !== '' && isset($wantedNorms[$norm]);
            })
            ->values();

        $inventoryRows = [];
        $skipped = 0;

        foreach ($metrics as $metric) {
            $sku = (string) $metric->sku;
            $itemId = (string) $metric->item_id;
            if (! MarketplaceLiveInventoryRules::isLinked($itemId, $sku)) {
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
                'product_id' => $itemId,
                'sku_code' => $sku,
                'inventory' => MarketplaceLiveInventoryRules::clampPushQty($pushQty, $shopifyStock ?? 0),
                'shopify_qty' => $shopifyStock ?? 0,
                // Mismatch button is qty-only; sending StartPrice on variation listings often fails the whole call.
                'price' => $exactShopifyQty ? null : ($metric->ebay_price !== null ? (float) $metric->ebay_price : null),
            ];
        }

        if ($inventoryRows === []) {
            return [
                'updated' => 0,
                'failed' => count($skus),
                'skipped' => $skipped,
                'message' => 'No linked eBay 1 SKUs found for inventory sync.',
            ];
        }

        $invResult = $this->pushInventoryRows($inventoryRows);
        $pushed = (int) ($invResult['pushed'] ?? 0);
        $failed = (int) ($invResult['failed'] ?? 0);
        $updatedSkus = $invResult['updated_skus'] ?? [];

        if ($pushed > 0) {
            $this->updateLocalStock($inventoryRows, $updatedSkus);
            $this->updateLocalPlatformQuantities($inventoryRows, true, $updatedSkus);
            $this->clearListingCaches();

            return [
                'updated' => $pushed,
                'failed' => $failed,
                'skipped' => $skipped,
                'message' => 'Synced '.$pushed.' SKU(s) to eBay 1 from live Shopify.',
            ];
        }

        $this->updateLocalPlatformQuantities($inventoryRows, false);

        return [
            'updated' => $pushed,
            'failed' => $failed > 0 ? $failed : count($inventoryRows),
            'skipped' => $skipped,
            'message' => $invResult['message'] ?? 'eBay 1 inventory update failed.',
        ];
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, price_updated: int, message: string}
     */
    public function syncFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('ebay1');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'Inventory and price sync are disabled in settings.',
            ];
        }

        if (! $this->ebay1Api->isConfigured()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'eBay 1 API credentials missing.',
            ];
        }

        if (! Schema::hasTable('ebay_metrics')) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'ebay_metrics table missing. Run Sync link map on Listings first.',
            ];
        }

        $metrics = EbayMetric::query()
            ->whereNotNull('sku')
            ->whereNotNull('item_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'item_id')
            ->get();

        if ($metrics->isEmpty()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'No eBay 1 SKU mappings found. Run Sync link map on Listings first.',
            ];
        }

        $skus = $metrics->pluck('sku')->unique()->values()->all();
        Log::info('Ebay1InventorySyncService: fetching live Shopify inventory', ['sku_count' => count($skus)]);
        $shopifyQty = $this->shopifyApi->getInventoryQuantitiesBySku($skus);

        $missing = [];
        foreach ($skus as $sku) {
            if ($this->resolveShopifyQty($shopifyQty, (string) $sku) === null) {
                $missing[] = (string) $sku;
            }
        }
        if ($missing !== []) {
            Log::info('Ebay1InventorySyncService: live variant fallback for missing SKUs', ['count' => count($missing)]);
            foreach ($this->fetchLiveShopifyQuantities($missing) as $sku => $qty) {
                $shopifyQty[$sku] = $qty;
            }
        }

        $coverage = MarketplaceLiveInventoryRules::shopifyLiveCoverageReport(
            $skus,
            fn (string $sku) => $this->resolveShopifyQty($shopifyQty, $sku)
        );
        Log::info('Ebay1InventorySyncService: Shopify live coverage', $coverage);
        if (! $coverage['ok'] && ($settings['inventory']['inventory_sync'] ?? false) && ! $dryRun) {
            Log::error('Ebay1InventorySyncService: aborting inventory push — Shopify live coverage too low', $coverage);

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
            $itemId = (string) $metric->item_id;
            if (! MarketplaceLiveInventoryRules::isLinked($itemId, $sku)) {
                $skipped++;
                continue;
            }

            $shopifyStock = $this->resolveShopifyQty($shopifyQty, $sku);
            $pushQty = null;
            if ($settings['inventory']['inventory_sync'] ?? false) {
                $pushQty = $shopifyStock === null
                    ? MarketplaceLiveInventoryRules::qtyWhenMissingFromShopify()
                    : MarketplaceLiveInventoryRules::qtyFromLiveShopify($shopifyStock, $qtyPercent, $maxQty);
            }

            $price = null;
            if ($settings['pricing']['price_sync'] ?? false) {
                $detail = $shopifyDetails[$sku] ?? null;
                if (is_array($detail)) {
                    $price = $useSalePrice
                        ? ($detail['price'] ?? $detail['sale_price'] ?? null)
                        : ($detail['compare_at_price'] ?? $detail['price'] ?? null);
                }
                $price = $this->applyPriceAdjustment((float) ($price ?? 0), $settings['pricing'] ?? []);
                if ($price <= 0) {
                    $price = $metric->ebay_price !== null ? (float) $metric->ebay_price : null;
                }
            }

            if ($pushQty !== null) {
                $inventoryRows[] = [
                    'product_id' => $itemId,
                    'sku_code' => $sku,
                    'inventory' => MarketplaceLiveInventoryRules::clampPushQty($pushQty, $shopifyStock ?? 0),
                    'shopify_qty' => $shopifyStock ?? 0,
                    'price' => $price,
                ];
            } elseif ($price !== null && $price > 0) {
                $priceRows[] = [
                    'product_id' => $itemId,
                    'sku_code' => $sku,
                    'price' => $price,
                ];
            }
        }

        if ($dryRun) {
            return [
                'updated' => count($inventoryRows),
                'failed' => 0,
                'skipped' => $skipped,
                'price_updated' => count($priceRows),
                'message' => '[dry-run] Would update '.count($inventoryRows).' inventory row(s), '.count($priceRows).' price-only row(s).',
            ];
        }

        $updated = 0;
        $failed = 0;
        $priceUpdated = 0;

        if ($inventoryRows !== []) {
            $invResult = $this->pushInventoryRows($inventoryRows);
            $updated = (int) ($invResult['pushed'] ?? 0);
            $failed = (int) ($invResult['failed'] ?? 0);
            $updatedSkus = $invResult['updated_skus'] ?? [];
            if ($updated > 0) {
                $this->updateLocalStock($inventoryRows, $updatedSkus);
                $this->updateLocalPlatformQuantities($inventoryRows, true, $updatedSkus);
                $this->clearListingCaches();
            } elseif ($failed > 0) {
                Log::warning('Ebay1InventorySyncService: inventory push failed', $invResult);
            }
        }

        if ($priceRows !== []) {
            foreach ($priceRows as $row) {
                $result = $this->ebay1Api->reviseFixedPriceItem(
                    (string) $row['product_id'],
                    (float) $row['price'],
                    null,
                    (string) $row['sku_code']
                );
                if (! empty($result['success'])) {
                    $priceUpdated++;
                }
            }
            if ($priceUpdated > 0) {
                $this->updateLocalPrices($priceRows);
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => $skipped,
            'price_updated' => $priceUpdated,
            'message' => "Updated {$updated} inventory, {$priceUpdated} price(s); failed {$failed}; skipped {$skipped}."
                .$this->appendMismatchPass(! $dryRun && ($settings['inventory']['inventory_sync'] ?? false)),
        ];
    }

    protected function appendMismatchPass(bool $run): string
    {
        if (! $run) {
            return '';
        }

        $pass = app(MarketplaceMismatchInventoryPass::class)->run('ebay1');

        return ' '.$pass['message'];
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int, price?: float|null}>  $inventoryRows
     * @return array{success: bool, pushed: int, failed: int, updated_skus: list<string>, message?: string}
     */
    protected function pushInventoryRows(array $inventoryRows): array
    {
        $pushed = 0;
        $failed = 0;
        $updatedSkus = [];
        $lastMessage = null;

        foreach ($inventoryRows as $row) {
            $one = $this->pushOneInventoryRow($row);
            if (! empty($one['ok'])) {
                $pushed++;
                $sku = trim((string) ($row['sku_code'] ?? ''));
                if ($sku !== '') {
                    $updatedSkus[] = $sku;
                }
            } else {
                $failed++;
                $lastMessage = $one['message'] ?? 'ReviseInventoryStatus failed';
            }
        }

        return [
            'success' => $pushed > 0,
            'pushed' => $pushed,
            'failed' => $failed,
            'updated_skus' => $updatedSkus,
            'message' => $lastMessage,
        ];
    }

    /**
     * @param  array{product_id: string, sku_code: string, inventory: int, price?: float|null}  $row
     * @return array{ok: bool, message?: string}
     */
    protected function pushOneInventoryRow(array $row): array
    {
        $itemId = trim((string) ($row['product_id'] ?? ''));
        $sku = trim((string) ($row['sku_code'] ?? ''));
        if ($itemId === '' || $sku === '') {
            return ['ok' => false, 'message' => 'ItemID and SKU are required.'];
        }

        $qty = max(0, (int) ($row['inventory'] ?? 0));
        $price = $row['price'] ?? null;
        $price = ($price !== null && (float) $price > 0) ? (float) $price : null;

        try {
            $result = $this->ebay1Api->reviseInventoryStatus($itemId, $qty, $sku, $price);
            $msg = (string) ($result['message'] ?? '');

            if (empty($result['success']) || (isset($result['quantity_confirmed']) && $result['quantity_confirmed'] === false)) {
                $fallback = $this->ebay1Api->reviseVariationQuantity($itemId, $sku, $qty);
                if (! empty($fallback['success'])) {
                    $result = $fallback;
                } elseif (empty($result['success'])) {
                    $result = $fallback;
                    $msg = (string) ($fallback['message'] ?? $msg);
                } else {
                    $msg = (string) ($fallback['message'] ?? $msg);

                    return [
                        'ok' => false,
                        'message' => $msg !== '' ? $msg : 'eBay did not confirm the new quantity.',
                    ];
                }
            }

            if (! empty($result['success'])) {
                return ['ok' => true, 'message' => (string) ($result['message'] ?? '')];
            }

            Log::warning('Ebay1InventorySyncService: revise inventory failed', [
                'item_id' => $itemId,
                'sku' => $sku,
                'qty' => $qty,
                'result' => $result,
            ]);

            return ['ok' => false, 'message' => $msg !== '' ? $msg : 'ReviseInventoryStatus failed'];
        } catch (\Throwable $e) {
            Log::warning('Ebay1InventorySyncService: revise inventory exception', [
                'item_id' => $itemId,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
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
            Log::warning('Ebay1InventorySyncService: live Shopify fetch failed', ['error' => $e->getMessage()]);

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
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $rows
     * @param  list<string>  $updatedSkus
     */
    protected function updateLocalStock(array $rows, array $updatedSkus = []): void
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
            $itemId = trim((string) $row['product_id']);
            if ($sku === '') {
                continue;
            }
            if ($allow !== [] && ! isset($allow[strtoupper($sku)])) {
                continue;
            }
            $qty = (int) $row['inventory'];

            EbayMetric::query()
                ->where('sku', $sku)
                ->when($itemId !== '', fn ($q) => $q->where('item_id', $itemId))
                ->update(['ebay_stock' => $qty]);
        }
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $rows
     * @param  list<string>  $updatedSkus
     */
    protected function updateLocalPlatformQuantities(array $rows, bool $updateEbayStock = true, array $updatedSkus = []): void
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

            $ebayQty = (int) $row['inventory'];
            $shopifyQty = array_key_exists('shopify_qty', $row) ? (int) $row['shopify_qty'] : $ebayQty;

            if (Schema::hasTable('product_stock_mappings')) {
                $payload = ['inventory_shopify' => $shopifyQty];
                if ($updateEbayStock && Schema::hasColumn('product_stock_mappings', 'inventory_ebay1')) {
                    $payload['inventory_ebay1'] = $ebayQty;
                }
                ProductStockMapping::query()
                    ->where(function ($q) use ($sku) {
                        $q->where('sku', $sku)->orWhere('sku', strtoupper($sku));
                    })
                    ->update($payload);
            }
        }
    }

    protected function clearListingCaches(): void
    {
        try {
            app(Ebay1LiveListingsService::class)->clearCache();
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            Cache::forget(MarketplaceListingQtyMatchService::CACHE_PREFIX.'ebay1');
            MappingChannelCounts::forgetMasterCaches();
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, price: float|string}>  $rows
     */
    protected function updateLocalPrices(array $rows): void
    {
        foreach ($rows as $row) {
            $sku = (string) $row['sku_code'];
            EbayMetric::query()->where('sku', $sku)->update(['ebay_price' => (float) $row['price']]);
        }
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
