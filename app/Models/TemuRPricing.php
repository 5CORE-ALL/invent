<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemuRPricing extends Model
{
    use HasFactory;

    protected $table = 'temu_r_pricing';

    protected $fillable = [
        'pricing_opportunity_type',
        'product_name',
        'goods_id',
        'sku_id',
        'variation',
        'product_status',
        'category',
        'current_base_price',
        'recommended_base_price',
        'date_created',
        'action',
    ];

    protected $casts = [
        'current_base_price' => 'decimal:2',
        'recommended_base_price' => 'decimal:2',
        'date_created' => 'datetime',
    ];
}
