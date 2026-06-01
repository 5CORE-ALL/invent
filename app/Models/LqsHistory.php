<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LqsHistory extends Model
{
    use HasFactory;

    protected $table = 'lqs_history';

    protected $fillable = [
        'date',
        'total_inv',
        'total_ov',
        'avg_dil',
        'avg_lqs',
    ];

    protected $casts = [
        'date' => 'date',
        'total_inv' => 'decimal:2',
        'total_ov' => 'decimal:2',
        'avg_dil' => 'decimal:2',
        'avg_lqs' => 'decimal:2',
    ];
}
