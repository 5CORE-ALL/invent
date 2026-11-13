<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Manually written Minutes of Meeting (one per meeting). */
class MeetingMinute extends Model
{
    protected $table = 'meeting_minutes';

    protected $fillable = [
        'meeting_id', 'discussion', 'complete_minutes', 'summary', 'decisions', 'issues',
        'created_by', 'updated_by',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
