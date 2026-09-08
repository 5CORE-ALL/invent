<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    // If your old table has a different name, specify it here
    // protected $table = 'your_old_table_name';

    protected $fillable = [
        'task_id',
        'parent_task_id',
        'subtask_order',
        'title',
        'group',
        'priority',
        'description',
        'eta_time',
        'etc_done',
        'is_missed',
        'is_missed_track',
        'is_automate_task',
        'completion_date',
        'completion_day',
        'start_date',
        'due_date',
        'split_tasks',
        'assign_to',
        'assignor',
        'link1',
        'link2',
        'link3',
        'link4',
        'link5',
        'link6',
        'link7',
        'link8',
        'link9',
        'image',
        'screenshots',
        'automate_task_id',
        'cl_id',
        'task_type',
        'schedule_type',
        'schedule_time',
        'status',
        'rework_reason',
        'report',
        'reference_link',
        'delete_rating',
        'delete_feedback',
        'order',
        'workspace',
        'is_data_from',
        'is_corrective_action',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'due_date' => 'datetime',
        'completion_date' => 'datetime',
        'schedule_time' => 'datetime',
        'is_missed' => 'boolean',
        'is_missed_track' => 'boolean',
        'is_automate_task' => 'boolean',
        'split_tasks' => 'boolean',
        'is_corrective_action' => 'boolean',
        'parent_task_id' => 'integer',
        'subtask_order' => 'integer',
        'screenshots' => 'array',
    ];

    // Helper methods to maintain compatibility with new code
    public function getAssignorAttribute($value)
    {
        // Return assignor name directly (it's stored as text in old table)
        return $value;
    }

    public function getAssigneeAttribute()
    {
        // Return assign_to as assignee
        return $this->assign_to;
    }

    public function getEtcMinutesAttribute()
    {
        // Map old eta_time to etc_minutes for compatibility
        return $this->eta_time;
    }

    public function getAtcAttribute()
    {
        // Map old etc_done to atc for compatibility
        return $this->etc_done;
    }

    public function getTidAttribute()
    {
        // Map old start_date to tid for compatibility
        return $this->start_date;
    }

    public function getCompletedAtAttribute()
    {
        // Map old completion_date to completed_at for compatibility
        return $this->completion_date;
    }

    // Relationships for backwards compatibility
    public function assignorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignor', 'name');
    }

    public function assigneeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assign_to', 'name');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id')->orderBy('subtask_order')->orderBy('id');
    }

    public function isSubtask(): bool
    {
        return $this->parent_task_id !== null && $this->parent_task_id > 0;
    }

    /**
     * All screenshot filenames: screenshots JSON plus legacy single image column.
     *
     * @return list<string>
     */
    public function screenshotFilenames(): array
    {
        $names = [];
        $raw = $this->getAttributes()['screenshots'] ?? null;
        if ($raw === null) {
            $raw = $this->screenshots ?? null;
        }
        if (is_array($raw)) {
            $names = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $names = $decoded;
            }
        }

        $image = trim((string) ($this->getAttributes()['image'] ?? ''));
        if ($image !== '' && ! in_array($image, $names, true)) {
            array_unshift($names, $image);
        }

        return array_values(array_filter(array_map(static function ($name) {
            $name = trim((string) $name);

            return $name !== '' && ! str_contains($name, '/') && ! str_contains($name, '\\')
                ? $name
                : '';
        }, $names)));
    }
}
