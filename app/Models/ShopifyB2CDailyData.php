<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopifyB2CDailyData extends Model
{
    use HasFactory;

    protected $table = 'shopify_b2c_daily_data';

    protected $fillable = [
        'order_id',
        'order_number',
        'line_item_id',
        'product_id',
        'variant_id',
        'order_date',
        'financial_status',
        'fulfillment_status',
        'sku',
        'product_title',
        'quantity',
        'price',
        'original_price',
        'discount_amount',
        'total_amount',
        'customer_name',
        'customer_email',
        'shipping_city',
        'shipping_country',
        'tracking_company',
        'tracking_number',
        'tracking_url',
        'source_name',
        'tags',
        'period',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'quantity' => 'integer',
    ];
}
