<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopifySku extends Model
{
    use HasFactory;

    protected $table = 'shopify_skus';
    
    protected $fillable = [
        'variant_id',
        'sku',
        'product_title',
        'variant_title',
        'product_link',
        'inv', // stock on hand in this app
        'quantity', // sold units (not available inventory)
        'price',
        'b2b_price',
        'b2c_price',
        'price_updated_manually_at',
        'image_src',
        'shopify_l30',
        'available_to_sell',
        'committed',        
        'on_hand',
    ];

    protected $dates = [
        'price_updated_manually_at',
    ];
}
