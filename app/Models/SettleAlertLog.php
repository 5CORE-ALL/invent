<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log of settle alerts that were raised (used to avoid repeating the same alert).
 */
class SettleAlertLog extends Model
{
    protected $table = 'settle_alert_logs';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'settle_date' => 'date:Y-m-d',
        'channels' => 'array',
        'failed_channels' => 'array',
        'sent_at' => 'datetime',
    ];
}
