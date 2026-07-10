<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VariationAdsFlagDaily extends Model
{
    protected $table = 'variation_ads_flag_daily';

    public $timestamps = false;

    protected $fillable = [
        'snapshot_date',
        'col_key',
        'green_count',
        'total_count',
        'created_at',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'green_count' => 'integer',
        'total_count' => 'integer',
        'created_at' => 'datetime',
    ];
}
