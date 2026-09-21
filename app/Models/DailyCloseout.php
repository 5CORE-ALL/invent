<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyCloseout extends Model
{
    protected $table = 'daily_closeouts';

    protected $fillable = [
        'user_id',
        'check_date',
        'tasks_completed',
        'incomplete_reason',
        'tasks_answered_at',
        'dar_nudge_5am_at',
        'dar_nudge_530am_at',
    ];

    protected $casts = [
        'check_date' => 'date',
        'tasks_completed' => 'boolean',
        'tasks_answered_at' => 'datetime',
        'dar_nudge_5am_at' => 'datetime',
        'dar_nudge_530am_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
