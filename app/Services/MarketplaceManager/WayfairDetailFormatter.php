<?php

namespace App\Services\MarketplaceManager;

use App\Models\WayfairDailyData;
use App\Models\WayfairPricingPrice;
use App\Models\ShopifySku;
use Illuminate\Support\Collection;

class WayfairDetailFormatter
{
    /**
     * @param  array<string, mixed>|null  $wfLive
     * @return array<string, mixed>
     */
    public function formatProduct(?array $wfLive, ?WayfairPricingPrice $product, ShopifySku $shopify): array
    {
        $wf = is_array($wfLive) ? $wfLive : [];
        $sku = (string) $shopify->sku;
        $linked = $product !== null && trim((string) $product->sku) !== '';
        $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromRow($shopify);
        $wfStock = $linked ? ($wf['inventory'] ?? $product?->wayfair_stock) : null;

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
                'quantity' => $wfStock,
                'last_synced_at' => $product?->updated_at,
            ],
            'wayfair' => [
                'product_id' => $wf['product_id'] ?? $product?->sku,
                'title' => $wf['title'] ?? $product?->sku ?? $sku,
                'status' => $wf['state'] ?? 'active',
                'stock' => $wfStock,
                'price' => $wf['price'] ?? $product?->price,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $orderRoot
     * @param  Collection<int, WayfairDailyData>  $lines
     * @return array<string, mixed>
     */
    public function formatOrder(array $orderRoot, Collection $lines, WayfairDailyData $primaryLine): array
    {
        $order = $orderRoot;

        return [
            'summary' => [
                'order_id' => (string) ($order['po_number'] ?? $primaryLine->po_number),
                'order_number' => $order['po_number'] ?? $primaryLine->po_number,
                'status' => $order['status'] ?? $primaryLine->status,
                'created' => $order['po_date'] ?? $primaryLine->po_date,
            ],
            'amounts' => [
                'total' => $primaryLine->total_price ?? $primaryLine->unit_price,
                'currency' => 'USD',
            ],
            'shipping' => [
                'city' => $primaryLine->customer_city,
                'state' => $primaryLine->customer_state,
                'country' => $primaryLine->customer_country,
                'tracking' => null,
                'carrier' => $primaryLine->carrier_code,
            ],
            'buyer' => [
                'name' => $primaryLine->customer_name,
            ],
            'lines' => $lines->map(fn (WayfairDailyData $line) => [
                'sku' => $line->sku,
                'title' => $line->sku,
                'quantity' => $line->quantity,
                'amount' => $line->total_price ?? $line->unit_price,
                'status' => $line->status,
            ])->values()->all(),
        ];
    }
}
