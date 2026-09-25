<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstagramShopSoldRaw extends Model
{
    protected $table = 'instagram_shop_sold_raw';

    protected $fillable = [
        'sale_date',
        'order_name',
        'sku',
        'product_title',
        'quantity',
        'sold_price',
        'gross_sales',
        'net_sales',
        'discounts',
        'returns',
        'sales_channel',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'quantity' => 'integer',
        'sold_price' => 'decimal:2',
        'gross_sales' => 'decimal:2',
        'net_sales' => 'decimal:2',
        'discounts' => 'decimal:2',
        'returns' => 'decimal:2',
    ];
}
