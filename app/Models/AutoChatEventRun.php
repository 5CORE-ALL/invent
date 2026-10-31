<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One fired ACE: which row/message it fired for and the task it created.
 */
class AutoChatEventRun extends Model
{
    protected $table = 'auto_chat_event_runs';

    protected $fillable = [
        'ace_id',
        'subject_key',
        'subject_label',
        'source',
        'task_id',
        'status',
        'error',
    ];
}
