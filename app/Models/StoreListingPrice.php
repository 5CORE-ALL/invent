<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreListingPrice extends Model
{
    protected $table = 'store_listing_prices';

    protected $fillable = [
        'store_product_id',
        'listing_key',
        'sku',
        'parent_sku',
        'slug',
        'name',
        'price',
        'special_price',
        'selling_price',
        'formatted_price',
        'special_price_type',
        'special_price_start',
        'special_price_end',
        'currency',
        'views',
        'sold',
        'qty',
        'is_in_stock',
        'url',
        'brand',
        'rating_percent',
        'base_image',
        'categories_json',
        'tags_json',
        'images_json',
        'is_variant',
        'is_default_variant',
        'store_variant_id',
        'variant_uid',
        'product_master_id',
        'matched',
        'raw_json',
        'synced_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'special_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'special_price_start' => 'datetime',
        'special_price_end' => 'datetime',
        'views' => 'integer',
        'sold' => 'integer',
        'qty' => 'integer',
        'is_in_stock' => 'boolean',
        'rating_percent' => 'decimal:2',
        'categories_json' => 'array',
        'tags_json' => 'array',
        'images_json' => 'array',
        'is_variant' => 'boolean',
        'is_default_variant' => 'boolean',
        'matched' => 'boolean',
        'raw_json' => 'array',
        'synced_at' => 'datetime',
    ];

    public function productMaster()
    {
        return $this->belongsTo(ProductMaster::class, 'product_master_id');
    }
}
