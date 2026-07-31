<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Daily snapshot of eBay 2 listing metrics per SKU (see {@see \App\Console\Commands\CollectEbay2Metrics}).
 *
 * {@see $daily_data} typically includes price, views, l7_views, cvr_percent, ebay_l30 —
 * used by /ebay2-tabulator-view Price trend dots and SKU charts.
 */
class Ebay2SkuDailyData extends Model
{
    use HasFactory;

    protected $table = 'ebay2_sku_daily_data';

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
