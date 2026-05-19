<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CcHealthValue extends Model
{
    protected $table = 'cc_health_values';

    protected $fillable = [
        'channel_id',
        'value',
        'recorded_on',
        'recorded_at',
        'user_id',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'recorded_on' => 'date',
        'recorded_at' => 'datetime',
    ];

    public function channel()
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
