<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountHealthDashboardEntry extends Model
{
    protected $table = 'account_health_dashboard_entries';

    protected $fillable = [
        'channel_id',
        'channel',
        'metric',
        'value_column',
        'old_value',
        'new_value',
        'user_id',
        'user_name',
        'entered_on',
    ];

    protected $casts = [
        'entered_on' => 'date',
    ];
}
