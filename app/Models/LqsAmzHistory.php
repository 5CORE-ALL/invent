<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LqsAmzHistory extends Model
{
    use HasFactory;

    protected $table = 'lqs_amz_history';

    protected $fillable = [
        'date',
        'total_inv',
        'total_l30',
        'total_sessions',
        'avg_dil',
        'avg_lqs',
        'avg_rating',
        'lqs_below_9_count',
    ];

    protected $casts = [
        'date'              => 'date',
        'total_inv'         => 'decimal:2',
        'total_l30'         => 'decimal:2',
        'total_sessions'    => 'decimal:2',
        'avg_dil'           => 'decimal:2',
        'avg_lqs'           => 'decimal:2',
        'avg_rating'        => 'decimal:2',
        'lqs_below_9_count' => 'integer',
    ];
}
