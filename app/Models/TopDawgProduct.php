<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopDawgProduct extends Model
{
    use HasFactory;

    protected $table = 'topdawg_products';

    protected $fillable = [
        'sku',
        'topdawg_listing_id',
        'tdid',
        'image_src',
        'listing_state',
        'product_title',
        'r_l30',
        'r_l60',
        'price',
        'msrp',
        'views',
        'remaining_inventory',
    ];
}
