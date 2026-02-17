<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReverbOrderMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_date',
        'status',
        'amount',
        'display_sku',
        'sku',
        'quantity',
        'order_number',
        'shopify_order_id',
        'pushed_to_shopify_at',
    ];

    protected $casts = [
        'pushed_to_shopify_at' => 'datetime',
    ];
}
