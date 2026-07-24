<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Daily snapshot of TikTok listing metrics per SKU (TT1 / TT2).
 * Powers the Price chart on /tiktok-pricing and /tiktok-2-pricing
 * (same pattern as {@see EbaySkuDailyData} for /ebay-tabulator-view).
 */
class TiktokSkuDailyData extends Model
{
    protected $table = 'tiktok_sku_daily_data';

    protected $fillable = [
        'sku',
        'channel',
        'record_date',
        'daily_data',
    ];

    protected $casts = [
        'record_date' => 'date',
        'daily_data' => 'array',
    ];
}
