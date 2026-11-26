<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person who must visit a page and check in every `frequency_days` days.
 *
 * @property int $id
 * @property string $page_key
 * @property int $user_id
 * @property int $frequency_days
 * @property \Carbon\Carbon $start_date
 * @property bool $active
 */
class PageAttendanceAssignment extends Model
{
    protected $table = 'page_attendance_assignments';

    protected $fillable = ['page_key', 'user_id', 'frequency_days', 'start_date', 'assigned_by', 'assignor_email', 'active'];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'active' => 'boolean',
        'frequency_days' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PageAttendanceEvent::class, 'assignment_id');
    }
}
