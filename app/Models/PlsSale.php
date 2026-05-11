<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlsSale extends Model
{
    use HasFactory;

    protected $table = 'pls_sales';

    protected $fillable = [
        'shopify_order_id',
        'order_number',
        'order_name',
        'shopify_line_item_id',
        'sku',
        'product_title',
        'variant_title',
        'quantity',
        'price',
        'total_amount',
        'discount_amount',
        'tax_amount',
        'financial_status',
        'fulfillment_status',
        'order_date',
        'processed_at',
        'fulfilled_at',
        'cancelled_at',
        'customer_email',
        'customer_name',
        'currency',
        'tags',
        'note',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'processed_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shopify_order_id' => 'integer',
        'shopify_line_item_id' => 'integer',
    ];

    /**
     * Scope to filter by SKU
     */
    public function scopeBySku($query, $sku)
    {
        return $query->where('sku', $sku);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('order_date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter paid orders
     */
    public function scopePaid($query)
    {
        return $query->where('financial_status', 'paid');
    }

    /**
     * Scope to filter fulfilled orders
     */
    public function scopeFulfilled($query)
    {
        return $query->where('fulfillment_status', 'fulfilled');
    }
}
