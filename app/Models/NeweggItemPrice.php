<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NeweggItemPrice extends Model
{
    use HasFactory;

    protected $table = 'newegg_item_prices';

    protected $fillable = [
        'seller_part_number',
        'newegg_item_number',
        'country_code',
        'currency',
        'active',
        'msrp',
        'map',
        'checkout_map',
        'selling_price',
        'enable_free_shipping',
        'on_promotion',
        'limit_quantity',
        'raw_json',
    ];

    protected $casts = [
        'msrp'          => 'decimal:2',
        'map'           => 'decimal:2',
        'selling_price' => 'decimal:2',
        'raw_json'      => 'array',
    ];
}
