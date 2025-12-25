<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemuPricing extends Model
{
    use HasFactory;

    protected $table = 'temu_pricing';

    protected $fillable = [
        'category',
        'category_id',
        'product_name',
        'contribution_goods',
        'sku',
        'goods_id',
        'sku_id',
        'variation',
        'quantity',
        'base_price',
        'external_product_id_type',
        'external_product_id',
        'status',
        'detail_status',
        'date_created',
        'incomplete_product_information',
    ];

    protected $casts = [
        'date_created' => 'datetime',
        'base_price' => 'decimal:2',
        'quantity' => 'integer',
    ];
}
