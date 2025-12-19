<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DobaDailyData extends Model
{
    use HasFactory;

    protected $table = 'doba_daily_data';

    protected $fillable = [
        'order_no',
        'platform_order_no',
        'order_time',
        'pay_time',
        'order_status',
        'order_type',
        'period',
        'item_no',
        'sku',
        'product_name',
        'quantity',
        'item_price',
        'total_price',
        'shipping_fee',
        'discount_amount',
        'platform_fee',
        'anticipated_income',
        'currency',
        'receiver_name',
        'receiver_phone',
        'receiver_email',
        'shipping_address1',
        'shipping_address2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country',
        'shipping_method',
        'carrier_name',
        'tracking_number',
        'ship_time',
        'delivery_time',
        'warehouse_code',
        'warehouse_name',
        'store_name',
        'platform_name',
        'seller_id',
        'seller_name',
        'order_item_json',
        'order_json',
    ];

    protected $casts = [
        'order_time' => 'datetime',
        'pay_time' => 'datetime',
        'ship_time' => 'datetime',
        'delivery_time' => 'datetime',
        'item_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'anticipated_income' => 'decimal:2',
    ];

    /**
     * Scope for L30 period
     */
    public function scopeL30($query)
    {
        return $query->where('period', 'l30');
    }

    /**
     * Scope for L60 period
     */
    public function scopeL60($query)
    {
        return $query->where('period', 'l60');
    }
}
