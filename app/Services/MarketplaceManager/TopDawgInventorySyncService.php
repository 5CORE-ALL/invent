<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Models\TopDawgProduct;
use App\Services\ShopifyApiService;
use App\Services\TopDawgApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TopDawgInventorySyncService
{
    public function __construct(
        protected TopDawgApiService $topdawgApi,
        protected ShopifyApiService $shopifyApi
    ) {}

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

        if (! $this->topdawgApi->isConfigured()) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'TopDawg API credentials missing.'];
        }

        $settings = MarketplaceSyncSettings::getFor('topdawg');
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

        $metrics = TopDawgProduct::query()
            ->whereNotNull('topdawg_listing_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'topdawg_listing_id')
            ->get()
            ->filter(function (TopDawgProduct $metric) use ($wantedNorms, $skus) {
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
            $productId = (string) $metric->topdawg_listing_id;
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
            if ($shopifyStock === null) {
                $skipped++;
                continue;
            }

            $qty = (int) floor($shopifyStock * ($qtyPercent / 100));
            if ($maxQty !== null && $maxQty !== '') {
                $qty = min($qty, (int) $maxQty);
            }
            $qty = max(0, $qty);

            $inventoryRows[] = [
                'product_id' => $productId,
                'sku_code' => $sku,
                'inventory' => $qty,
                'shopify_qty' => $shopifyStock,
            ];
        }

        if ($inventoryRows === []) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => $skipped,
                'message' => 'No linked TopDawg SKUs found for inventory sync.',
            ];
        }

        $invResult = $this->pushInventoryRows($inventoryRows);
        $this->persistLocalStock($inventoryRows);

        return [
            'updated' => (int) ($invResult['pushed'] ?? 0),
            'failed' => (int) ($invResult['failed'] ?? 0),
            'skipped' => $skipped,
            'message' => $invResult['message']
                ?? ('Pushed '.count($inventoryRows).' inventory row(s) to TopDawg.'),
        ];
    }

    /**
     * Full inventory sync from Shopify → TopDawg for all linked SKUs.
     *
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncAllFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('topdawg');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'Inventory/price sync disabled in TopDawg settings.',
            ];
        }

        if (! $this->topdawgApi->isConfigured()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'TopDawg API credentials missing.',
            ];
        }

        $skus = TopDawgProduct::query()
            ->whereNotNull('sku')
            ->whereNotNull('topdawg_listing_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'topdawg_listing_id')
            ->pluck('sku')
            ->map(static fn ($s) => trim((string) $s))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($skus === []) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'No linked TopDawg SKUs. Run Sync TopDawg link map first.',
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
        $pass = app(MarketplaceMismatchInventoryPass::class)->run('topdawg');
        $result['message'] = ($result['message'] ?? '').' '.($pass['message'] ?? '');

        return $result;
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $inventoryRows
     * @return array{success: bool, pushed: int, failed: int, message?: string}
     */
    protected function pushInventoryRows(array $inventoryRows): array
    {
        $items = [];
        foreach ($inventoryRows as $row) {
            $items[] = [
                'sku' => (string) $row['sku_code'],
                'quantity' => (int) $row['inventory'],
            ];
        }

        $bulk = $this->topdawgApi->updateItemInventoryBulk($items);

        return [
            'success' => ($bulk['pushed'] ?? 0) > 0,
            'pushed' => (int) ($bulk['pushed'] ?? 0),
            'failed' => (int) ($bulk['failed'] ?? 0),
            'message' => $bulk['error_message'] ?? null,
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
            Log::warning('TopDawgInventorySyncService: live Shopify fetch failed', ['error' => $e->getMessage()]);

            return [];
        }
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
            TopDawgProduct::query()
                ->where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->update(['remaining_inventory' => $qty]);

            if (Schema::hasTable('product_stock_mappings')
                && Schema::hasColumn('product_stock_mappings', 'inventory_topdawg')) {
                ProductStockMapping::query()
                    ->where('sku', $sku)
                    ->update(['inventory_topdawg' => $qty]);
            }
        }
    }
}
