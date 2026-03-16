<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalmartPricingSales extends Model
{
    use HasFactory;

    protected $table = 'walmart_pricing';

    protected $fillable = [
        'sku',
        'item_id',
        'item_name',
        'current_price',
        'buy_box_base_price',
        'buy_box_total_price',
        'buy_box_win_rate',
        'competitor_price',
        'comparison_price',
        'price_differential',
        'price_competitive_score',
        'price_competitive',
        'repricer_strategy_type',
        'repricer_strategy_name',
        'repricer_status',
        'repricer_min_price',
        'repricer_max_price',
        'gmv30',
        'inventory_count',
        'fulfillment',
        'sales_rank',
        'l30_orders',
        'l30_qty',
        'l30_revenue',
        'l60_orders',
        'l60_qty',
        'l60_revenue',
        'traffic',
        'views',
        'page_views',
        'in_demand',
        'promo_status',
        'promo_details',
        'reduced_referral_status',
        'walmart_funded_status',
    ];

    protected $casts = [
        'current_price' => 'decimal:2',
        'buy_box_base_price' => 'decimal:2',
        'buy_box_total_price' => 'decimal:2',
        'buy_box_win_rate' => 'decimal:2',
        'competitor_price' => 'decimal:2',
        'comparison_price' => 'decimal:2',
        'price_differential' => 'decimal:2',
        'price_competitive_score' => 'decimal:2',
        'price_competitive' => 'boolean',
        'repricer_min_price' => 'decimal:2',
        'repricer_max_price' => 'decimal:2',
        'gmv30' => 'decimal:2',
        'l30_revenue' => 'decimal:2',
        'l60_revenue' => 'decimal:2',
        'in_demand' => 'boolean',
        'promo_details' => 'array',
    ];
}

