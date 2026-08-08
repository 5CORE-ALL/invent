<?php

namespace App\Models;

use Illuminate\Support\Facades\Schema;

/**
 * TikTok Shop 2 API order lines — same shape as TiktokOrder, separate shop.
 */
class Tiktok2Order extends TiktokOrder
{
    protected $table = 'tiktok2_orders';

    protected $fillable = [
        'order_id',
        'line_item_id',
        'order_status',
        'line_status',
        'seller_sku',
        'product_id',
        'sku_id',
        'product_name',
        'quantity',
        'original_price',
        'sale_price',
        'seller_discount',
        'platform_discount',
        'currency',
        'order_amount',
        'fulfillment_type',
        'delivery_type',
        'shipping_provider',
        'buyer_nickname',
        'shop_region',
        'order_created_at',
        'order_updated_at',
        'rts_time',
        'delivery_time',
        'collection_time',
        'raw_json',
        'fetched_at',
        'shopify_order_id',
        'import_status',
        'pushed_to_shopify_at',
        'tracking_pushed_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'original_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'seller_discount' => 'decimal:2',
        'platform_discount' => 'decimal:2',
        'order_amount' => 'decimal:2',
        'order_created_at' => 'datetime',
        'order_updated_at' => 'datetime',
        'rts_time' => 'datetime',
        'delivery_time' => 'datetime',
        'collection_time' => 'datetime',
        'fetched_at' => 'datetime',
        'pushed_to_shopify_at' => 'datetime',
        'tracking_pushed_at' => 'datetime',
        'raw_json' => 'array',
    ];

    public static function tableReady(): bool
    {
        return Schema::hasTable('tiktok2_orders');
    }
}
