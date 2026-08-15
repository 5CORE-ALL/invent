<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Daily snapshot of Reverb listing metrics per SKU (see {@see \App\Console\Commands\CollectReverbMetrics}).
 *
 * {@see $daily_data} typically includes rolling Views, RV L30, price, and CVR%.
 */
class ReverbSkuDailyData extends Model
{
    protected $table = 'reverb_sku_daily_data';

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
