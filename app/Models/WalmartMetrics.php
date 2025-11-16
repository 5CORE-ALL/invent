<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalmartMetrics extends Model
{
    use HasFactory;
    protected $table = 'walmart_metrics';

    protected $fillable = [
        'sku',
        'l30',
        'l30_amt',
        'l60',
        'l60_amt',
        'price',
        'stock',
    ];
}
