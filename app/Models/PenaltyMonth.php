<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenaltyMonth extends Model
{
    protected $fillable = ['user_id', 'month', 'points', 'rate', 'amount', 'closed_at'];

    protected $casts = [
        'points' => 'integer',
        'rate' => 'float',
        'amount' => 'float',
        'closed_at' => 'datetime',
    ];
}
