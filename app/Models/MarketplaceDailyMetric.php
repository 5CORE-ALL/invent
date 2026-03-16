<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceDailyMetric extends Model
{
    protected $table = 'marketplace_daily_metrics';

    protected $fillable = [
        'channel',
        'date',
        'total_orders',
        'total_quantity',
        'total_revenue',
        'total_sales',
        'total_cogs',
        'total_pft',
        'pft_percentage',
        'roi_percentage',
        'avg_price',
        'l30_sales',
        'tacos_percentage',
        'n_pft',
        'n_roi',
        'kw_spent',
        'pmt_spent',
        'hl_spent',
        'total_commission',
        'total_fees',
        'net_proceeds',
        'extra_data',
    ];

    protected $casts = [
        'date' => 'date',
        'total_orders' => 'integer',
        'total_quantity' => 'integer',
        'total_revenue' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'total_cogs' => 'decimal:2',
        'total_pft' => 'decimal:2',
        'pft_percentage' => 'decimal:2',
        'roi_percentage' => 'decimal:2',
        'avg_price' => 'decimal:2',
        'l30_sales' => 'decimal:2',
        'tacos_percentage' => 'decimal:2',
        'n_pft' => 'decimal:2',
        'n_roi' => 'decimal:2',
        'kw_spent' => 'decimal:2',
        'pmt_spent' => 'decimal:2',
        'hl_spent' => 'decimal:2',
        'total_commission' => 'decimal:2',
        'total_fees' => 'decimal:2',
        'net_proceeds' => 'decimal:2',
        'extra_data' => 'array',
    ];

    // Channel constants
    const CHANNEL_AMAZON = 'Amazon';
    const CHANNEL_EBAY = 'eBay';
    const CHANNEL_TEMU = 'Temu';
    const CHANNEL_SHEIN = 'Shein';
    const CHANNEL_MERCARI = 'Mercari';
    const CHANNEL_ALIEXPRESS = 'AliExpress';

    public static function getChannels()
    {
        return [
            self::CHANNEL_AMAZON,
            self::CHANNEL_EBAY,
            self::CHANNEL_TEMU,
            self::CHANNEL_SHEIN,
            self::CHANNEL_MERCARI,
            self::CHANNEL_ALIEXPRESS,
        ];
    }

    // Scope to get metrics for a specific channel
    public function scopeForChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    // Scope to get metrics for a date range
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    // Get last 30 days metrics for a channel
    public static function getLast30Days($channel)
    {
        return self::forChannel($channel)
            ->forDateRange(now()->subDays(30), now())
            ->orderBy('date', 'desc')
            ->get();
    }

    // Get today's metrics for all channels
    public static function getTodayMetrics()
    {
        return self::where('date', now()->toDateString())
            ->get()
            ->keyBy('channel');
    }
}
