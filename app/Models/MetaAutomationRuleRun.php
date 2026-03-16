<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaAutomationRuleRun extends Model
{
    use HasFactory;

    protected $table = 'meta_automation_rule_runs';

    protected $fillable = [
        'rule_id',
        'user_id',
        'status',
        'started_at',
        'finished_at',
        'entities_evaluated',
        'conditions_matched',
        'actions_executed',
        'dry_run',
        'error_message',
        'execution_log',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'entities_evaluated' => 'integer',
        'conditions_matched' => 'integer',
        'actions_executed' => 'integer',
        'dry_run' => 'boolean',
        'execution_log' => 'array',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(MetaAutomationRule::class, 'rule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
