<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\ProductStockMapping;
use App\Models\WayfairPricingPrice;
use App\Models\ShopifySku;
use App\Services\WayfairApiService;
use App\Services\ShopifyApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WayfairInventorySyncService
{
    public function __construct(
        protected WayfairApiService $wayfairApi,
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

        if (! $this->wayfairApi->isConfigured()) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'Wayfair API credentials missing.'];
        }

        $settings = MarketplaceSyncSettings::getFor('wayfair');
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

        $products = WayfairPricingPrice::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get()
            ->filter(function (WayfairPricingPrice $row) use ($wantedNorms, $skus) {
                $raw = (string) $row->sku;
                if (in_array($raw, $skus, true)) {
                    return true;
                }
                $norm = ShopifySku::normalizeSkuForShopifyLookup($raw);

                return $norm !== '' && isset($wantedNorms[$norm]);
            })
            ->values();

        $catalogQty = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($fetchSkus);
        foreach ($catalogQty as $key => $qty) {
            if (! array_key_exists($key, $shopifyQty)) {
                $shopifyQty[$key] = (int) $qty;
            }
        }

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
            if ($shopifyStock === null) {
                $skipped++;
                continue;
            }

            $qty = MarketplaceLiveInventoryRules::qtyFromLiveShopify($shopifyStock, $qtyPercent, $maxQty);
            $qty = MarketplaceLiveInventoryRules::clampPushQty($qty, $shopifyStock);

            $currentMp = $product->wayfair_stock !== null ? (int) $product->wayfair_stock : null;
            if ($shopifyStock > 0
                && MarketplaceLiveInventoryRules::qtyWithinMismatchTolerance((int) $shopifyStock, $currentMp)) {
                $skipped++;
                continue;
            }

            $apiItems[] = [
                'sku' => $sku,
                'quantity' => $qty,
            ];
        }

        if ($apiItems === []) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => $skipped,
                'message' => 'No linked Wayfair SKUs found for inventory sync.',
            ];
        }

        $bulk = $this->wayfairApi->updateItemInventoryBulk($apiItems);
        $accepted = [];
        foreach ($bulk['skus'] ?? [] as $sku) {
            $accepted[strtoupper((string) $sku)] = true;
        }
        $persistRows = $accepted === []
            ? []
            : array_values(array_filter($apiItems, static fn (array $row) => isset($accepted[strtoupper((string) $row['sku'])])));
        if ($persistRows !== []) {
            $this->persistLocalStock($persistRows);
            app(WayfairLiveListingsService::class)->clearCache();
        }

        return [
            'updated' => (int) ($bulk['pushed'] ?? 0),
            'failed' => (int) ($bulk['failed'] ?? 0),
            'skipped' => $skipped,
            'message' => $bulk['message'] ?? ('Pushed '.count($persistRows).' inventory row(s) to Wayfair.'),
        ];
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncAllFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('wayfair');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'Inventory/price sync disabled in Wayfair settings.',
            ];
        }

        if (! $this->wayfairApi->isConfigured()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'Wayfair API credentials missing.',
            ];
        }

        $skus = WayfairPricingPrice::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->pluck('sku')
            ->map(static fn ($s) => trim((string) $s))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        if ($catalog->tablesReady() && $catalog->hasAnyActive()) {
            $skus = $catalog->filterLinkedToVerified($skus);
        }

        $skus = $this->filterToActiveWayfairListings($skus);

        if ($skus === []) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'No active Wayfair SKUs linked to Shopify. Run Sync link map first.',
            ];
        }

        if ($dryRun) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => '[dry-run] Would sync '.count($skus).' active Wayfair SKU(s).',
            ];
        }

        return $this->syncSkusFromShopify($skus);
    }

    /**
     * Keep SKUs whose live Wayfair listing state is active. If the live cache
     * has no state data, keep the incoming list (treated as active).
     *
     * @param  list<string>  $skus
     * @return list<string>
     */
    protected function filterToActiveWayfairListings(array $skus): array
    {
        $live = app(WayfairLiveListingsService::class)->peekCached();
        if (! is_array($live) || $live === []) {
            return $skus;
        }

        $activeNorms = [];
        foreach ($live as $row) {
            if (! is_array($row)) {
                continue;
            }
            $state = strtolower(trim((string) ($row['state'] ?? 'active')));
            if (! in_array($state, ['active', '1', 'true', 'onselling', 'on_selling'], true)) {
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($row['sku'] ?? ''));
            if ($norm !== '') {
                $activeNorms[$norm] = true;
            }
        }
        if ($activeNorms === []) {
            return $skus;
        }

        return array_values(array_filter($skus, static function ($sku) use ($activeNorms) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);

            return $norm !== '' && isset($activeNorms[$norm]);
        }));
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
            Log::warning('WayfairInventorySyncService: live Shopify fetch failed', ['error' => $e->getMessage()]);

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
     * @param  array<int, array{sku: string, quantity: int}>  $rows
     */
    protected function persistLocalStock(array $rows): void
    {
        foreach ($rows as $row) {
            $sku = (string) $row['sku'];
            $qty = (int) $row['quantity'];
            WayfairPricingPrice::query()
                ->where(function ($q) use ($sku) {
                    $q->where('sku', $sku)
                        ->orWhere('sku', strtoupper($sku))
                        ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))]);
                })
                ->update(['wayfair_stock' => $qty]);

            if (Schema::hasTable('product_stock_mappings')
                && Schema::hasColumn('product_stock_mappings', 'inventory_wayfair')) {
                ProductStockMapping::query()
                    ->where('sku', $sku)
                    ->update(['inventory_wayfair' => (string) $qty]);
            }
        }
    }
}
