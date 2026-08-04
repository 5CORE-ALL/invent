<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Temu2Order extends Model
{
    use HasFactory;

    protected $table = 'temu2_orders';

    protected $fillable = [
        'parent_order_sn',
        'parent_order_status',
        'parent_order_status_text',
        'parent_order_time',
        'expect_ship_latest_time',
        'parent_shipping_time',
        'latest_delivery_time',
        'order_update_time',
        'region_id',
        'site_id',
        'order_sn',
        'sku_id',
        'goods_id',
        'ext_code',
        'product_sku_id',
        'goods_name',
        'spec',
        'quantity',
        'original_order_quantity',
        'canceled_quantity_before_shipment',
        'order_base_amount',
        'order_total_amount',
        'amount_raw_json',
        'amount_fetched_at',
        'order_status',
        'order_status_text',
        'fulfillment_type',
        'order_payment_type',
        'thumb_url',
        'order_shipping_time',
        'raw_json',
        'fetch_window',
        'fetched_at',
        'shopify_order_id',
        'pushed_to_shopify_at',
        'import_status',
        'display_sku',
    ];

    protected $casts = [
        'parent_order_time' => 'datetime',
        'expect_ship_latest_time' => 'datetime',
        'parent_shipping_time' => 'datetime',
        'latest_delivery_time' => 'datetime',
        'order_update_time' => 'datetime',
        'order_shipping_time' => 'datetime',
        'fetched_at' => 'datetime',
        'amount_fetched_at' => 'datetime',
        'pushed_to_shopify_at' => 'datetime',
        'raw_json' => 'array',
        'order_base_amount' => 'decimal:2',
        'order_total_amount' => 'decimal:2',
    ];
}
