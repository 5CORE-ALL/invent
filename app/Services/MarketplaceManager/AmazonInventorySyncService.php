<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonListingStatus;
use App\Models\MarketplaceSyncSettings;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Services\AmazonSpApiService;
use App\Services\ShopifyApiService;
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
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncSkusFromShopify(array $skus, ?array $shopifyConfig = null): array
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

        $metrics = AmazonListingStatus::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get()
            ->filter(function (AmazonListingStatus $metric) use ($wantedNorms, $skus) {
                if (! AmazonListingStatusHelper::isLinked($metric)) {
                    return false;
                }
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

            // Match AliExpress: missing/0 Shopify => push 0 (do not skip and leave stale Amazon qty).
            $qty = $shopifyStock === null
                ? MarketplaceLiveInventoryRules::qtyWhenMissingFromShopify()
                : MarketplaceLiveInventoryRules::qtyFromLiveShopify($shopifyStock, $qtyPercent, $maxQty);

            $inventoryRows[] = [
                'product_id' => $productId,
                'sku_code' => $sku,
                'inventory' => $qty,
                'shopify_qty' => $shopifyStock ?? 0,
            ];
        }

        if ($inventoryRows === []) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => $skipped + max(0, count($skus) - $metrics->count()),
                'message' => 'No linked Amazon SKUs found for inventory sync.',
            ];
        }

        $invResult = $this->pushInventoryRows($inventoryRows);
        $this->persistLocalStock($inventoryRows);

        return [
            'updated' => (int) ($invResult['pushed'] ?? 0),
            'failed' => (int) ($invResult['failed'] ?? 0),
            'skipped' => $skipped,
            'message' => $invResult['message']
                ?? ('Pushed '.count($inventoryRows).' inventory row(s) to Amazon (local + SP-API best effort).'),
        ];
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncAllFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('amazon');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'Inventory/price sync disabled in Amazon settings.',
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

        $result = $this->syncSkusFromShopify($skus);
        $pass = app(MarketplaceMismatchInventoryPass::class)->run('amazon');
        $result['message'] = ($result['message'] ?? '').' '.($pass['message'] ?? '');

        return $result;
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $inventoryRows
     * @return array{success: bool, pushed: int, failed: int, message?: string}
     */
    protected function pushInventoryRows(array $inventoryRows): array
    {
        $pushed = 0;
        $failed = 0;
        $errors = [];

        foreach ($inventoryRows as $row) {
            $sku = (string) $row['sku_code'];
            $qty = (int) $row['inventory'];
            $result = $this->amazonApi->updateInventoryBySku($sku, $qty);
            if (! empty($result['success'])) {
                $pushed++;
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
            'message' => $errors !== []
                ? 'SP-API: '.implode('; ', array_slice($errors, 0, 3))
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

        // Fallback to local Shopify SoT used by mismatch UI when Admin API misses/fails.
        $local = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($skus);
        foreach ($skus as $sku) {
            if ($this->resolveShopifyQty($live, $sku) !== null) {
                continue;
            }
            $qty = $this->resolveShopifyQty($local, $sku);
            if ($qty === null) {
                continue;
            }
            $live[strtoupper(trim($sku))] = $qty;
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
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $rows
     */
    protected function persistLocalStock(array $rows): void
    {
        foreach ($rows as $row) {
            $sku = (string) $row['sku_code'];
            $qty = (int) $row['inventory'];

            if (Schema::hasTable('product_stock_mappings')
                && Schema::hasColumn('product_stock_mappings', 'inventory_amazon')) {
                ProductStockMapping::query()
                    ->where('sku', $sku)
                    ->update(['inventory_amazon' => $qty]);
            }
        }
    }
}
