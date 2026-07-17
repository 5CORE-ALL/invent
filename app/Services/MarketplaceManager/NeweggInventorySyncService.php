<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\NeweggMetric;
use App\Models\NeweggPricing;
use App\Models\NeweggPricingPrice;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Services\NeweggApiService;
use App\Services\ShopifyApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NeweggInventorySyncService
{
    public function __construct(
        protected NeweggApiService $neweggApi,
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

        if (! $this->neweggApi->isConfigured()) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'Newegg API credentials missing.'];
        }

        $settings = MarketplaceSyncSettings::getFor('newegg');
        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;

        $shopifyQty = $this->fetchLiveShopifyQuantities($skus, $shopifyConfig);
        $metrics = NeweggMetric::query()
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
            if (! MarketplaceLiveInventoryRules::isLinked($productId, $sku)) {
                $skipped++;
                continue;
            }

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

        if ($inventoryRows === []) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => $skipped + (count($skus) - $metrics->count()),
                'message' => 'No linked Newegg SKUs found for inventory sync.',
            ];
        }

        $invResult = $this->pushInventoryRows($inventoryRows);
        if (! empty($invResult['success'])) {
            $this->updateLocalStock($inventoryRows);
            $this->updateLocalPlatformQuantities($inventoryRows);

            return [
                'updated' => (int) ($invResult['pushed'] ?? count($inventoryRows)),
                'failed' => (int) ($invResult['failed'] ?? 0),
                'skipped' => $skipped,
                'message' => 'Synced '.((int) ($invResult['pushed'] ?? 0)).' SKU(s) to Newegg from live Shopify.',
            ];
        }

        $this->updateLocalPlatformQuantities($inventoryRows, false);

        return [
            'updated' => (int) ($invResult['pushed'] ?? 0),
            'failed' => (int) ($invResult['failed'] ?? count($inventoryRows)),
            'skipped' => $skipped,
            'message' => $invResult['message'] ?? 'Newegg inventory update failed.',
        ];
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, price_updated: int, message: string}
     */
    public function syncFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('newegg');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'Inventory and price sync are disabled in settings.',
            ];
        }

        if (! $this->neweggApi->isConfigured()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'Newegg API credentials missing.',
            ];
        }

        if (! Schema::hasTable('newegg_metric')) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'newegg_metric table missing. Run Sync link map on Listings first.',
            ];
        }

        $metrics = NeweggMetric::query()
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
                'message' => 'No Newegg SKU mappings found. Run Sync link map on Listings first.',
            ];
        }

        $skus = $metrics->pluck('sku')->unique()->values()->all();
        Log::info('NeweggInventorySyncService: fetching live Shopify inventory', ['sku_count' => count($skus)]);
        $shopifyQty = $this->shopifyApi->getInventoryQuantitiesBySku($skus);

        $missing = [];
        foreach ($skus as $sku) {
            if ($this->resolveShopifyQty($shopifyQty, (string) $sku) === null) {
                $missing[] = (string) $sku;
            }
        }
        if ($missing !== []) {
            Log::info('NeweggInventorySyncService: live variant fallback for missing SKUs', ['count' => count($missing)]);
            foreach ($this->fetchLiveShopifyQuantities($missing) as $sku => $qty) {
                $shopifyQty[$sku] = $qty;
            }
        }

        $coverage = MarketplaceLiveInventoryRules::shopifyLiveCoverageReport(
            $skus,
            fn (string $sku) => $this->resolveShopifyQty($shopifyQty, $sku)
        );
        Log::info('NeweggInventorySyncService: Shopify live coverage', $coverage);
        if (! $coverage['ok'] && ($settings['inventory']['inventory_sync'] ?? false) && ! $dryRun) {
            Log::error('NeweggInventorySyncService: aborting inventory push — Shopify live coverage too low', $coverage);

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
                $this->updateLocalStock($inventoryRows);
                $this->updateLocalPlatformQuantities($inventoryRows);
            } elseif ($failed > 0) {
                Log::warning('NeweggInventorySyncService: inventory push failed', $invResult);
            }
        }

        if ($priceRows !== []) {
            $bulk = $this->neweggApi->updateItemPriceBulk(array_map(static fn ($r) => [
                'seller_part_number' => $r['sku_code'],
                'price' => $r['price'],
            ], $priceRows), 'USA');
            $priceUpdated = (int) ($bulk['pushed'] ?? 0);
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
                .$this->appendMismatchPass(!$dryRun && ($settings['inventory']['inventory_sync'] ?? false)),
        ];
    }

    protected function appendMismatchPass(bool $run): string
    {
        if (! $run) {
            return '';
        }

        $pass = app(MarketplaceMismatchInventoryPass::class)->run('newegg');

        return ' '.$pass['message'];
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
                'seller_part_number' => (string) $row['sku_code'],
                'quantity' => (int) $row['inventory'],
            ];
        }

        $bulk = $this->neweggApi->updateItemInventoryBulk($items);

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
            Log::warning('NeweggInventorySyncService: live Shopify fetch failed', ['error' => $e->getMessage()]);

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
        foreach ($rows as $row) {
            $sku = trim((string) $row['sku_code']);
            if ($sku === '') {
                continue;
            }
            $qty = (int) $row['inventory'];

            if (Schema::hasTable('newegg_pricing_prices')) {
                NeweggPricingPrice::updateOrCreate(
                    ['sku' => strtoupper($sku)],
                    ['ne_stock' => $qty]
                );
            }

            if (Schema::hasTable('newegg_pricing')) {
                NeweggPricing::query()
                    ->where('seller_part_number', $sku)
                    ->orWhere('seller_part_number', strtoupper($sku))
                    ->update(['available_quantity' => $qty]);
            }
        }
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

            $neQty = (int) $row['inventory'];
            $shopifyQty = array_key_exists('shopify_qty', $row) ? (int) $row['shopify_qty'] : $neQty;

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
                if ($updateNeStock && Schema::hasColumn('product_stock_mappings', 'inventory_newegg')) {
                    $payload['inventory_newegg'] = $neQty;
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
            NeweggMetric::query()->where('sku', $sku)->update(['price' => (float) $row['price']]);

            if (Schema::hasTable('newegg_pricing_prices')) {
                NeweggPricingPrice::updateOrCreate(
                    ['sku' => strtoupper(trim($sku))],
                    ['price' => (float) $row['price']]
                );
            }

            if (Schema::hasTable('newegg_pricing')) {
                NeweggPricing::query()
                    ->where('seller_part_number', $sku)
                    ->orWhere('seller_part_number', strtoupper($sku))
                    ->update(['selling_price' => (float) $row['price']]);
            }
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
