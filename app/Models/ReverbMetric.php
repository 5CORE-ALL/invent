<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Marketplace Manager link-map for Reverb (mirrors aliexpress_metric).
 */
class ReverbMetric extends Model
{
    protected $table = 'reverb_metric';

    protected $fillable = [
        'product_id',
        'sku',
        'product_name',
        'price',
        'l30',
        'l60',
        'order_dates',
        'last_order_date',
        'bullet_points',
    ];

    protected $casts = [
        'order_dates' => 'array',
        'last_order_date' => 'datetime',
        'price' => 'decimal:2',
        'l30' => 'integer',
        'l60' => 'integer',
    ];
}
