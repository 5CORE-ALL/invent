<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TiktokCampaignReport extends Model
{
    use HasFactory;

    protected $table = 'tiktok_campaign_reports';

    protected $fillable = [
        'campaign_name',
        'campaign_id',
        'product_id',
        'report_range',
        'creative_type',
        'video_title',
        'video_id',
        'tiktok_account',
        'time_posted',
        'status',
        'custom_status',
        'authorization_type',
        'cost',
        'budget',
        'sku_orders',
        'cost_per_order',
        'gross_revenue',
        'roi',
        'in_roas',
        'currency',
        'product_ad_impressions',
        'product_ad_clicks',
        'product_ad_click_rate',
        'ad_conversion_rate',
        'video_view_rate_2_second',
        'video_view_rate_6_second',
        'video_view_rate_25_percent',
        'video_view_rate_50_percent',
        'video_view_rate_75_percent',
        'video_view_rate_100_percent',
    ];

    protected $casts = [
        'time_posted' => 'datetime',
        'cost' => 'decimal:2',
        'budget' => 'decimal:2',
        'sku_orders' => 'integer',
        'cost_per_order' => 'decimal:2',
        'gross_revenue' => 'decimal:2',
        'roi' => 'decimal:2',
        'in_roas' => 'decimal:2',
        'product_ad_impressions' => 'integer',
        'product_ad_clicks' => 'integer',
        'product_ad_click_rate' => 'decimal:4',
        'ad_conversion_rate' => 'decimal:4',
        'video_view_rate_2_second' => 'decimal:4',
        'video_view_rate_6_second' => 'decimal:4',
        'video_view_rate_25_percent' => 'decimal:4',
        'video_view_rate_50_percent' => 'decimal:4',
        'video_view_rate_75_percent' => 'decimal:4',
        'video_view_rate_100_percent' => 'decimal:4',
    ];
}
