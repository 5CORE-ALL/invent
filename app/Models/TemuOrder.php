<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemuOrder extends Model
{
    use HasFactory;

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
        'order_status',
        'order_status_text',
        'fulfillment_type',
        'order_payment_type',
        'thumb_url',
        'order_shipping_time',
        'raw_json',
        'fetch_window',
        'fetched_at',
    ];

    protected $casts = [
        'parent_order_time' => 'datetime',
        'expect_ship_latest_time' => 'datetime',
        'parent_shipping_time' => 'datetime',
        'latest_delivery_time' => 'datetime',
        'order_update_time' => 'datetime',
        'order_shipping_time' => 'datetime',
        'fetched_at' => 'datetime',
    ];
}
