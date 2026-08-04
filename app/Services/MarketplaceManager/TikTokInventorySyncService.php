<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Models\TikTokProduct;
use App\Services\ShopifyApiService;
use App\Services\TikTokShopService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TikTokInventorySyncService
{
    public function __construct(
        protected TikTokShopService $tiktokApi,
        protected ShopifyApiService $shopifyApi
    ) {}

    /**
     * @param  array<int, string>  $skus
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncSkusFromShopify(array $skus, ?array $shopifyConfig = null): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($sku) => trim((string) $sku),
            $skus
        ), static fn ($sku) => $sku !== '')));

        if ($skus === []) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'No SKUs to sync.'];
        }

        if (! $this->tiktokApi->isAuthenticated()) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'TikTok Shop API not authenticated.'];
        }

        $settings = MarketplaceSyncSettings::getFor('tiktok');
        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;

        $shopifyQty = $this->fetchLiveShopifyQuantities($skus, $shopifyConfig);

        $metrics = TikTokProduct::query()
            ->whereNotNull('product_id')
            ->whereNotNull('sku_id')
            ->where('sku', '!=', '')
            ->whereIn('sku', $skus)
            ->get();

        $updated = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($metrics as $metric) {
            $sku = (string) $metric->sku;
            $productId = (string) $metric->product_id;
            $skuId = (string) $metric->sku_id;

            if ($productId === '' || $skuId === '') {
                $skipped++;
                continue;
            }

            $shopifyStock = $this->resolveShopifyQty($shopifyQty, $sku);
            $pushQty = $shopifyStock === null
                ? MarketplaceLiveInventoryRules::qtyWhenMissingFromShopify()
                : MarketplaceLiveInventoryRules::qtyFromLiveShopify($shopifyStock, $qtyPercent, $maxQty);
            $pushQty = MarketplaceLiveInventoryRules::clampPushQty($pushQty, $shopifyStock ?? 0);

            $result = $this->tiktokApi->updateProductInventory($productId, $skuId, $pushQty);

            if (! empty($result['success'])) {
                $updated++;
                $this->updateLocalStock($sku, $pushQty, $shopifyStock ?? 0);
            } else {
                $failed++;
                Log::warning('TikTokInventorySyncService: push failed', [
                    'sku' => $sku,
                    'product_id' => $productId,
                    'message' => $result['message'] ?? null,
                ]);
            }

            usleep(200000);
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => $skipped,
            'message' => "Synced {$updated} SKU(s) to TikTok Shop from live Shopify.",
        ];
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('tiktok');
        if (! ($settings['inventory']['inventory_sync'] ?? false)) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'Inventory sync is disabled in settings.',
            ];
        }

        if (! $this->tiktokApi->isAuthenticated()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'TikTok Shop API not authenticated.',
            ];
        }

        if (! Schema::hasTable('tiktok_products')) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'tiktok_products table missing. Run Sync products first.',
            ];
        }

        $metrics = TikTokProduct::query()
            ->whereNotNull('sku')
            ->whereNotNull('product_id')
            ->whereNotNull('sku_id')
            ->where('sku', '!=', '')
            ->get();

        if ($metrics->isEmpty()) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'No TikTok Shop SKU mappings with sku_id found. Run Sync products first.',
            ];
        }

        $skus = $metrics->pluck('sku')->unique()->values()->all();
        Log::info('TikTokInventorySyncService: fetching live Shopify inventory', ['sku_count' => count($skus)]);
        $shopifyQty = $this->shopifyApi->getInventoryQuantitiesBySku($skus);

        $missing = [];
        foreach ($skus as $sku) {
            if ($this->resolveShopifyQty($shopifyQty, (string) $sku) === null) {
                $missing[] = (string) $sku;
            }
        }
        if ($missing !== []) {
            foreach ($this->fetchLiveShopifyQuantities($missing) as $sku => $qty) {
                $shopifyQty[$sku] = $qty;
            }
        }

        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;

        $updated = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($metrics as $metric) {
            $sku = (string) $metric->sku;
            $productId = (string) $metric->product_id;
            $skuId = (string) $metric->sku_id;

            if ($productId === '' || $skuId === '') {
                $skipped++;
                continue;
            }

            $shopifyStock = $this->resolveShopifyQty($shopifyQty, $sku);
            $pushQty = $shopifyStock === null
                ? MarketplaceLiveInventoryRules::qtyWhenMissingFromShopify()
                : MarketplaceLiveInventoryRules::qtyFromLiveShopify($shopifyStock, $qtyPercent, $maxQty);
            $pushQty = MarketplaceLiveInventoryRules::clampPushQty($pushQty, $shopifyStock ?? 0);

            if ($dryRun) {
                $updated++;
                continue;
            }

            $result = $this->tiktokApi->updateProductInventory($productId, $skuId, $pushQty);

            if (! empty($result['success'])) {
                $updated++;
                $this->updateLocalStock($sku, $pushQty, $shopifyStock ?? 0);
            } else {
                $failed++;
            }

            usleep(200000);
        }

        if ($dryRun) {
            return [
                'updated' => $updated,
                'failed' => 0,
                'skipped' => $skipped,
                'message' => "[dry-run] Would update {$updated} inventory row(s).",
            ];
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => $skipped,
            'message' => "Updated {$updated} inventory; failed {$failed}; skipped {$skipped}.",
        ];
    }

    protected function fetchLiveShopifyQuantities(array $skus, ?array $shopifyConfig = null): array
    {
        try {
            if ($shopifyConfig) {
                return $this->shopifyApi->getInventoryQuantitiesBySku($skus, $shopifyConfig);
            }

            return $this->shopifyApi->getInventoryQuantitiesBySku($skus);
        } catch (\Throwable $e) {
            Log::warning('TikTokInventorySyncService: live Shopify fetch failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

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

    protected function updateLocalStock(string $sku, int $pushQty, int $shopifyQty): void
    {
        if (! Schema::hasTable('product_stock_mappings')) {
            return;
        }

        $payload = ['inventory_shopify' => $shopifyQty];
        if (Schema::hasColumn('product_stock_mappings', 'inventory_tiktok')) {
            $payload['inventory_tiktok'] = $pushQty;
        }

        ProductStockMapping::query()
            ->where(function ($q) use ($sku) {
                $q->where('sku', $sku)->orWhere('sku', strtoupper($sku));
            })
            ->update($payload);
    }
}
