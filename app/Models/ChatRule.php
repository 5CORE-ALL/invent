<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatRule extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'metric',
        'operator',
        'threshold',
        'audience',
        'user_ids',
        'message',
        'send_time',
        'weekdays_only',
        'frequency',
        'last_run_at',
        'last_sent_count',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'weekdays_only' => 'boolean',
        'threshold' => 'float',
        'user_ids' => 'array',
        'last_run_at' => 'datetime',
        'last_sent_count' => 'integer',
    ];

    /** Command key stored on the bot messages this rule sends (also used to avoid sending twice). */
    public function botCommand(): string
    {
        return 'chat_rule_'.$this->id;
    }
}
