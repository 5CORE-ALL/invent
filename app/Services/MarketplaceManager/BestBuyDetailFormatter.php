<?php

namespace App\Services\MarketplaceManager;

use App\Models\BestBuyOrderMetric;
use App\Models\BestbuyUsaProduct;
use App\Models\ShopifySku;
use Illuminate\Support\Collection;

class BestBuyDetailFormatter
{
    /**
     * @param  array<string, mixed>|null  $bbLive
     * @return array<string, mixed>
     */
    public function formatProduct(?array $bbLive, ?BestbuyUsaProduct $product, ShopifySku $shopify): array
    {
        $bb = is_array($bbLive) ? $bbLive : [];
        $sku = (string) $shopify->sku;
        $linked = $product !== null && trim((string) $product->sku) !== '';
        $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromRow($shopify);
        $bbStock = $linked ? ($bb['inventory'] ?? $product?->stock) : null;

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
                'quantity' => $bbStock,
                'last_synced_at' => $product?->updated_at,
            ],
            'bestbuy' => [
                'product_id' => $bb['product_id'] ?? $product?->sku,
                'title' => $bb['title'] ?? $product?->sku ?? $sku,
                'status' => $bb['state'] ?? 'active',
                'stock' => $bbStock,
                'price' => $bb['price'] ?? $product?->price,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $orderRoot
     * @param  Collection<int, BestBuyOrderMetric>  $lines
     * @return array<string, mixed>
     */
    public function formatOrder(array $orderRoot, Collection $lines, BestBuyOrderMetric $primaryLine): array
    {
        $order = $orderRoot;
        $customer = trim(trim((string) ($primaryLine->shipping_first_name ?? $primaryLine->billing_first_name ?? '')).' '
            .trim((string) ($primaryLine->shipping_last_name ?? $primaryLine->billing_last_name ?? '')));

        return [
            'summary' => [
                'order_id' => (string) ($order['order_id'] ?? $primaryLine->order_id),
                'order_number' => $order['channel_order_id'] ?? $primaryLine->channel_order_id,
                'status' => $order['status'] ?? $primaryLine->status,
                'created' => $order['order_created_at'] ?? $primaryLine->order_created_at,
            ],
            'amounts' => [
                'total' => $primaryLine->lineAmount(),
                'currency' => $primaryLine->currency ?? 'USD',
            ],
            'shipping' => [
                'city' => $primaryLine->shipping_city ?? $primaryLine->billing_city,
                'state' => $primaryLine->shipping_state ?? $primaryLine->billing_state,
                'country' => $primaryLine->shipping_country ?? $primaryLine->billing_country,
                'tracking' => null,
                'carrier' => $primaryLine->shipping_carrier,
            ],
            'buyer' => [
                'name' => $customer !== '' ? $customer : null,
            ],
            'lines' => $lines->map(fn (BestBuyOrderMetric $line) => [
                'sku' => $line->sku,
                'title' => $line->product_title,
                'quantity' => $line->quantity,
                'amount' => $line->lineAmount(),
                'status' => $line->status,
            ])->values()->all(),
        ];
    }
}
