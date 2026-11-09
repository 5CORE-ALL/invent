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
        'p_sales',
        'p_qty',
        'p_groi_pct',
        'p_npft',
        'p_nroi_pct',
        'p_ads_pct',
        'p_acos_pct',
        'p_views',
        'y_spend',
        'y_acos_pct',
        'y_tacos_pct',
        'y_sales',
        'y_cvr_pct',
        'y_bgt',
        'y_utilized_pct',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
    ];
}
