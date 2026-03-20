<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SheinMetric extends Model
{
    use HasFactory;

    protected $table = 'shein_metrics';

    protected $fillable = [
        'sku',
        'product_name',
        'spu_name',
        'inventory',
        'price',
        'retail_price',
        'cost_price',
        'views',
        'rating',
        'review_count',
        'status',
        'description',
        'image_url',
        'category',
        'last_synced_at',
        'raw_data',
    ];

    protected $casts = [
        'inventory' => 'integer',
        'price' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'views' => 'integer',
        'rating' => 'decimal:2',
        'review_count' => 'integer',
        'last_synced_at' => 'datetime',
        'raw_data' => 'array',
    ];

    /**
     * Get the product stock mapping relationship (if needed)
     */
    public function productStockMapping()
    {
        return $this->hasOne(\App\Models\ProductStockMapping::class, 'sku', 'sku');
    }
}
