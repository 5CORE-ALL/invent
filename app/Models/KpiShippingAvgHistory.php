<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiShippingAvgHistory extends Model
{
    protected $table = 'kpi_shipping_avg_history';

    protected $fillable = [
        'snapshot_date',
        'avg_pct',
    ];
}
