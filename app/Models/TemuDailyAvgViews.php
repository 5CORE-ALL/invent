<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemuDailyAvgViews extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'avg_views',
        'total_products',
        'total_views'
    ];

    protected $casts = [
        'date' => 'date',
        'avg_views' => 'decimal:2',
        'total_products' => 'integer',
        'total_views' => 'integer'
    ];
}
