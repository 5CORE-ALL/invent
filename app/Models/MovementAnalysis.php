<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovementAnalysis extends Model
{
    protected $table = 'movement_analysis';

    protected $fillable = [
        'parent',
        'sku',
        'months',
        's_msl',
    ];

    protected $casts = [
        'months' => 'array',
    ];
}
