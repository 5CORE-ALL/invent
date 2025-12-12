<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SheinDailyData extends Model
{
    use HasFactory;

    protected $table = 'shein_daily_data';

    protected $fillable = [
        'order_type',
        'order_number',
        'exchange_order',
        'order_status',
        'shipment_mode',
        'urged_or_not',
        'is_it_lost',
        'whether_to_stay',
        'order_issue',
        'product_name',
        'product_description',
        'specification',
        'seller_sku',
        'shein_sku',
        'skc',
        'item_id',
        'product_status',
        'inventory_id',
        'exchange_id',
        'reason_for_replacement',
        'product_id_to_be_exchanged',
        'locked_or_not',
        'order_processed_on',
        'collection_deadline',
        'delivery_deadline',
        'delivery_time',
        'tracking_number',
        'sellers_package',
        'seller_currency',
        'product_price',
        'coupon_discount',
        'store_campaign_discount',
        'commission',
        'estimated_merchandise_revenue',
        'consumption_tax',
        'province',
        'city',
        'quantity',
    ];

    protected $casts = [
        'order_processed_on' => 'datetime',
        'collection_deadline' => 'datetime',
        'delivery_deadline' => 'datetime',
        'delivery_time' => 'datetime',
        'product_price' => 'decimal:2',
        'coupon_discount' => 'decimal:2',
        'store_campaign_discount' => 'decimal:2',
        'commission' => 'decimal:2',
        'estimated_merchandise_revenue' => 'decimal:2',
        'consumption_tax' => 'decimal:2',
        'quantity' => 'integer',
    ];
}
