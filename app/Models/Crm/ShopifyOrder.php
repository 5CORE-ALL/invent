<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyOrder extends Model
{
    protected $fillable = [
        'shopify_order_id',
        'shopify_customer_id',
        'total_price',
        'subtotal_price',
        'total_discounts',
        'total_tax',
        'shipping_price',
        'currency',
        'source_name',
        'source_identifier',
        'landing_site',
        'referring_site',
        'line_items_count',
        'order_status',
        'financial_status',
        'fulfillment_status',
        'cancelled_at',
        'order_date',
        'last_synced_at',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload' => 'json',
        'order_date' => 'datetime',
        'last_synced_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_price' => 'decimal:2',
        'subtotal_price' => 'decimal:2',
        'total_discounts' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'shipping_price' => 'decimal:2',
        'line_items_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function shopifyCustomer(): BelongsTo
    {
        return $this->belongsTo(ShopifyCustomer::class, 'shopify_customer_id', 'shopify_customer_id');
    }
}
