<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TikTokDailyData extends Model
{
    use HasFactory;

    protected $table = 'tiktok_daily_data';

    protected $fillable = [
        'order_id',
        'order_status',
        'sku',
        'product_name',
        'quantity',
        'unit_price',
        'total_amount',
        'shipping_fee',
        'platform_discount',
        'seller_discount',
        'platform_commission',
        'payment_fee',
        'net_sales',
        'buyer_name',
        'shipping_address',
        'tracking_number',
        'shipping_provider',
        'order_created_at',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'period',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'platform_discount' => 'decimal:2',
        'seller_discount' => 'decimal:2',
        'platform_commission' => 'decimal:2',
        'payment_fee' => 'decimal:2',
        'net_sales' => 'decimal:2',
        'order_created_at' => 'datetime',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];
}
