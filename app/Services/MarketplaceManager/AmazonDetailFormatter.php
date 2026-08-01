<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonListingStatus;
use App\Models\AmazonOrder;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AmazonDetailFormatter
{
    /**
     * @return array<string, mixed>
     */
    public function formatProduct(?AmazonListingStatus $listing, ShopifySku $shopify): array
    {
        $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromRow($shopify);
        $linked = AmazonListingStatusHelper::isLinked($listing, (string) $shopify->sku);
        $value = AmazonListingStatusHelper::valueArray($listing);
        $amazonQty = null;
        if ($linked && Schema::hasTable('product_stock_mappings')) {
            $map = ProductStockMapping::query()->where('sku', $shopify->sku)->first();
            if ($map && $map->inventory_amazon !== null && $map->inventory_amazon !== '') {
                $amazonQty = (int) $map->inventory_amazon;
            }
        }

        return [
            'shopify' => [
                'sku' => $shopify->sku,
                'product_title' => $shopify->product_title,
                'variant_title' => $shopify->variant_title,
                'variant_id' => $shopify->variant_id,
                'product_link' => $shopify->product_link,
                'image' => $shopify->image_src,
                'available_to_sell' => $shopifyQty,
                'on_hand' => $shopify->on_hand,
                'b2c_price' => $shopify->b2c_price ?? $shopify->price,
                'price' => $shopify->price,
            ],
            'link' => [
                'product_id' => $linked ? AmazonListingStatusHelper::resolveProductId($listing) : null,
                'asin' => $linked ? AmazonListingStatusHelper::resolveAsin($listing) : null,
                'title' => $value['title'] ?? null,
                'remaining_inventory' => $amazonQty,
                'link_synced_at' => $listing?->updated_at,
            ],
            'amazon' => [
                'product_id' => $linked ? AmazonListingStatusHelper::resolveProductId($listing) : null,
                'asin' => $linked ? AmazonListingStatusHelper::resolveAsin($listing) : null,
                'title' => $value['title'] ?? null,
                'status' => $linked ? AmazonListingStatusHelper::resolveListingState($listing) : null,
                'stock' => $amazonQty,
                'nr_req' => $value['nr_req'] ?? null,
                'listing_status' => $value['listing_status'] ?? $value['status'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $orderRoot
     * @param  Collection<int, \App\Models\AmazonOrderItem>  $items
     * @return array<string, mixed>
     */
    public function formatOrder(array $orderRoot, Collection $items, AmazonOrder $order): array
    {
        $shipping = $orderRoot['ShippingAddress'] ?? $orderRoot['shippingAddress'] ?? [];
        $buyer = $orderRoot['BuyerInfo'] ?? $orderRoot['buyerInfo'] ?? [];

        return [
            'summary' => [
                'order_id' => (string) ($orderRoot['AmazonOrderId'] ?? $order->amazon_order_id),
                'status' => $orderRoot['OrderStatus'] ?? $order->status,
                'created' => $orderRoot['PurchaseDate'] ?? $order->order_date,
            ],
            'amounts' => [
                'total' => $order->total_amount,
                'currency' => $order->currency,
            ],
            'buyer' => [
                'email' => $buyer['BuyerEmail'] ?? $buyer['buyerEmail'] ?? null,
                'name' => $buyer['BuyerName'] ?? $buyer['buyerName'] ?? null,
            ],
            'shipping' => is_array($shipping) ? $shipping : [],
            'line_items' => $items->map(static fn ($item) => [
                'sku' => $item->sku,
                'asin' => $item->asin,
                'title' => $item->title,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ])->values()->all(),
        ];
    }
}
