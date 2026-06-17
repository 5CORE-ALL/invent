<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CpHistory extends Model
{
    use HasFactory;

    protected $table = 'cp_histories';

    protected $fillable = [
        'sku',
        'old_cp',
        'new_cp',
        'is_increase',
        'reason',
        'changed_by',
        'approved',
        'approved_by',
        'approved_at',
        'archived',
        'archived_at',
    ];

    protected $casts = [
        'old_cp' => 'float',
        'new_cp' => 'float',
        'is_increase' => 'boolean',
        'approved' => 'boolean',
        'approved_at' => 'datetime',
        'archived' => 'boolean',
        'archived_at' => 'datetime',
    ];
}
