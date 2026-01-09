<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaAutomationRule extends Model
{
    use HasFactory;

    protected $table = 'meta_automation_rules';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'entity_type',
        'conditions',
        'actions',
        'is_active',
        'schedule',
        'dry_run_mode',
        'last_run_at',
        'total_runs',
        'total_actions_taken',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'dry_run_mode' => 'boolean',
        'last_run_at' => 'datetime',
        'total_runs' => 'integer',
        'total_actions_taken' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(MetaAutomationRuleRun::class, 'rule_id');
    }
}
