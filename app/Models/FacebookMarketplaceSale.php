<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacebookMarketplaceSale extends Model
{
    use HasFactory;

    protected $table = 'facebook_marketplace_sales';

    protected $fillable = [
        'order_number',
        'sku',
        'qty_sold',
        'sold_price',
        'order_date',
        'notes',
    ];

    protected $casts = [
        'qty_sold'   => 'integer',
        'sold_price' => 'decimal:2',
        'order_date' => 'date',
    ];
}
