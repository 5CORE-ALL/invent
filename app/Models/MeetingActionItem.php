<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingActionItem extends Model
{
    protected $fillable = [
        'meeting_id',
        'title',
        'description',
        'assignee_user_id',
        'assignee_name',
        'assignee_matched',
        'priority',
        'due_date',
        'task_id',
    ];

    protected $casts = [
        'assignee_matched' => 'boolean',
        'due_date' => 'date',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }
}
