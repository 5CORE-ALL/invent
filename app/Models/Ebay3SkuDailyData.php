<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Daily snapshot of eBay 3 listing metrics per SKU (see {@see \App\Console\Commands\CollectEbay3Metrics}).
 *
 * {@see $daily_data} typically includes price, views, l7_views, cvr_percent, ebay_l30,
 * inv, ovl30 — used by /ebay3-tabulator-view Sprc Dil history.
 */
class Ebay3SkuDailyData extends Model
{
    use HasFactory;

    protected $table = 'ebay3_sku_daily_data';

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
