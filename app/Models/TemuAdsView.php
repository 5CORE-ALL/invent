<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemuAdsView extends Model
{
    use HasFactory;

    protected $table = 'temu_ads_views';

    protected $fillable = [
        'goods_id',
        'sku',
        'goods_name',
        'spend',
        'net_total_cost',
        'base_price_sales',
        'roas',
        'acos',
        'cost_per_order',
        'sub_order_count',
        'items',
        'impressions',
        'clicks',
        'ctr',
        'cvr',
        'add_to_cart_count',
        'net_base_price_sales',
        'net_roas',
        'net_acos',
        'net_cost_per_order',
        'net_sub_order_count',
        'net_items',
    ];

    protected $casts = [
        'goods_id' => 'string',
        'spend' => 'decimal:2',
        'net_total_cost' => 'decimal:2',
        'base_price_sales' => 'decimal:2',
        'roas' => 'decimal:2',
        'acos' => 'decimal:2',
        'cost_per_order' => 'decimal:2',
        'sub_order_count' => 'integer',
        'items' => 'integer',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'ctr' => 'decimal:2',
        'cvr' => 'decimal:2',
        'add_to_cart_count' => 'integer',
        'net_base_price_sales' => 'decimal:2',
        'net_roas' => 'decimal:2',
        'net_acos' => 'decimal:2',
        'net_cost_per_order' => 'decimal:2',
        'net_sub_order_count' => 'integer',
        'net_items' => 'integer',
    ];
}
