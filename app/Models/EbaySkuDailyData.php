<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Daily snapshot of eBay listing metrics per SKU (see {@see \App\Console\Commands\CollectEbayMetrics}).
 *
 * {@see $daily_data} typically includes cumulative `views` from the metrics API; PMT Ads derives
 * period views via day-over-day deltas and cumulative window math in {@see \App\Http\Controllers\Campaigns\EbayPMPAdsController::computeEbayDailyViewAggregates}.
 */
class EbaySkuDailyData extends Model
{
    use HasFactory;

    protected $table = 'ebay_sku_daily_data';

    protected $fillable = [
        'sku',
        'record_date',
        'daily_data',
    ];

    protected $casts = [
        'record_date' => 'date',
        'daily_data' => 'array',
    ];
}

