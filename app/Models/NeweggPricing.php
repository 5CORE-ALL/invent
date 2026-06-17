<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NeweggPricing extends Model
{
    use HasFactory;

    protected $table = 'newegg_pricing';

    protected $fillable = [
        'seller_part_number',
        'newegg_item_number',
        'country_code',
        // price
        'currency',
        'active',
        'msrp',
        'map',
        'checkout_map',
        'selling_price',
        'enable_free_shipping',
        'on_promotion',
        'limit_quantity',
        // inventory
        'available_quantity',
        'fulfillment_option',
        'inventory_active',
        'warehouse_allocation',
        // raw
        'price_raw_json',
        'inventory_raw_json',
    ];

    protected $casts = [
        'msrp'                 => 'decimal:2',
        'map'                  => 'decimal:2',
        'selling_price'        => 'decimal:2',
        'warehouse_allocation' => 'array',
        'price_raw_json'       => 'array',
        'inventory_raw_json'   => 'array',
    ];
}
