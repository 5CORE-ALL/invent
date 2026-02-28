<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonListingRaw extends Model
{
    protected $table = 'amazon_listings_raw';

    protected $fillable = [
        'report_imported_at',
        'seller_sku',
        'asin1',
        'condition_type',
        'condition_type_display',
        'item_name',
        'external_product_id',
        'raw_data',
        'thumbnail_image',
        'color',
        'material',
        'style',
        'size',
        'model_number',
        'model_name',
        'part_number',
        'manufacturer',
        'brand',
        'exterior_finish',
        'number_of_items',
        'assembly_required',
        'item_type_keyword',
        'generic_keyword',
        'product_description',
        'product_type',
        'quantity',
        'bullet_point',
        'handling_time',
        'merchant_shipping_group',
        'minimum_advertised_price',
        'your_price',
        'list_price',
        'country_of_origin',
        'warranty_description',
        'voltage',
        'noise_level',
        'item_dimensions',
        'included_components',
    ];

    protected $casts = [
    'report_imported_at' => 'datetime',
    'raw_data' => 'array',
    'assembly_required' => 'boolean',
    'bullet_point' => 'array',
    'item_dimensions' => 'array',      // ✅ ADD THIS
    'included_components' => 'array',  // ✅ ADD THIS
];

}
