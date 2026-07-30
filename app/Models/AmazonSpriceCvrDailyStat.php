<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonSpriceCvrDailyStat extends Model
{
    protected $table = 'amazon_sprice_cvr_daily_stats';

    protected $fillable = [
        'snapshot_date',
        'red_count',
        'green_count',
        'pink_count',
        'increased_count',
        'decreased_count',
        'hold_count',
        'total_count',
        'low_cvr',
        'high_cvr',
        'rule_pies',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'red_count' => 'integer',
        'green_count' => 'integer',
        'pink_count' => 'integer',
        'increased_count' => 'integer',
        'decreased_count' => 'integer',
        'hold_count' => 'integer',
        'total_count' => 'integer',
        'low_cvr' => 'float',
        'high_cvr' => 'float',
        'rule_pies' => 'array',
    ];
}
