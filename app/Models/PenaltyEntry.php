<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenaltyEntry extends Model
{
    protected $fillable = [
        'user_id', 'points', 'reason', 'source', 'rule_id', 'penalty_date', 'period_key', 'created_by',
    ];

    protected $casts = [
        'points' => 'integer',
        'penalty_date' => 'date',
    ];
}
