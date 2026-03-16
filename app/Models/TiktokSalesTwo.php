<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TiktokSalesTwo extends Model
{
    protected $table = 'tiktok_sales_two';

    protected $fillable = [
        'order_id',
        'order_status',
        'seller_sku',
        'product_name',
        'quantity',
        'unit_price',
        'order_amount',
        'order_date',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'order_amount' => 'decimal:2',
        'order_date' => 'datetime',
    ];
}
