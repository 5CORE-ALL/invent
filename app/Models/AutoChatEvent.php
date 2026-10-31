<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ACE (Automated Chat Event) rule. See the create_auto_chat_events_tables migration.
 */
class AutoChatEvent extends Model
{
    protected $table = 'auto_chat_events';

    protected $fillable = [
        'name',
        'page',
        'trigger_type',
        'column_key',
        'operator',
        'value',
        'chat_channel_id',
        'chat_keyword',
        'task_title',
        'task_description',
        'task_group',
        'priority',
        'due_in_days',
        'assignee_mode',
        'assignee_id',
        'is_active',
        'created_by',
        'last_run_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'due_in_days' => 'integer',
        'last_run_at' => 'datetime',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(AutoChatEventRun::class, 'ace_id');
    }
}
