<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AliexpressDailyData extends Model
{
    use HasFactory;

    protected $table = 'aliexpress_daily_data';

    protected $fillable = [
        'order_id',
        'order_status',
        'owner',
        'buyer_name',
        'order_date',
        'payment_time',
        'payment_method',
        'supply_price',
        'product_total',
        'shipping_cost',
        'estimated_vat',
        'platform_collects',
        'order_amount',
        'ddp_tariff',
        'store_promotion',
        'store_direct_discount',
        'platform_coupon',
        'item_id',
        'product_information',
        'ean_code',
        'sku_code',
        'quantity',
        'order_note',
        'complete_shipping_address',
        'receiver_name',
        'buyer_country',
        'state_province',
        'city',
        'detailed_address',
        'zip_code',
        'national_address',
        'email',
        'phone',
        'mobile',
        'tax_number',
        'shipping_method',
        'shipping_deadline',
        'tracking_number',
        'shipping_time',
        'buyer_confirmation_time',
        'order_type',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'payment_time' => 'datetime',
        'shipping_deadline' => 'datetime',
        'shipping_time' => 'datetime',
        'buyer_confirmation_time' => 'datetime',
        'supply_price' => 'decimal:2',
        'product_total' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'estimated_vat' => 'decimal:2',
        'order_amount' => 'decimal:2',
        'ddp_tariff' => 'decimal:2',
        'store_promotion' => 'decimal:2',
        'store_direct_discount' => 'decimal:2',
        'platform_coupon' => 'decimal:2',
        'quantity' => 'integer',
    ];
}
