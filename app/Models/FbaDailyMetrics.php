<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FbaDailyMetrics extends Model
{
    protected $table = 'fba_daily_metrics';

    protected $fillable = [
        'record_date',
        'sales',
        'pft',
        'gpft',
        'price',
        'cvr',
        'views',
        'inv',
        'l30',
        'dil',
        'zero_sold',
        'ads_pct',
        'spend',
        'roi',
    ];

    protected $casts = [
        'record_date' => 'date',
        'sales'     => 'decimal:2',
        'pft'       => 'decimal:2',
        'gpft'      => 'decimal:2',
        'price'     => 'decimal:2',
        'cvr'       => 'decimal:2',
        'views'     => 'integer',
        'inv'       => 'integer',
        'l30'       => 'integer',
        'dil'       => 'decimal:2',
        'zero_sold' => 'integer',
        'ads_pct'   => 'decimal:2',
        'spend'     => 'decimal:2',
        'roi'       => 'decimal:2',
    ];
}
