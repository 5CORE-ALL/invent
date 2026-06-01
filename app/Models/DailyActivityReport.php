<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyActivityReport extends Model
{
    protected $table = 'daily_activity_reports';

    protected $fillable = [
        'user_id',
        'channel_id',
        'report_date',
        'responsibilities',
        'comments',
        'submitted_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'responsibilities' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channelMaster(): BelongsTo
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_id');
    }
}
