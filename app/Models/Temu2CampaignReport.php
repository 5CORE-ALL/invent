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

    /**
     * /temu2/ads Spend badge — one row per goods_id (latest), selected period.
     *
     * @return array{spend: float, clicks: int, impressions: int, sold: int, sales: float, rows: int}
     */
    public static function badgeTotals(?string $period = 'L30'): array
    {
        $period = strtoupper((string) $period);
        $query = static::query();
        if (in_array($period, ['L7', 'L30', 'L60'], true)) {
            $query->where('report_range', $period);
        }

        $ids = (clone $query)
            ->whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->selectRaw('MAX(id) as id')
            ->groupBy('goods_id')
            ->pluck('id');

        // Not Created is the column default for never-status-synced API leftovers.
        // Those rows stored Overall spend and inflated /temu2/ads vs Seller Center.
        $row = static::query()
            ->whereIn('id', $ids)
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'not created'")
            ->selectRaw('
                COUNT(*) AS row_count,
                COALESCE(SUM(spend), 0) AS spend,
                COALESCE(SUM(clicks), 0) AS clicks,
                COALESCE(SUM(impressions), 0) AS impressions,
                COALESCE(SUM(sub_orders), 0) AS sold,
                COALESCE(SUM(base_price_sales), 0) AS sales
            ')
            ->first();

        return [
            'spend' => round((float) ($row->spend ?? 0), 2),
            'clicks' => (int) ($row->clicks ?? 0),
            'impressions' => (int) ($row->impressions ?? 0),
            'sold' => (int) ($row->sold ?? 0),
            'sales' => round((float) ($row->sales ?? 0), 2),
            'rows' => (int) ($row->row_count ?? 0),
        ];
    }
}
