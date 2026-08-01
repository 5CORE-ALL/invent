<?php

namespace App\Services\MarketplaceManager;

use App\Models\PurchasingPowerProduct;
use App\Models\PurchasingPowerSale;
use App\Models\ShopifySku;
use Illuminate\Support\Collection;

class PurchasingPowerDetailFormatter
{
    /**
     * @param  array<string, mixed>|null  $ppLive
     * @return array<string, mixed>
     */
    public function formatProduct(?array $ppLive, ?PurchasingPowerProduct $product, ShopifySku $shopify): array
    {
        $pp = is_array($ppLive) ? $ppLive : [];
        $sku = (string) $shopify->sku;
        $linked = $product !== null && trim((string) $product->sku) !== '';
        $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromRow($shopify);
        $ppStock = $linked ? ($pp['inventory'] ?? $product?->stock) : null;

        return [
            'shopify' => [
                'sku' => $shopify->sku,
                'goods_summary' => $shopify->product_title ?? $shopify->variant_title,
                'variant_title' => $shopify->variant_title,
                'variant_id' => $shopify->variant_id,
                'product_link' => $shopify->product_link,
                'image' => $shopify->image_src,
                'available_to_sell' => $shopifyQty,
                'b2c_price' => $shopify->b2c_price ?? $shopify->price,
                'price' => $shopify->price,
            ],
            'link' => [
                'product_id' => $product?->sku,
                'title' => $product?->sku,
                'price' => $product?->price,
                'quantity' => $ppStock,
                'last_synced_at' => $product?->updated_at,
            ],
            'purchasingpower' => [
                'product_id' => $pp['product_id'] ?? $product?->sku,
                'title' => $pp['title'] ?? $product?->sku ?? $sku,
                'status' => $pp['state'] ?? 'active',
                'stock' => $ppStock,
                'price' => $pp['price'] ?? $product?->price,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $orderRoot
     * @param  Collection<int, PurchasingPowerSale>  $lines
     * @return array<string, mixed>
     */
    public function formatOrder(array $orderRoot, Collection $lines, PurchasingPowerSale $primaryLine): array
    {
        $order = $orderRoot;
        $customer = trim(trim((string) ($primaryLine->customer_first_name ?? '')).' '.trim((string) ($primaryLine->customer_last_name ?? '')));

        return [
            'summary' => [
                'order_id' => (string) ($order['order_id'] ?? $primaryLine->order_id),
                'order_number' => $order['order_number'] ?? $primaryLine->order_number,
                'status' => $order['status'] ?? $primaryLine->status,
                'created' => $order['date_created'] ?? $primaryLine->date_created,
            ],
            'amounts' => [
                'total' => $primaryLine->amount,
                'currency' => $primaryLine->currency ?? 'USD',
            ],
            'shipping' => [
                'city' => $primaryLine->customer_city,
                'state' => $primaryLine->customer_state,
                'country' => $primaryLine->customer_country,
                'tracking' => $primaryLine->tracking_number,
                'carrier' => $primaryLine->shipping_company,
            ],
            'buyer' => [
                'name' => $customer !== '' ? $customer : null,
            ],
            'lines' => $lines->map(fn (PurchasingPowerSale $line) => [
                'sku' => $line->offer_sku ?? $line->product_sku,
                'title' => $line->product_name,
                'quantity' => $line->quantity,
                'amount' => $line->amount,
                'status' => $line->status,
            ])->values()->all(),
        ];
    }
}
