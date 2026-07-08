<?php

namespace App\Services\MarketplaceManager;

use App\Models\AliexpressMetric;
use App\Models\AliexpressPricingPrice;
use App\Models\MarketplaceSyncSettings;
use App\Services\AliExpressApiService;
use App\Services\ShopifyApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AliexpressInventorySyncService
{
    public function __construct(
        protected AliExpressApiService $aliExpressApi,
        protected ShopifyApiService $shopifyApi
    ) {}

    /**
     * @return array{updated: int, failed: int, skipped: int, price_updated: int, message: string}
     */
    public function syncFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('aliexpress');
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
                'message' => 'ALIEXPRESS_ACCESS_TOKEN missing.',
            ];
        }

        if (! Schema::hasTable('aliexpress_metric')) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'aliexpress_metric table missing. Run fetch first.',
            ];
        }

        $metrics = AliexpressMetric::query()
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
                'message' => 'No AliExpress SKU mappings found. Sync listings from API first.',
            ];
        }

        $skus = $metrics->pluck('sku')->unique()->values()->all();
        $shopifyQty = $this->shopifyApi->getInventoryQuantitiesBySku($skus);
        $shopifyDetails = $this->shopifyApi->getProductDetailsBySkuMap($skus);

        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $minQty = max(0, (int) ($settings['inventory']['min_quantity'] ?? 1));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;
        $useSalePrice = (bool) ($settings['pricing']['use_sale_price'] ?? false);

        $inventoryRows = [];
        $priceRows = [];
        $skipped = 0;

        foreach ($metrics as $metric) {
            $sku = (string) $metric->sku;
            $productId = (string) $metric->product_id;
            if ($productId === '' || $sku === $productId) {
                $skipped++;
                continue;
            }

            if ($settings['inventory']['inventory_sync'] ?? false) {
                $shopifyStock = (int) ($shopifyQty[$sku] ?? 0);
                $qty = (int) floor($shopifyStock * ($qtyPercent / 100));
                if ($qty < $minQty) {
                    $qty = $minQty;
                }
                if ($maxQty !== null && $maxQty !== '') {
                    $qty = min($qty, (int) $maxQty);
                }
                $inventoryRows[] = [
                    'product_id' => $productId,
                    'sku_code' => $sku,
                    'inventory' => max(0, $qty),
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
            $invResult = $this->aliExpressApi->batchUpdateInventory($inventoryRows);
            if (! empty($invResult['success'])) {
                $updated = count($inventoryRows);
                $this->updateLocalStock($inventoryRows);
            } else {
                $failed = count($inventoryRows);
                Log::warning('AliexpressInventorySyncService: inventory batch failed', $invResult);
            }
        }

        if ($priceRows !== []) {
            $priceResult = $this->aliExpressApi->batchUpdatePrice($priceRows);
            if (! empty($priceResult['success'])) {
                $priceUpdated = count($priceRows);
                $this->updateLocalPrices($priceRows);
            } else {
                Log::warning('AliexpressInventorySyncService: price batch failed', $priceResult);
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => $skipped,
            'price_updated' => $priceUpdated,
            'message' => "Inventory: {$updated} updated, {$failed} failed. Prices: {$priceUpdated} updated. Skipped: {$skipped}.",
        ];
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
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int}>  $rows
     */
    protected function updateLocalStock(array $rows): void
    {
        if (! Schema::hasTable('aliexpress_pricing_prices')) {
            return;
        }

        foreach ($rows as $row) {
            $sku = strtoupper(trim((string) $row['sku_code']));
            if ($sku === '') {
                continue;
            }
            AliexpressPricingPrice::updateOrCreate(
                ['sku' => $sku],
                ['ae_stock' => (int) $row['inventory']]
            );
        }
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, price: float|string}>  $rows
     */
    protected function updateLocalPrices(array $rows): void
    {
        foreach ($rows as $row) {
            $sku = (string) $row['sku_code'];
            AliexpressMetric::query()
                ->where('sku', $sku)
                ->update(['price' => (float) $row['price']]);

            if (Schema::hasTable('aliexpress_pricing_prices')) {
                AliexpressPricingPrice::updateOrCreate(
                    ['sku' => strtoupper(trim($sku))],
                    ['price' => (float) $row['price']]
                );
            }
        }
    }
}
