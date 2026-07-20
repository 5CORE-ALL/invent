<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerCareHealthStatusHistory extends Model
{
    protected $table = 'customer_care_health_status_histories';

    protected $fillable = [
        'snapshot_date',
        'red_count',
        'yellow_count',
        'green_count',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'red_count' => 'integer',
        'yellow_count' => 'integer',
        'green_count' => 'integer',
    ];
}
