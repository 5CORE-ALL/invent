<?php

namespace App\Services\MarketplaceManager;

use App\Models\MmInventoryLedger;
use App\Models\ShopifyCatalogProduct;
use App\Models\ShopifyCatalogVariant;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Log;

/**
 * Apply Shopify products/* webhook payloads onto shopify_catalog_* (+ light shopify_skus / ledger).
 */
class ShopifyCatalogWebhookService
{
    public function store(): string
    {
        return (string) config('marketplace_manager.default_store', 'main');
    }

    /**
     * Upsert product + variants from products/create or products/update payload.
     *
     * @param  array<string, mixed>  $product
     * @return array{product_id: int, variants: int, skus: list<string>}
     */
    public function upsertFromProductPayload(array $product): array
    {
        $store = $this->store();
        $pid = (int) ($product['id'] ?? 0);
        if ($pid <= 0) {
            throw new \InvalidArgumentException('product payload missing id');
        }

        $now = now();
        $productRow = ShopifyCatalogProduct::updateOrCreate(
            [
                'store' => $store,
                'shopify_id' => $pid,
            ],
            [
                'title' => $product['title'] ?? null,
                'handle' => $product['handle'] ?? null,
                'status' => $product['status'] ?? null,
                'body_html' => $product['body_html'] ?? null,
                'vendor' => $product['vendor'] ?? null,
                'product_type' => $product['product_type'] ?? null,
                'synced_at' => $now,
            ]
        );

        $seenVariantIds = [];
        $skus = [];
        $variantCount = 0;

        foreach ($product['variants'] ?? [] as $variant) {
            if (! is_array($variant)) {
                continue;
            }
            $vid = (int) ($variant['id'] ?? 0);
            if ($vid <= 0) {
                continue;
            }
            $seenVariantIds[$vid] = true;
            $sku = isset($variant['sku']) ? trim((string) $variant['sku']) : '';
            $qty = array_key_exists('inventory_quantity', $variant)
                ? (int) $variant['inventory_quantity']
                : null;

            ShopifyCatalogVariant::updateOrCreate(
                [
                    'store' => $store,
                    'shopify_variant_id' => $vid,
                ],
                [
                    'shopify_catalog_product_id' => $productRow->id,
                    'shopify_product_id' => $pid,
                    'sku' => $sku !== '' ? $sku : null,
                    'variant_title' => $variant['title'] ?? null,
                    'price' => isset($variant['price']) ? (float) $variant['price'] : null,
                    'position' => isset($variant['position']) ? (int) $variant['position'] : null,
                    'inventory_quantity' => $qty,
                    'synced_at' => $now,
                ]
            );
            $variantCount++;

            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }
            $skus[] = $sku;

            $itemId = null;
            if (! empty($variant['inventory_item_id'])) {
                $itemId = preg_replace('/\D+/', '', (string) $variant['inventory_item_id']) ?: null;
            }

            try {
                ShopifySku::query()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'variant_id' => (string) $vid,
                        'available_to_sell' => $qty ?? 0,
                        'inv' => $qty ?? 0,
                        'on_hand' => $qty ?? 0,
                        'product_title' => $product['title'] ?? null,
                        'variant_title' => $variant['title'] ?? null,
                        'price' => isset($variant['price']) ? (float) $variant['price'] : null,
                        'updated_at' => $now,
                    ]
                );
            } catch (\Throwable $e) {
                // non-fatal
            }

            try {
                app(InventoryLedgerService::class)->upsertMapping(
                    $sku,
                    $itemId,
                    (string) $vid,
                    $qty,
                    'webhook',
                );
            } catch (\Throwable $e) {
                Log::warning('ShopifyCatalogWebhookService: ledger upsert failed', [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Drop variants removed from the product.
        if ($seenVariantIds !== []) {
            ShopifyCatalogVariant::query()
                ->where('store', $store)
                ->where('shopify_product_id', $pid)
                ->whereNotIn('shopify_variant_id', array_keys($seenVariantIds))
                ->delete();
        }

        return [
            'product_id' => $pid,
            'variants' => $variantCount,
            'skus' => array_values(array_unique($skus)),
        ];
    }

    /**
     * Soft-remove catalog rows for products/delete (payload usually has id).
     *
     * @param  array<string, mixed>  $product
     * @return array{deleted_products: int, deleted_variants: int}
     */
    public function deleteFromProductPayload(array $product): array
    {
        $store = $this->store();
        $pid = (int) ($product['id'] ?? 0);
        if ($pid <= 0) {
            throw new \InvalidArgumentException('product delete payload missing id');
        }

        $productRow = ShopifyCatalogProduct::query()
            ->where('store', $store)
            ->where('shopify_id', $pid)
            ->first();

        $deletedVariants = 0;
        $deletedProducts = 0;

        if ($productRow) {
            $skus = ShopifyCatalogVariant::query()
                ->where('shopify_catalog_product_id', $productRow->id)
                ->whereNotNull('sku')
                ->pluck('sku')
                ->all();

            $deletedVariants = ShopifyCatalogVariant::query()
                ->where('shopify_catalog_product_id', $productRow->id)
                ->delete();
            $deletedProducts = $productRow->delete() ? 1 : 0;

            foreach ($skus as $sku) {
                $sku = trim((string) $sku);
                if ($sku === '') {
                    continue;
                }
                try {
                    MmInventoryLedger::query()
                        ->where('store', $store)
                        ->where('sku', $sku)
                        ->delete();
                } catch (\Throwable $e) {
                    // non-fatal
                }
            }
        } else {
            $deletedVariants = ShopifyCatalogVariant::query()
                ->where('store', $store)
                ->where('shopify_product_id', $pid)
                ->delete();
        }

        return [
            'deleted_products' => $deletedProducts,
            'deleted_variants' => $deletedVariants,
        ];
    }
}
