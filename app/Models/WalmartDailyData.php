<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalmartDailyData extends Model
{
    use HasFactory;

    protected $table = 'walmart_daily_data';

    protected $fillable = [
        'purchase_order_id',
        'customer_order_id',
        'order_date',
        'order_type',
        'mart_id',
        'is_replacement',
        'is_premium_order',
        'original_customer_order_id',
        'replacement_order_id',
        'seller_order_id',
        'order_line_number',
        'period',
        'sku',
        'upc',
        'gtin',
        'item_id',
        'product_name',
        'quantity',
        'condition',
        'unit_price',
        'currency',
        'tax_amount',
        'shipping_charge',
        'discount_amount',
        'fee_amount',
        'status',
        'all_statuses_json',
        'order_line_json',
        'status_date',
        'cancellation_reason',
        'refund_amount',
        'refund_reason',
        'customer_name',
        'customer_phone',
        'customer_email',
        'shipping_address1',
        'shipping_address2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country',
        'shipping_method',
        'ship_method_code',
        'carrier_name',
        'tracking_number',
        'estimated_delivery_date',
        'estimated_ship_date',
        'ship_date_time',
        'fulfillment_option',
        'ship_node_type',
        'ship_node_name',
        'pickup_location',
        'partner_id',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'status_date' => 'datetime',
        'estimated_delivery_date' => 'datetime',
        'estimated_ship_date' => 'datetime',
        'ship_date_time' => 'datetime',
        'unit_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_charge' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'is_replacement' => 'boolean',
        'is_premium_order' => 'boolean',
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
