<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One period of one assignment: checked in ('done') or not ('missed', a task was generated).
 *
 * @property int $id
 * @property int $assignment_id
 * @property string $status 'done' | 'missed'
 */
class PageAttendanceEvent extends Model
{
    public const DONE = 'done';

    public const MISSED = 'missed';

    protected $table = 'page_attendance_events';

    protected $fillable = [
        'assignment_id', 'page_key', 'user_id', 'frequency_days', 'period_start', 'period_end',
        'status', 'visited_at', 'note', 'task_id',
    ];

    protected $casts = [
        'period_start' => 'date:Y-m-d',
        'period_end' => 'date:Y-m-d',
        'visited_at' => 'datetime',
        'frequency_days' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PageAttendanceAssignment::class, 'assignment_id');
    }
}
