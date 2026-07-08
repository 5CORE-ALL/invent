<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiShippingIncentive extends Model
{
    protected $table = 'kpi_shipping_incentives';

    protected $fillable = [
        'target',
        'amount',
        'user_id',
        'condition',
        'updated_by',
    ];
}
