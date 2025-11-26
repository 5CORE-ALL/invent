<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbaMetricsHistory extends Model
{
    use HasFactory;

    protected $table = 'fba_metrics_history';

    protected $fillable = [
        'sku',
        'record_date',
        'price',
        'views',
        'gprft',
        'groi_percent',
        'tacos',
    ];

    protected $casts = [
        'record_date' => 'date',
        'price' => 'decimal:2',
        'views' => 'integer',
        'gprft' => 'decimal:2',
        'groi_percent' => 'decimal:2',
        'tacos' => 'decimal:2',
    ];
}
