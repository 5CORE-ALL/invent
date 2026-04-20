<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountHealthMetricValueHistory extends Model
{
    protected $table = 'account_health_metric_value_histories';

    protected $fillable = [
        'channel_id',
        'field_key',
        'value',
        'recorded_on',
    ];

    protected $casts = [
        'value' => 'float',
        'recorded_on' => 'date',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_id');
    }
}
