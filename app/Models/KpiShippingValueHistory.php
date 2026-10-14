<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiShippingValueHistory extends Model
{
    protected $table = 'kpi_shipping_value_history';

    protected $fillable = [
        'channel',
        'snapshot_date',
        'on_time_pct',
    ];
}
