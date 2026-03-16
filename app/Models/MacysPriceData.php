<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MacysPriceData extends Model
{
    use HasFactory;

    protected $table = 'macys_price_data';

    protected $fillable = [
        'sku',
        'offer_sku',
        'product_sku',
        'category_code',
        'category_label',
        'brand',
        'product_name',
        'offer_state',
        'price',
        'original_price',
        'quantity',
        'alert_threshold',
        'logistic_class',
        'activated',
        'available_start_date',
        'available_end_date',
        'favorite_offer',
        'discount_price',
        'discount_start_date',
        'discount_end_date',
        'lead_time_to_ship',
        'upc',
        'inactivity_reason',
        'fulfillment_center_code',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'quantity' => 'integer',
        'alert_threshold' => 'integer',
        'lead_time_to_ship' => 'integer',
        'activated' => 'boolean',
        'favorite_offer' => 'boolean',
        'available_start_date' => 'date',
        'available_end_date' => 'date',
        'discount_start_date' => 'date',
        'discount_end_date' => 'date',
    ];
}
