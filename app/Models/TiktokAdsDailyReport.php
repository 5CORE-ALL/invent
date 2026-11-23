<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TiktokAdsDailyReport extends Model
{
    protected $table = 'tiktok_ads_daily_reports';

    protected $guarded = [];

    protected $casts = [
        'stat_date' => 'date',
        'spend' => 'decimal:2',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'conversions' => 'integer',
        'raw' => 'array',
    ];
}
