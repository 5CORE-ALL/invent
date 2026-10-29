<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomateTaskUpdateForm extends Model
{
    protected $table = 'automate_task_update_forms';

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

    public function submissions(): HasMany
    {
        return $this->hasMany(AutomateTaskUpdateSubmission::class, 'form_id');
    }
}
