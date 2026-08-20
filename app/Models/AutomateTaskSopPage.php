<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomateTaskSopPage extends Model
{
    protected $table = 'automate_task_sop_pages';

    protected $fillable = [
        'automate_task_id',
        'title',
        'body',
        'source_link',
        'created_by',
        'updated_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
