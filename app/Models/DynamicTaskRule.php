<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynamicTaskRule extends Model
{
    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'];

    protected $fillable = [
        'name', 'event_key', 'metric', 'operator', 'threshold', 'title', 'description',
        'task_group', 'priority', 'eta_minutes', 'tid_in_days', 'link',
        'recipient_user_ids', 'frequency', 'skip_if_open', 'is_active',
        'created_by', 'updated_by', 'last_run_at',
    ];

    protected $casts = [
        'recipient_user_ids' => 'array',
        'skip_if_open' => 'boolean',
        'is_active' => 'boolean',
        'threshold' => 'float',
        'eta_minutes' => 'integer',
        'tid_in_days' => 'integer',
        'last_run_at' => 'datetime',
    ];

    public function creations(): HasMany
    {
        return $this->hasMany(DynamicTaskCreation::class, 'rule_id');
    }

    /** @return list<int> */
    public function recipientIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->recipient_user_ids ?? [])));
    }
}
