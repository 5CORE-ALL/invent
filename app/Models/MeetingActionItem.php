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
        'status',
        'completed_at',
        'created_by',
    ];

    public const STATUSES = [
        'pending' => ['Pending', 'warning'],
        'in_progress' => ['In Progress', 'info'],
        'completed' => ['Completed', 'success'],
    ];

    protected $casts = [
        'assignee_matched' => 'boolean',
        'due_date' => 'date',
        'completed_at' => \App\Casts\MeetingDateTime::class,
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
