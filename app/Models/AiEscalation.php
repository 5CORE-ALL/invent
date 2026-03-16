<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiEscalation extends Model
{
    protected $fillable = [
        'user_id',
        'original_question',
        'domain',
        'assigned_senior_email',
        'senior_reply',
        'status',
        'answered_at',
        'junior_read_at',
        'email_notification_sent',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
        'junior_read_at' => 'datetime',
        'email_notification_sent' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
