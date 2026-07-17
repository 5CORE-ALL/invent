<?php

namespace App\Services\MarketplaceManager;

use App\Models\AlibabaMetric;
use App\Models\AlibabaPricingPrice;
use App\Models\MarketplaceSyncSettings;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Services\AlibabaApiService;
use App\Services\ShopifyApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AlibabaInventorySyncService
{
    public function __construct(
        protected AlibabaApiService $aliExpressApi,
        protected ShopifyApiService $shopifyApi
    ) {}

    /**
     * Immediately sync specific SKUs from Shopify → Alibaba and refresh local qty fields.
     * Used after an Alibaba order is pushed to Shopify so AE stock matches the decrement.
     *
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

        if (empty($this->aliExpressApi->getAccessToken())) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'ALIBABA_ACCESS_TOKEN missing.'];
        }

        $settings = MarketplaceSyncSettings::getFor('alibaba');
        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;
        // min_quantity is ignored: Shopify 0 => marketplace 0 (never invent 1).

        // Ledger-first when MM_USE_INVENTORY_LEDGER; else live Shopify API.
        $shopifyQty = app(ShopifyQtySource::class)->fetchQuantitiesForPush(
            $skus,
            fn (array $need) => $this->fetchLiveShopifyQuantities($need, $shopifyConfig)
        );
        $metrics = AlibabaMetric::query()
            ->whereIn('sku', $skus)
            ->whereNotNull('product_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'product_id')
            ->get();

        $inventoryRows = [];
        $skipped = 0;

        foreach ($metrics as $metric) {
            $sku = (string) $metric->sku;
            $productId = (string) $metric->product_id;
            // Rule 1: never update unlinked SKUs.
            if (! MarketplaceLiveInventoryRules::isLinked($productId, $sku)) {
                $skipped++;
                continue;
            }

            $shopifyStock = $this->resolveShopifyQty($shopifyQty, $sku);
            // Rule 4: missing / 0 live Shopify => push 0 (do not skip leaving stale MP stock).
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
                'failed' => 0,
                'skipped' => $skipped + (count($skus) - $metrics->count()),
                'message' => 'No linked Alibaba SKUs found for inventory sync.',
            ];
        }

        $invResult = $this->aliExpressApi->batchUpdateInventory($inventoryRows);
        if (! empty($invResult['success'])) {
            $this->updateLocalStock($inventoryRows);
            $this->updateLocalPlatformQuantities($inventoryRows);

            return [
                'updated' => count($inventoryRows),
                'failed' => 0,
                'skipped' => $skipped,
                'message' => 'Synced '.count($inventoryRows).' SKU(s) to Alibaba and local platform.',
            ];
        }

        // Still refresh Shopify qty on our platform even if AE API push failed.
        $this->updateLocalPlatformQuantities($inventoryRows, false);

        Log::warning('AlibabaInventorySyncService: post-order SKU inventory sync failed', [
            'skus' => $skus,
            'result' => $invResult,
        ]);

        return [
            'updated' => 0,
            'failed' => count($inventoryRows),
            'skipped' => $skipped,
            'message' => $invResult['message'] ?? 'Alibaba inventory update failed.',
        ];
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, price_updated: int, message: string}
     */
    public function syncFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('alibaba');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'Inventory and price sync are disabled in settings.',
            ];
        }

        if (empty($this->aliExpressApi->getAccessToken())) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'ALIBABA_ACCESS_TOKEN missing.',
            ];
        }

        if (! Schema::hasTable('alibaba_metrics')) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'alibaba_metrics table missing. Run fetch first.',
            ];
        }

        $metrics = AlibabaMetric::query()
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
                'message' => 'No Alibaba SKU mappings found. Run Sync AE link map on Listings first.',
            ];
        }

        if (MarketplaceSyncSettings::alibabaCanCreateProducts($settings)) {
            Log::info('AlibabaInventorySyncService: create_products_on_alibaba is enabled but listing creation is not implemented yet; only existing linked SKUs will be updated.');
        }

        $skus = $metrics->pluck('sku')->unique()->values()->all();

        // 1) Fetch LIVE Shopify stock first (Admin API) — never use local shopify_skus / mapping cache for push qty.
        Log::info('AlibabaInventorySyncService: fetching live Shopify inventory', ['sku_count' => count($skus)]);
        $shopifyQty = $this->shopifyApi->getInventoryQuantitiesBySku($skus);

        // Fill gaps with direct variant lookups (bulk product pagination can miss SKUs).
        $missing = [];
        foreach ($skus as $sku) {
            if ($this->resolveShopifyQty($shopifyQty, (string) $sku) === null) {
                $missing[] = (string) $sku;
            }
        }
        if ($missing !== []) {
            Log::info('AlibabaInventorySyncService: live variant fallback for missing SKUs', ['count' => count($missing)]);
            foreach ($this->fetchLiveShopifyQuantities($missing) as $sku => $qty) {
                $shopifyQty[$sku] = $qty;
            }
        }

        $coverage = MarketplaceLiveInventoryRules::shopifyLiveCoverageReport(
            $skus,
            fn (string $sku) => $this->resolveShopifyQty($shopifyQty, $sku)
        );
        Log::info('AlibabaInventorySyncService: Shopify live coverage', $coverage);
        if (! $coverage['ok'] && ($settings['inventory']['inventory_sync'] ?? false) && ! $dryRun) {
            Log::error('AlibabaInventorySyncService: aborting inventory push — Shopify live coverage too low', $coverage);

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
            // Rule 1: never update unlinked SKUs.
            if (! MarketplaceLiveInventoryRules::isLinked($productId, $sku)) {
                $skipped++;
                continue;
            }

            if ($settings['inventory']['inventory_sync'] ?? false) {
                $shopifyStock = $this->resolveShopifyQty($shopifyQty, $sku);
                // Rules 2+4: live Shopify only; missing/0 => marketplace 0 (never invent stock).
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
            Log::info('AlibabaInventorySyncService: pushing live Shopify qty to Alibaba', [
                'rows' => count($inventoryRows),
            ]);
            $invResult = $this->aliExpressApi->batchUpdateInventory($inventoryRows);
            if (! empty($invResult['success'])) {
                $updated = count($inventoryRows);
                $this->updateLocalStock($inventoryRows);
                $this->updateLocalPlatformQuantities($inventoryRows);
            } else {
                $failed = count($inventoryRows);
                Log::warning('AlibabaInventorySyncService: inventory batch failed', $invResult);
            }
        }

        if ($priceRows !== []) {
            $priceResult = $this->aliExpressApi->batchUpdatePrice($priceRows);
            if (! empty($priceResult['success'])) {
                $priceUpdated = count($priceRows);
                $this->updateLocalPrices($priceRows);
            } else {
                Log::warning('AlibabaInventorySyncService: price batch failed', $priceResult);
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => $skipped,
            'price_updated' => $priceUpdated,
            'message' => "Inventory: {$updated} updated, {$failed} failed. Prices: {$priceUpdated} updated. Skipped: {$skipped}."
                .$this->appendMismatchPass(!$dryRun && ($settings['inventory']['inventory_sync'] ?? false)),
        ];
    }

    protected function appendMismatchPass(bool $run): string
    {
        if (! $run) {
            return '';
        }

        $pass = app(MarketplaceMismatchInventoryPass::class)->run('alibaba');

        return ' '.$pass['message'];
    }

  /**
     * @param  array<string, mixed>  $pricingSettings
     */
    protected function applyPriceAdjustment(float $price, array $pricingSettings): float
    {
        if ($price <= 0) {
            return 0;
        }

        $value = (float) ($pricingSettings['adjustment_value'] ?? 0);
        if ($value <= 0) {
            return round($price, 2);
        }

        $method = $pricingSettings['adjustment_method'] ?? 'percent';
        $type = $pricingSettings['adjustment_type'] ?? 'increase';
        $delta = $method === 'fixed' ? $value : ($price * ($value / 100));
        $adjusted = $type === 'decrease' ? $price - $delta : $price + $delta;

        return round(max(0, $adjusted), 2);
    }

    /**
     * @param  array<int, string>  $skus
     * @param  array{store_url?: string, token?: string}|null  $shopifyConfig
     * @return array<string, int>
     */
    protected function fetchLiveShopifyQuantities(array $skus, ?array $shopifyConfig = null): array
    {
        $storeUrl = $this->normalizeStoreUrl((string) ($shopifyConfig['store_url'] ?? ''));
        $token = (string) ($shopifyConfig['token'] ?? '');

        if ($storeUrl === '' || $token === '') {
            $storeUrl = $this->normalizeStoreUrl((string) config('services.shopify.store_url', ''));
            $token = (string) (config('services.shopify.access_token') ?: config('services.shopify.password') ?: '');
        }

        if ($storeUrl === '' || $token === '') {
            return $this->shopifyApi->getInventoryQuantitiesBySku($skus);
        }

        $map = [];
        foreach ($skus as $sku) {
            $variantId = null;
            $local = ShopifySku::firstForProductSku($sku);
            if ($local && ! empty($local->variant_id)) {
                $variantId = (int) $local->variant_id;
            }

            if ($variantId) {
                try {
                    $response = Http::withHeaders([
                        'X-Shopify-Access-Token' => $token,
                    ])->timeout(30)->get("https://{$storeUrl}/admin/api/2024-01/variants/{$variantId}.json");

                    if ($response->successful()) {
                        $map[$sku] = (int) ($response->json('variant.inventory_quantity') ?? 0);
                        continue;
                    }
                } catch (\Throwable $e) {
                    Log::warning('AlibabaInventorySyncService: variant qty fetch failed', [
                        'sku' => $sku,
                        'variant_id' => $variantId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            try {
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                ])->timeout(30)->get('https://'.$storeUrl.'/admin/api/2024-01/variants.json', [
                    'sku' => $sku,
                    'limit' => 1,
                ]);
                if ($response->successful()) {
                    $variants = $response->json('variants') ?? [];
                    if (is_array($variants) && $variants !== []) {
                        $map[$sku] = (int) ($variants[0]['inventory_quantity'] ?? 0);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('AlibabaInventorySyncService: sku qty fetch failed', [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $map;
    }

    protected function normalizeStoreUrl(string $url): string
    {
        return strtolower(rtrim(str_replace(['https://', 'http://'], '', trim($url)), '/'));
    }

    /**
     * @param  array<string, int>  $shopifyQty
     */
    protected function resolveShopifyQty(array $shopifyQty, string $sku): ?int
    {
        if (array_key_exists($sku, $shopifyQty)) {
            return (int) $shopifyQty[$sku];
        }

        $needle = strtoupper(trim($sku));
        foreach ($shopifyQty as $key => $qty) {
            if (strtoupper(trim((string) $key)) === $needle) {
                return (int) $qty;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $rows
     */
    protected function updateLocalStock(array $rows): void
    {
        if (! Schema::hasTable('alibaba_pricing_prices')) {
            return;
        }

        foreach ($rows as $row) {
            $sku = strtoupper(trim((string) $row['sku_code']));
            if ($sku === '') {
                continue;
            }
            AlibabaPricingPrice::updateOrCreate(
                ['sku' => $sku],
                ['ab_stock' => (int) $row['inventory']]
            );
        }
    }

    /**
     * Keep marketplace UI qty columns in sync after AE inventory push.
     *
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $rows
     */
    protected function updateLocalPlatformQuantities(array $rows, bool $updateAeStock = true): void
    {
        foreach ($rows as $row) {
            $sku = trim((string) $row['sku_code']);
            if ($sku === '') {
                continue;
            }

            $aeQty = (int) $row['inventory'];
            $shopifyQty = array_key_exists('shopify_qty', $row) ? (int) $row['shopify_qty'] : $aeQty;

            $shopifyRow = ShopifySku::firstForProductSku($sku);
            if ($shopifyRow) {
                $shopifyRow->fill([
                    'available_to_sell' => $shopifyQty,
                    'inv' => $shopifyQty,
                    'on_hand' => $shopifyQty,
                ])->save();
            }

            if (Schema::hasTable('product_stock_mappings')) {
                $payload = ['inventory_shopify' => $shopifyQty];
                if ($updateAeStock && Schema::hasColumn('product_stock_mappings', 'inventory_alibaba')) {
                    $payload['inventory_alibaba'] = $aeQty;
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
    protected function updateLocalPrices(array $rows): void
    {
        foreach ($rows as $row) {
            $sku = (string) $row['sku_code'];
            AlibabaMetric::query()
                ->where('sku', $sku)
                ->update(['price' => (float) $row['price']]);

            if (Schema::hasTable('alibaba_pricing_prices')) {
                AlibabaPricingPrice::updateOrCreate(
                    ['sku' => strtoupper(trim($sku))],
                    ['price' => (float) $row['price']]
                );
            }
        }
    }
}
