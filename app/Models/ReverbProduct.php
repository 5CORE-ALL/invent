<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReverbProduct extends Model
{
    use HasFactory;

    protected $table = 'reverb_products';

    protected $fillable = [
        'sku',
        'reverb_listing_id',
        'listing_state',
        'product_title',
        'r_l30',
        'r_l60',
        'price',
        'views',
        'remaining_inventory',
        'bump_bid',
        'recommended_bid',
        'status',
    ];
}
