<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Temu3Order extends Model
{
    use HasFactory;

    protected $table = 'temu3_orders';

    protected $fillable = [
        'order_id',
        'order_status',
        'fulfillment_mode',
        'order_item_id',
        'order_item_status',
        'product_name_by_customer_order',
        'product_name',
        'variation',
        'contribution_sku',
        'sku_id',
        'quantity_purchased',
        'quantity_to_ship',
        'quantity_shipped',
        'quantity_canceled',
        'purchase_date',
        'latest_shipping_time',
        'latest_delivery_time',
        'activity_goods_base_price',
        'base_price_total',
        'tracking_number',
        'carrier',
        'order_settlement_status',
    ];

    protected $casts = [
        'purchase_date' => 'datetime',
        'latest_shipping_time' => 'datetime',
        'latest_delivery_time' => 'datetime',
        'quantity_purchased' => 'integer',
        'quantity_to_ship' => 'integer',
        'quantity_shipped' => 'integer',
        'quantity_canceled' => 'integer',
        'activity_goods_base_price' => 'decimal:2',
        'base_price_total' => 'decimal:2',
    ];
}
