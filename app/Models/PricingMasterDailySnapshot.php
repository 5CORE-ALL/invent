<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingMasterDailySnapshot extends Model
{
    protected $table = 'pricing_master_daily_snapshots';

    protected $fillable = [
        'snapshot_date',
        'total_inv',
        'total_ov_l30',
        'avg_price',
        'avg_cvr',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'total_inv' => 'integer',
        'total_ov_l30' => 'integer',
        'avg_price' => 'decimal:2',
        'avg_cvr' => 'decimal:2',
    ];
}
