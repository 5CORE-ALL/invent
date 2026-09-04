<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GoogleAdsCampaign extends Model
{
    use HasFactory;

    protected $table = 'google_ads_campaigns';

    protected $fillable = [
        'campaign_id',
        'campaign_name',
        'campaign_status',
        'campaign_primary_status',
        'campaign_primary_status_reasons',
        'campaign_serving_status',
        'advertising_channel_type',
        'experiment_type',
        'bidding_strategy_type',
        'payment_mode',
        'start_date',
        'end_date',
        'target_google_search',
        'target_search_network',
        'target_content_network',
        'target_partner_search_network',
        'shopping_merchant_id',
        'shopping_feed_label',
        'shopping_campaign_priority',
        'positive_geo_target_type',
        'negative_geo_target_type',
        'manual_cpc_enhanced_enabled',
        'budget_id',
        'budget_name',
        'budget_status',
        'budget_amount_micros',
        'budget_total_amount_micros',
        'budget_delivery_method',
        'budget_period',
        'budget_explicitly_shared',
        'budget_has_recommended_budget',
        'metrics_impressions',
        'metrics_clicks',
        'metrics_ctr',
        'metrics_average_cpc',
        'metrics_average_cpm',
        'metrics_average_cpe',
        'metrics_average_cpv',
        'metrics_cost_micros',
        'metrics_interactions',
        'metrics_interaction_rate',
        'metrics_all_conversions',
        'metrics_all_conversions_value',
        'metrics_conversions',
        'metrics_conversions_value',
        'metrics_cost_per_conversion',
        'metrics_cost_per_all_conversions',
        'metrics_value_per_conversion',
        'metrics_value_per_all_conversions',
        'metrics_search_absolute_top_impression_share',
        'metrics_search_impression_share',
        'metrics_search_rank_lost_impression_share',
        'metrics_search_budget_lost_impression_share',
        'metrics_video_views',
        'metrics_video_quartile_p25_rate',
        'metrics_video_quartile_p50_rate',
        'metrics_video_quartile_p75_rate',
        'metrics_video_quartile_p100_rate',
        'metrics_video_view_rate',
        'ga4_sold_units',
        'ga4_ad_sales',
        'ga4_actual_sold_units',
        'ga4_actual_revenue',
        'date',
    ];

    protected $casts = [
        'campaign_id' => 'string',
        'budget_id' => 'string',
        'start_date' => 'date',
        'end_date' => 'date',
        'date' => 'date',
        'target_google_search' => 'boolean',
        'target_search_network' => 'boolean',
        'target_content_network' => 'boolean',
        'target_partner_search_network' => 'boolean',
        'manual_cpc_enhanced_enabled' => 'boolean',
        'budget_explicitly_shared' => 'boolean',
        'budget_has_recommended_budget' => 'boolean',
        'metrics_impressions' => 'integer',
        'metrics_clicks' => 'integer',
        'metrics_ctr' => 'decimal:4',
        'metrics_average_cpc' => 'decimal:2',
        'metrics_average_cpm' => 'decimal:2',
        'metrics_average_cpe' => 'decimal:2',
        'metrics_average_cpv' => 'decimal:2',
        'metrics_cost_micros' => 'integer',
        'metrics_interactions' => 'integer',
        'metrics_interaction_rate' => 'decimal:4',
        'metrics_all_conversions' => 'decimal:2',
        'metrics_all_conversions_value' => 'decimal:2',
        'metrics_conversions' => 'decimal:2',
        'metrics_conversions_value' => 'decimal:2',
        'metrics_cost_per_conversion' => 'decimal:2',
        'metrics_cost_per_all_conversions' => 'decimal:2',
        'metrics_value_per_conversion' => 'decimal:2',
        'metrics_value_per_all_conversions' => 'decimal:2',
        'metrics_search_absolute_top_impression_share' => 'decimal:4',
        'metrics_search_impression_share' => 'decimal:4',
        'metrics_search_rank_lost_impression_share' => 'decimal:4',
        'metrics_search_budget_lost_impression_share' => 'decimal:4',
        'metrics_video_views' => 'integer',
        'metrics_video_quartile_p25_rate' => 'decimal:4',
        'metrics_video_quartile_p50_rate' => 'decimal:4',
        'metrics_video_quartile_p75_rate' => 'decimal:4',
        'metrics_video_quartile_p100_rate' => 'decimal:4',
        'metrics_video_view_rate' => 'decimal:4',
        'budget_amount_micros' => 'integer',
        'budget_total_amount_micros' => 'integer',
        'ga4_sold_units' => 'decimal:2',
        'ga4_ad_sales' => 'decimal:2',
        'ga4_actual_sold_units' => 'decimal:2',
        'ga4_actual_revenue' => 'decimal:2',
    ];

    /**
     * Resolve a GA4 purchase row to a google_ads_campaigns identity.
     * Campaign ID first (campaign-wise), then exact name, then closest partial name.
     * YouTube names ending " YT" prefer VIDEO so sales are not written onto a Shopping row.
     *
     * @return object{campaign_id: string, campaign_name: string, advertising_channel_type: string}|null
     */
    public static function matchFromGa4(?string $campaignId, string $campaignName): ?object
    {
        $campaignId = trim((string) $campaignId);
        if ($campaignId !== '' && strcasecmp($campaignId, '(not set)') !== 0) {
            $row = DB::table('google_ads_campaigns')
                ->where('campaign_id', $campaignId)
                ->select('campaign_id', 'campaign_name', 'advertising_channel_type')
                ->distinct()
                ->first();
            if ($row) {
                return $row;
            }
        }

        $campaignNameUpper = strtoupper(trim($campaignName));
        if ($campaignNameUpper === '' || $campaignNameUpper === '(NOT SET)') {
            return null;
        }

        $row = DB::table('google_ads_campaigns')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$campaignNameUpper])
            ->select('campaign_id', 'campaign_name', 'advertising_channel_type')
            ->distinct()
            ->first();
        if ($row) {
            return $row;
        }

        $query = DB::table('google_ads_campaigns')
            ->where(function ($q) use ($campaignNameUpper, $campaignName) {
                $clean = trim($campaignName);
                $q->where('campaign_name', 'LIKE', '%'.$clean.'%')
                    ->orWhereRaw('UPPER(TRIM(campaign_name)) LIKE ?', ['%'.$campaignNameUpper.'%']);
            });

        if (str_ends_with($campaignNameUpper, ' YT')) {
            $query->orderByRaw("CASE WHEN advertising_channel_type = 'VIDEO' THEN 0 ELSE 1 END");
        }

        return $query->orderByRaw('CHAR_LENGTH(campaign_name) ASC')
            ->select('campaign_id', 'campaign_name', 'advertising_channel_type')
            ->distinct()
            ->first();
    }
}
