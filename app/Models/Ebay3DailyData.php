<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ebay3DailyData extends Model
{
    use HasFactory;

    protected $table = 'ebay3_daily_data';

    protected $fillable = [
        'order_id',
        'legacy_order_id',
        'creation_date',
        'last_modified_date',
        'order_fulfillment_status',
        'order_payment_status',
        'sales_record_reference',
        'period',
        'line_item_id',
        'sku',
        'legacy_item_id',
        'legacy_variation_id',
        'title',
        'quantity',
        'line_item_fulfillment_status',
        'unit_price',
        'currency',
        'line_item_cost',
        'shipping_cost',
        'tax_amount',
        'discount_amount',
        'ebay_collect_and_remit_tax',
        'total_price',
        'total_fee',
        'total_marketplace_fee',
        'buyer_username',
        'buyer_email',
        'ship_to_name',
        'shipping_address1',
        'shipping_address2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country',
        'shipping_phone',
        'fulfillment_instructions_type',
        'shipping_carrier',
        'shipping_service',
        'tracking_number',
        'shipped_date',
        'actual_delivery_date',
        'cancel_status',
        'cancel_reason',
        'refund_amount',
        'seller_id',
        'line_item_json',
        'order_json',
    ];

    protected $casts = [
        'creation_date' => 'datetime',
        'last_modified_date' => 'datetime',
        'shipped_date' => 'datetime',
        'actual_delivery_date' => 'datetime',
        'unit_price' => 'decimal:2',
        'line_item_cost' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'ebay_collect_and_remit_tax' => 'decimal:2',
        'total_price' => 'decimal:2',
        'total_fee' => 'decimal:2',
        'total_marketplace_fee' => 'decimal:2',
        'refund_amount' => 'decimal:2',
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
