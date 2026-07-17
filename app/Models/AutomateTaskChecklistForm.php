<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomateTaskChecklistForm extends Model
{
    protected $table = 'automate_task_checklist_forms';

    protected $fillable = [
        'automate_task_id',
        'title',
        'questions',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'questions' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AutomateTaskChecklistSubmission::class, 'form_id');
    }
}
