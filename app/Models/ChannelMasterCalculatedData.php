<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelMasterCalculatedData extends Model
{
    use HasFactory;

    protected $table = 'channel_master_calculated_data';

    protected $fillable = [
        'channel',
        'sheet_link',
        'channel_percentage',
        'type',
        'base',
        'target',
        'missing_link',
        'addition_sheet',
        'l60_sales',
        'l30_sales',
        'yesterday_sales',
        'l7_sales',
        'growth',
        'l7_vs_30_pace',
        'l60_orders',
        'l30_orders',
        'total_quantity',
        'gprofit_pct',
        'gprofit_l60',
        'g_roi',
        'g_roi_l60',
        'total_profit',
        'n_pft',
        'n_roi',
        'tacos_percentage',
        'cogs',
        'total_ad_spend',
        'ads_percentage',
        'clicks',
        'ad_sold',
        'ad_sales',
        'cvr',
        'acos',
        'missing_ads',
        'kw_clicks', 'pt_clicks', 'hl_clicks', 'pmt_clicks', 'shopping_clicks', 'serp_clicks',
        'kw_sales', 'pt_sales', 'hl_sales', 'pmt_sales', 'shopping_sales', 'serp_sales',
        'kw_sold', 'pt_sold', 'hl_sold', 'pmt_sold', 'shopping_sold', 'serp_sold',
        'kw_acos', 'pt_acos', 'hl_acos', 'pmt_acos', 'shopping_acos', 'serp_acos',
        'kw_cvr', 'pt_cvr', 'hl_cvr', 'pmt_cvr', 'shopping_cvr', 'serp_cvr',
        'listed_count',
        'w_ads',
        'map',
        'miss',
        'nmap',
        'total_views',
        'nr',
        'update_flag',
        'red_margin',
        'account_health',
        'reviews_data',
        'calculated_at',
        'data_as_of',
    ];

    protected $casts = [
        'l60_sales' => 'decimal:2',
        'l30_sales' => 'decimal:2',
        'yesterday_sales' => 'decimal:2',
        'l7_sales' => 'decimal:2',
        'growth' => 'decimal:2',
        'l7_vs_30_pace' => 'decimal:2',
        'gprofit_pct' => 'decimal:2',
        'gprofit_l60' => 'decimal:2',
        'g_roi' => 'decimal:2',
        'g_roi_l60' => 'decimal:2',
        'total_profit' => 'decimal:2',
        'n_pft' => 'decimal:2',
        'n_roi' => 'decimal:2',
        'tacos_percentage' => 'decimal:2',
        'cogs' => 'decimal:2',
        'total_ad_spend' => 'decimal:2',
        'ads_percentage' => 'decimal:2',
        'ad_sales' => 'decimal:2',
        'cvr' => 'decimal:2',
        'acos' => 'decimal:2',
        'account_health' => 'array',
        'reviews_data' => 'array',
        'calculated_at' => 'datetime',
        'data_as_of' => 'datetime',
    ];

    /**
     * Get the latest calculated data for display
     */
    public static function getLatestData()
    {
        return self::orderBy('l30_sales', 'desc')->get();
    }

    /**
     * Get data filtered by type
     */
    public static function getByType(string $type)
    {
        return self::where('type', $type)
            ->orderBy('l30_sales', 'desc')
            ->get();
    }

    /**
     * Check if data is fresh (calculated today)
     */
    public static function isDataFresh(): bool
    {
        $latestCalculation = self::max('calculated_at');
        
        if (!$latestCalculation) {
            return false;
        }
        
        return \Carbon\Carbon::parse($latestCalculation)->isToday();
    }

    /**
     * Get last calculation time
     */
    public static function getLastCalculationTime()
    {
        return self::max('calculated_at');
    }
}
