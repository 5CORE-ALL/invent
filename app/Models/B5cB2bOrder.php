<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class B5cB2bOrder extends Model
{
    protected $table = 'b5c_b2b_orders';

    protected $fillable = [
        'store_order_id',
        'status',
        'customer_email',
        'customer_name',
        'currency',
        'total',
        'tracking_reference',
        'ordered_at',
        'shopify_order_id',
        'shopify_imported_at',
        'payload',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'ordered_at' => 'datetime',
        'shopify_imported_at' => 'datetime',
        'payload' => 'array',
    ];
}
