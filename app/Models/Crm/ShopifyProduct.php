<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class ShopifyProduct extends Model
{
    protected $fillable = [
        'shopify_product_id',
        'title',
        'price',
        'inventory',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload' => 'json',
        'price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
