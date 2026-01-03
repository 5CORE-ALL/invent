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
        'ub1_pink_count',
        'ub1_red_count',
        'ub1_green_count',
        'combined_pink_count',
        'combined_red_count',
        'combined_green_count',
    ];

    protected $casts = [
        'date' => 'date',
        'pink_count' => 'integer',
        'red_count' => 'integer',
        'green_count' => 'integer',
        'ub1_pink_count' => 'integer',
        'ub1_red_count' => 'integer',
        'ub1_green_count' => 'integer',
        'combined_pink_count' => 'integer',
        'combined_red_count' => 'integer',
        'combined_green_count' => 'integer',
    ];
}

