<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReverbOrderMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_date',
        'order_paid_at',
        'status',
        'amount',
        'display_sku',
        'sku',
        'quantity',
        'order_number',
        'shopify_order_id',
        'pushed_to_shopify_at',
        'import_status',
    ];

    protected $casts = [
        'order_paid_at' => 'datetime',
        'pushed_to_shopify_at' => 'datetime',
    ];
}
