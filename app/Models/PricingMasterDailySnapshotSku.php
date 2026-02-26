<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingMasterDailySnapshotSku extends Model
{
    protected $table = 'pricing_master_daily_snapshots_sku';

    protected $fillable = [
        'snapshot_date',
        'sku',
        'inventory',
        'overall_l30',
        'avg_price',
        'avg_cvr',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'inventory' => 'integer',
        'overall_l30' => 'integer',
        'avg_price' => 'decimal:2',
        'avg_cvr' => 'decimal:2',
    ];
}
