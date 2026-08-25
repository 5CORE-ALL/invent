<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Temu2CampaignReport extends Model
{
    use HasFactory;

    protected $table = 'temu2_campaign_reports';

    protected $fillable = [
        'goods_name',
        'goods_id',
        'sku',
        'report_range',
        'spend',
        'base_price_sales',
        'roas',
        'in_roas',
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
        'status',
    ];

    protected $casts = [
        'goods_id' => 'string',
        'spend' => 'decimal:2',
        'base_price_sales' => 'decimal:2',
        'roas' => 'decimal:2',
        'in_roas' => 'decimal:2',
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
        'target' => 'decimal:2',
    ];

    public function displayAdStatus(): string
    {
        $status = trim((string) ($this->status ?? ''));
        if ($status === '' || strcasecmp($status, 'Unknown') === 0 || strcasecmp($status, 'Not Created') === 0) {
            return 'No ad';
        }

        return $status;
    }
}
