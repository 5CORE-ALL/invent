<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Models\Temu2Metric;
use App\Services\ShopifyApiService;
use App\Services\Temu2ApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Temu2InventorySyncService
{
    public function __construct(
        protected Temu2ApiService $temuApi,
        protected ShopifyApiService $shopifyApi
    ) {}

    public function syncFromShopify(bool $dryRun = false): array
    {
        return $this->syncAllFromShopify($dryRun);
    }

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

        if (! $this->temuApi->isConfigured()) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'Temu 2 API credentials missing.'];
        }

        $settings = MarketplaceSyncSettings::getFor('temu2');
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
            // Listings mismatch tab uses shopify_skus as Shopify qty source of truth.
            // Overlay it so pushed Temu 2 qty matches the numbers shown on that tab.
            foreach (MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($fetchSkus) as $key => $qty) {
                $shopifyQty[$key] = (int) $qty;
            }
        }

        $metrics = Temu2Metric::query()
            ->whereNotNull('goods_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'goods_id')
            ->get()
            ->filter(function (Temu2Metric $metric) use ($wantedNorms, $skus) {
                $raw = (string) $metric->sku;
                if (in_array($raw, $skus, true)) {
                    return true;
                }
                $norm = ShopifySku::normalizeSkuForShopifyLookup($raw);

                return $norm !== '' && isset($wantedNorms[$norm]);
            })
            ->values();

        $apiItems = [];
        $skipped = 0;

        foreach ($metrics as $metric) {
            $sku = (string) $metric->sku;
            $goodsId = (string) $metric->goods_id;
            if (! MarketplaceLiveInventoryRules::isLinked($goodsId, $sku)) {
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

            $qty = MarketplaceLiveInventoryRules::qtyFromLiveShopify($shopifyStock, $qtyPercent, $maxQty);

            $apiItems[] = [
                'goods_id' => $goodsId,
                'sku_id' => $metric->sku_id,
                'sku' => $sku,
                'quantity' => $qty,
            ];
        }

        if ($apiItems === []) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => $skipped,
                'message' => 'No linked Temu 2 SKUs found for inventory sync.',
            ];
        }

        $bulk = $this->temuApi->updateItemInventoryBulk($apiItems);
        $this->persistLocalStock($apiItems);

        return [
            'updated' => (int) ($bulk['pushed'] ?? 0),
            'failed' => (int) ($bulk['failed'] ?? 0),
            'skipped' => $skipped,
            'message' => $bulk['message'] ?? ('Pushed '.count($apiItems).' inventory row(s) to Temu 2.'),
        ];
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncAllFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('temu2');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'Inventory/price sync disabled in Temu 2 settings.',
            ];
        }

        if (! $this->temuApi->isConfigured()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'Temu 2 API credentials missing.',
            ];
        }

        $skus = Temu2Metric::query()
            ->whereNotNull('sku')
            ->whereNotNull('goods_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'goods_id')
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
                'message' => 'No linked Temu 2 SKUs. Run Sync Temu 2 link map first.',
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
        $pass = app(MarketplaceMismatchInventoryPass::class)->run('temu2');
        $result['message'] = ($result['message'] ?? '').' '.($pass['message'] ?? '');

        return $result;
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
            Log::warning('Temu2InventorySyncService: live Shopify fetch failed', ['error' => $e->getMessage()]);

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
     * @param  array<int, array{goods_id: string, sku_id?: mixed, sku: string, quantity: int}>  $rows
     */
    protected function persistLocalStock(array $rows): void
    {
        foreach ($rows as $row) {
            $sku = (string) $row['sku'];
            $qty = (int) $row['quantity'];
            Temu2Metric::query()
                ->where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->update(['quantity' => $qty]);

            if (Schema::hasTable('product_stock_mappings')
                && Schema::hasColumn('product_stock_mappings', 'inventory_temu2')) {
                ProductStockMapping::query()
                    ->where('sku', $sku)
                    ->update(['inventory_temu2' => (string) $qty]);
            }
        }
    }
}
