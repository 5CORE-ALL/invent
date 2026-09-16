<?php

namespace App\Services\MarketplaceManager;

use App\Models\B5cB2bProduct;
use App\Models\MarketplaceSyncSettings;
use App\Models\ShopifySku;
use App\Services\Business5CoreB2bApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class B5cB2bInventorySyncService
{
    public function __construct(protected Business5CoreB2bApiService $api)
    {
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncFromShopify(bool $exactShopifyQty = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('b5cb2b');
        if (! ($settings['inventory']['inventory_sync'] ?? false)) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'Inventory sync is disabled in Business 5 Core (B2B) settings.'];
        }
        if (! Schema::hasTable('b5c_b2b_products')) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'b5c_b2b_products table missing.'];
        }

        $skus = B5cB2bProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->pluck('sku')
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->syncSkusFromShopify($skus, null, $exactShopifyQty);
    }

    /**
     * @param  list<string>  $skus
     * @param  array{store_url?: string, token?: string}|null  $shopifyConfig
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncSkusFromShopify(array $skus, ?array $shopifyConfig = null, bool $exactShopifyQty = false): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($sku) => trim((string) $sku),
            $skus
        ))));
        if ($skus === []) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'No SKUs to sync.'];
        }
        if (! $this->api->isConfigured()) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'Business 5 Core B2B API is not configured.'];
        }

        $settings = MarketplaceSyncSettings::getFor('b5cb2b');
        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $shopifyQty = app(ShopifyQtySource::class)->fetchQuantitiesForPush($skus);
        $shopifyQty = MarketplaceLiveInventoryRules::applyListingsShopifyQtyForPush($shopifyQty, $skus, $exactShopifyQty);

        $updated = 0;
        $failed = 0;
        $skipped = 0;
        $batch = [];
        foreach ($skus as $sku) {
            $norm = strtoupper(ShopifySku::normalizeSkuForShopifyLookup($sku) ?: $sku);
            $qty = $shopifyQty[$sku] ?? $shopifyQty[$norm] ?? null;
            if ($qty === null) {
                $skipped++;
                continue;
            }
            $pushQty = $exactShopifyQty ? (int) $qty : (int) floor(((int) $qty) * ($qtyPercent / 100));
            $batch[] = [
                'sku' => $sku,
                'qty' => max(0, $pushQty),
                'manage_stock' => true,
            ];
        }

        foreach (array_chunk($batch, 200) as $chunk) {
            try {
                $this->api->pushInventory($chunk);
                $updated += count($chunk);
                foreach ($chunk as $row) {
                    B5cB2bProduct::query()->where('sku', $row['sku'])->update([
                        'qty' => $row['qty'],
                        'in_stock' => $row['qty'] > 0,
                    ]);
                }
            } catch (\Throwable $e) {
                $failed += count($chunk);
                Log::warning('B5C B2B inventory push failed', ['error' => $e->getMessage()]);
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => $skipped,
            'message' => "Pushed {$updated} SKU(s) to Business 5 Core B2B"
                .($failed ? ", {$failed} failed" : '')
                .($skipped ? ", {$skipped} skipped" : '').'.',
        ];
    }
}
