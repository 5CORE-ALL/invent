<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dar extends Model
{
    protected $table = 'dars';

    protected $fillable = [
        'user_id',
        'report_date',
        'group',
        'task',
        'time_taken',
        'history',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'history' => 'array',
        'report_date' => 'date',
        'time_taken' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
