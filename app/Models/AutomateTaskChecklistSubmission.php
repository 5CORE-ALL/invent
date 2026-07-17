<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomateTaskChecklistSubmission extends Model
{
    protected $table = 'automate_task_checklist_submissions';

    protected $fillable = [
        'form_id',
        'automate_task_id',
        'submitted_by',
        'answers',
        'submitted_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(AutomateTaskChecklistForm::class, 'form_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
