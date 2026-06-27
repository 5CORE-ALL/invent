<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForecastAnalysisHistory extends Model
{
    protected $table = 'forecast_analysis_history';

    public $timestamps = false;

    protected $fillable = [
        'sku', 'parent', 'field', 'old_value', 'new_value', 'updated_by', 'updated_at',
    ];

    protected $casts = ['updated_at' => 'datetime'];
}
