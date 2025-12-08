<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbaSkuDailyData extends Model
{
    use HasFactory;

    protected $table = 'fba_sku_daily_data';

    protected $fillable = [
        'sku',
        'record_date',
        'daily_data',
    ];

    protected $casts = [
        'record_date' => 'date',
        'daily_data' => 'array',
    ];
}











