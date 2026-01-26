<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemuCampaignReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_name',
        'goods_id',
        'report_range',
        'spend',
        'base_price_sales',
        'roas',
        'acos_ad',
        'cost_per_transaction',
        'sub_orders',
        'items',
        'net_total_cost',
        'net_declared_sales',
        'net_roas',
        'net_acos_ad',
        'net_cost_per_transaction',
        'net_orders',
        'net_number_pieces',
        'impressions',
        'clicks',
        'ctr',
        'cvr',
        'add_to_cart_number',
        'weekly_roas',
        'target',
        'status'
    ];

    protected $casts = [
        'spend' => 'decimal:2',
        'base_price_sales' => 'decimal:2',
        'roas' => 'decimal:2',
        'acos_ad' => 'decimal:2',
        'cost_per_transaction' => 'decimal:2',
        'sub_orders' => 'integer',
        'items' => 'integer',
        'net_total_cost' => 'decimal:2',
        'net_declared_sales' => 'decimal:2',
        'net_roas' => 'decimal:2',
        'net_acos_ad' => 'decimal:2',
        'net_cost_per_transaction' => 'decimal:2',
        'net_orders' => 'integer',
        'net_number_pieces' => 'integer',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'ctr' => 'decimal:2',
        'cvr' => 'decimal:2',
        'add_to_cart_number' => 'integer',
        'weekly_roas' => 'decimal:2',
        'target' => 'decimal:2'
    ];
}
