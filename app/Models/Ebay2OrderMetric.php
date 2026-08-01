<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ebay2OrderMetric extends Model
{
    use HasFactory;

    protected $table = 'ebay2_order_metrics';

    protected $fillable = [
        'order_id',
        'order_number',
        'order_date',
        'status',
        'sku',
        'product_id',
        'display_title',
        'quantity',
        'amount',
        'shopify_order_id',
        'pushed_to_shopify_at',
        'import_status',
        'raw_payload',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'pushed_to_shopify_at' => 'datetime',
        'raw_payload' => 'array',
    ];
}
