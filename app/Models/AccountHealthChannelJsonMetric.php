<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountHealthChannelJsonMetric extends Model
{
    protected $table = 'account_health_channel_json_metrics';

    protected $fillable = [
        'channel_id',
        'metrics',
    ];

    protected $casts = [
        'metrics' => 'array',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_id');
    }
}
