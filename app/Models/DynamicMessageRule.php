<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynamicMessageRule extends Model
{
    public const FREQUENCIES = ['once' => 'Only once per person', 'daily' => 'At most once a day', 'weekly' => 'At most once a week'];
    public const OPERATORS = ['>', '>=', '<', '<=', '=', '!='];

    protected $fillable = [
        'name', 'event_key', 'metric', 'operator', 'threshold', 'title', 'body',
        'recipient_user_ids', 'frequency', 'allow_reply', 'is_active',
        'created_by', 'updated_by', 'last_run_at',
    ];

    protected $casts = [
        'recipient_user_ids' => 'array',
        'allow_reply' => 'boolean',
        'is_active' => 'boolean',
        'threshold' => 'float',
        'last_run_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(DynamicMessage::class, 'rule_id');
    }

    /** @return list<int> */
    public function recipientIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->recipient_user_ids ?? [])));
    }
}
