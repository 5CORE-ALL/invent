<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\ProductStockMapping;
use App\Models\BestbuyUsaProduct;
use App\Models\ShopifySku;
use App\Services\BestBuyApiService;
use App\Services\ShopifyApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BestBuyInventorySyncService
{
    public function __construct(
        protected BestBuyApiService $bbApi,
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

        if (! $this->bbApi->isConfigured()) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'Best Buy API credentials missing.'];
        }

        $settings = MarketplaceSyncSettings::getFor('bestbuy');
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

        $products = BestbuyUsaProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get()
            ->filter(function (BestbuyUsaProduct $row) use ($wantedNorms, $skus) {
                $raw = (string) $row->sku;
                if (in_array($raw, $skus, true)) {
                    return true;
                }
                $norm = ShopifySku::normalizeSkuForShopifyLookup($raw);

                return $norm !== '' && isset($wantedNorms[$norm]);
            })
            ->values();

        $apiItems = [];
        $skipped = 0;

        foreach ($products as $product) {
            $sku = (string) $product->sku;

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

            // Match AliExpress/Amazon: missing Shopify qty => push 0 (do not skip and leave stale MP stock).
            $qty = $shopifyStock === null
                ? MarketplaceLiveInventoryRules::qtyWhenMissingFromShopify()
                : MarketplaceLiveInventoryRules::qtyFromLiveShopify($shopifyStock, $qtyPercent, $maxQty);

            $apiItems[] = [
                'sku' => $sku,
                'quantity' => $qty,
            ];
        }

        if ($apiItems === []) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => $skipped + max(0, count($skus) - $products->count()),
                'message' => 'No linked Best Buy SKUs found for inventory sync.',
            ];
        }

        $bulk = $this->bbApi->updateItemInventoryBulk($apiItems);
        $this->persistLocalStock($apiItems);

        return [
            'updated' => (int) ($bulk['pushed'] ?? count($apiItems)),
            'failed' => (int) ($bulk['failed'] ?? 0),
            'skipped' => $skipped,
            'message' => $bulk['message'] ?? ('Pushed '.count($apiItems).' inventory row(s) to Best Buy.'),
        ];
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncAllFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('bestbuy');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'Inventory/price sync disabled in Best Buy settings.',
            ];
        }

        if (! $this->bbApi->isConfigured()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'Best Buy API credentials missing.',
            ];
        }

        $skus = BestbuyUsaProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
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
                'message' => 'No Best Buy SKUs. Run Sync link map first.',
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
        $pass = app(MarketplaceMismatchInventoryPass::class)->run('bestbuy');
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
        $live = [];
        try {
            unset($shopifyConfig);
            $live = $this->shopifyApi->getInventoryQuantitiesBySku($skus);
        } catch (\Throwable $e) {
            Log::warning('BestBuyInventorySyncService: live Shopify fetch failed', ['error' => $e->getMessage()]);
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
     * @param  array<int, array{sku: string, quantity: int}>  $rows
     */
    protected function persistLocalStock(array $rows): void
    {
        foreach ($rows as $row) {
            $sku = (string) $row['sku'];
            $qty = (int) $row['quantity'];
            BestbuyUsaProduct::query()
                ->where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->update(['stock' => $qty]);

            if (Schema::hasTable('product_stock_mappings')
                && Schema::hasColumn('product_stock_mappings', 'inventory_bestbuy')) {
                ProductStockMapping::query()
                    ->where('sku', $sku)
                    ->update(['inventory_bestbuy' => (string) $qty]);
            }
        }
    }
}
