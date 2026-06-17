<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepopSalesData extends Model
{
    protected $table = 'depop_sales_data';

    protected $fillable = [
        'sale_date',
        'buyer',
        'description',
        'size',
        'quantity',
        'item_price',
        'total',
        'usps_cost',
        'depop_fee',
        'sku_code',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'quantity' => 'integer',
        'item_price' => 'decimal:2',
        'total' => 'decimal:2',
        'usps_cost' => 'decimal:2',
        'depop_fee' => 'decimal:2',
    ];
}
