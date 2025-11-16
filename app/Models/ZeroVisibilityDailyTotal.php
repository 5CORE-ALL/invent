<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZeroVisibilityDailyTotal extends Model
{
    protected $table = 'zero_visibility_daily_totals';
    protected $fillable = ['date', 'total'];

    protected $casts = [
        'date' => 'date',
        'total' => 'integer',
    ];
}
