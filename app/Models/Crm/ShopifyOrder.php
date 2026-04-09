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
        'currency',
        'order_status',
        'order_date',
        'last_synced_at',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload' => 'json',
        'order_date' => 'datetime',
        'last_synced_at' => 'datetime',
        'total_price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function shopifyCustomer(): BelongsTo
    {
        return $this->belongsTo(ShopifyCustomer::class, 'shopify_customer_id', 'shopify_customer_id');
    }
}
