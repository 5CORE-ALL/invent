<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LqsMarketplaceHistory extends Model
{
    protected $table = 'lqs_marketplace_history';

    protected $fillable = [
        'marketplace',
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
        'date' => 'date',
        'total_inv' => 'float',
        'total_l30' => 'float',
        'total_sessions' => 'float',
        'avg_dil' => 'float',
        'avg_lqs' => 'float',
        'avg_rating' => 'float',
        'lqs_below_9_count' => 'integer',
    ];
}
