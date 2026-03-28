<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaireDailyData extends Model
{
    use HasFactory;

    protected $table = 'faire_daily_data';

    protected $fillable = [
        'order_date',
        'order_number',
        'purchase_order_number',
        'retailer_name',
        'address_1',
        'address_2',
        'city',
        'state',
        'zip_code',
        'country',
        'product_name',
        'option_name',
        'sku',
        'gtin',
        'status',
        'quantity',
        'wholesale_price',
        'retail_price',
        'ship_date',
        'scheduled_order_date',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'ship_date' => 'datetime',
        'scheduled_order_date' => 'datetime',
        'quantity' => 'integer',
        'wholesale_price' => 'decimal:2',
        'retail_price' => 'decimal:2',
    ];
}
