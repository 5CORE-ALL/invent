<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalmartSkuDailyData extends Model
{
    protected $table = 'walmart_sku_daily_data';

    protected $fillable = [
        'sku',
        'record_date',
        'daily_data'
    ];

    protected $casts = [
        'daily_data' => 'array',
        'record_date' => 'date'
    ];

    /**
     * Get data for a specific SKU and date range
     */
    public static function getSkuData($sku, $startDate = null, $endDate = null)
    {
        $query = static::where('sku', $sku)
            ->orderBy('record_date', 'asc');

        if ($startDate) {
            $query->where('record_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('record_date', '<=', $endDate);
        }

        return $query->get();
    }

    /**
     * Get chart data for a specific SKU
     */
    public static function getChartData($sku, $days = 30)
    {
        $data = static::where('sku', $sku)
            ->where('record_date', '>=', now()->subDays($days))
            ->orderBy('record_date', 'asc')
            ->get();

        $chartData = [
            'dates' => [],
            'price' => [],
            'views' => [],
            'cvr_percent' => [],
            'ad_percent' => [],
            'orders' => []
        ];

        foreach ($data as $record) {
            $dailyData = $record->daily_data;
            $chartData['dates'][] = $record->record_date->format('Y-m-d');
            $chartData['price'][] = $dailyData['price'] ?? 0;
            $chartData['views'][] = $dailyData['views'] ?? 0;
            $chartData['cvr_percent'][] = $dailyData['cvr_percent'] ?? 0;
            $chartData['ad_percent'][] = $dailyData['ad_percent'] ?? 0;
            $chartData['orders'][] = $dailyData['orders'] ?? 0;
        }

        return $chartData;
    }
}