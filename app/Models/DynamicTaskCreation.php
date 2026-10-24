<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicTaskCreation extends Model
{
    protected $fillable = ['rule_id', 'assignee_user_id', 'task_id', 'title', 'dedupe_key', 'context'];

    protected $casts = ['context' => 'array'];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(DynamicTaskRule::class, 'rule_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id')->withTrashed();
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
}
