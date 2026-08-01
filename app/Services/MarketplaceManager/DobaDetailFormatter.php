<?php

namespace App\Services\MarketplaceManager;

use App\Models\DobaDailyData;
use App\Models\DobaMetric;
use App\Models\ShopifySku;
use Illuminate\Support\Collection;

class DobaDetailFormatter
{
    /**
     * @param  array<string, mixed>|null  $dobaLive
     * @return array<string, mixed>
     */
    public function formatProduct(?array $dobaLive, ?DobaMetric $product, ShopifySku $shopify): array
    {
        $db = is_array($dobaLive) ? $dobaLive : [];
        $sku = (string) $shopify->sku;
        $linked = $product !== null && trim((string) $product->sku) !== '';
        $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromRow($shopify);
        $dobaStock = $linked ? ($db['inventory'] ?? $product?->inventory) : null;

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
                'product_id' => $product?->item_id ?? $product?->sku,
                'title' => $product?->sku,
                'price' => $product?->anticipated_income,
                'quantity' => $dobaStock,
                'last_synced_at' => $product?->updated_at,
            ],
            'doba' => [
                'product_id' => $db['product_id'] ?? $product?->item_id,
                'title' => $db['title'] ?? $product?->sku ?? $sku,
                'status' => $db['state'] ?? 'active',
                'stock' => $dobaStock,
                'price' => $db['price'] ?? $product?->anticipated_income,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $orderRoot
     * @param  Collection<int, DobaDailyData>  $lines
     * @return array<string, mixed>
     */
    public function formatOrder(array $orderRoot, Collection $lines, DobaDailyData $primaryLine): array
    {
        $order = $orderRoot;

        return [
            'summary' => [
                'order_id' => (string) ($order['order_no'] ?? $primaryLine->order_no),
                'order_number' => $order['platform_order_no'] ?? $primaryLine->platform_order_no ?? $primaryLine->order_no,
                'status' => $order['order_status'] ?? $primaryLine->order_status,
                'created' => $order['order_time'] ?? $primaryLine->order_time,
            ],
            'amounts' => [
                'total' => $primaryLine->total_price ?? $primaryLine->item_price,
                'currency' => $primaryLine->currency ?? 'USD',
            ],
            'shipping' => [
                'city' => $primaryLine->shipping_city,
                'state' => $primaryLine->shipping_state,
                'country' => $primaryLine->shipping_country,
                'tracking' => $primaryLine->tracking_number,
                'carrier' => $primaryLine->carrier_name,
            ],
            'buyer' => [
                'name' => $primaryLine->receiver_name,
            ],
            'lines' => $lines->map(fn (DobaDailyData $line) => [
                'sku' => $line->sku,
                'title' => $line->product_name ?? $line->sku,
                'quantity' => $line->quantity,
                'amount' => $line->total_price ?? $line->item_price,
                'status' => $line->order_status,
            ])->values()->all(),
        ];
    }
}
