<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonDailyBadgeStat extends Model
{
    protected $table = 'amazon_daily_badge_stats';

    protected $fillable = [
        'snapshot_date',
        'sold_count',
        'zero_sold_count',
        'map_count',
        'nmap_count',
        'missing_count',
        'prc_gt_lmp_count',
        'campaign_count',
        'missing_campaign_count',
        'nra_count',
        'ra_count',
        'paused_count',
        'ub7_count',
        'ub7_ub1_count',
        'kw_spend',
        'hl_spend',
        'pt_spend',
        'total_pft',
        'total_sales',
        'total_spend',
        'gpft_pct',
        'npft_pct',
        'groi_pct',
        'nroi_pct',
        'tcos_pct',
        'total_l30_orders',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
    ];
}
