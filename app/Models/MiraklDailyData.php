<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MiraklDailyData extends Model
{
    use HasFactory;

    protected $table = 'mirakl_daily_data';

    protected $fillable = [
        'channel_name',
        'channel_id',
        'order_id',
        'channel_order_id',
        'order_line_id',
        'status',
        'order_created_at',
        'order_updated_at',
        'period',
        'sku',
        'product_title',
        'quantity',
        'unit_price',
        'currency',
        'tax_amount',
        'shipping_price',
        'shipping_tax',
        'billing_first_name',
        'billing_last_name',
        'billing_street',
        'billing_city',
        'billing_state',
        'billing_zip',
        'billing_country',
        'shipping_first_name',
        'shipping_last_name',
        'shipping_street',
        'shipping_city',
        'shipping_state',
        'shipping_zip',
        'shipping_country',
        'shipping_carrier',
        'shipping_method',
    ];

    protected $casts = [
        'order_created_at' => 'datetime',
        'order_updated_at' => 'datetime',
        'unit_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_price' => 'decimal:2',
        'shipping_tax' => 'decimal:2',
    ];

    /**
     * Scope for Macy's orders
     */
    public function scopeMacys($query)
    {
        return $query->where('channel_name', "Macy's, Inc.");
    }

    /**
     * Scope for Tiendamia orders
     */
    public function scopeTiendamia($query)
    {
        return $query->where('channel_name', 'Tiendamia');
    }

    /**
     * Scope for Best Buy USA orders
     */
    public function scopeBestBuyUsa($query)
    {
        return $query->where('channel_name', 'Best Buy USA');
    }

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
