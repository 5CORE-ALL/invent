<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiShippingLink extends Model
{
    protected $table = 'kpi_shipping_links';

    protected $fillable = [
        'channel',
        'link',
        'on_time_pct',
        'updated_by',
    ];
}
