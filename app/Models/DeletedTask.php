<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeletedTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_task_id',
        'title',
        'description',
        'group',
        'priority',
        'status',
        'assignor',
        'assign_to',
        'assignor_name',
        'assignee_name',
        'eta_time',
        'etc_done',
        'start_date',
        'completion_date',
        'completion_day',
        'split_tasks',
        'is_missed',
        'is_missed_track',
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
        'task_type',
        'rework_reason',
        'deleted_by_email',
        'deleted_by_name',
        'deleted_at',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'completion_date' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
