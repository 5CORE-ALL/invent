<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonDailyOrder extends Model
{
    use HasFactory;

    protected $table = 'amazon_daily_orders';

    protected $fillable = [
        'asin',
        'sku',
        'price',
        'units_ordered',
        'order_date'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'order_date' => 'date'
    ];
}