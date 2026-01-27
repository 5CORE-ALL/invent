<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'group',
        'priority',
        'assignor_id',
        'assignee_id',
        'split_tasks',
        'flag_raise',
        'status',
        'etc_minutes',
        'atc',
        'rework_reason',
        'completed_at',
        'tid',
        'l1',
        'l2',
        'training_link',
        'video_link',
        'form_link',
        'form_report_link',
        'checklist_link',
        'pl',
        'process',
        'image',
    ];

    protected $casts = [
        'tid' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function assignor()
    {
        return $this->belongsTo(User::class, 'assignor_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
