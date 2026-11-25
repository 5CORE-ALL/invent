<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenaltySlab extends Model
{
    protected $fillable = ['position', 'max_points', 'rate'];

    protected $casts = [
        'position' => 'integer',
        'max_points' => 'integer',
        'rate' => 'float',
    ];
}
