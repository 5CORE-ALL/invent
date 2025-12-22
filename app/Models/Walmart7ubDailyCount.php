<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Walmart7ubDailyCount extends Model
{
    use HasFactory;

    protected $table = 'walmart_7ub_daily_counts';

    protected $fillable = [
        'date',
        'pink_count',
        'red_count',
        'green_count',
    ];

    protected $casts = [
        'date' => 'date',
        'pink_count' => 'integer',
        'red_count' => 'integer',
        'green_count' => 'integer',
    ];
}

