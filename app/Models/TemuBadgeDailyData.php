<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemuBadgeDailyData extends Model
{
    protected $table = 'temu_badge_daily_data';

    protected $fillable = [
        'record_date',
        'total_sales',
        'total_orders',
        'total_quantity',
        'sku_count',
        'total_views',
        'avg_views',
        'total_spend',
        'avg_cvr_pct',
        'extra_data',
    ];

    protected $casts = [
        'record_date' => 'date',
        'total_sales' => 'decimal:2',
        'total_quantity' => 'integer',
        'total_orders' => 'integer',
        'sku_count' => 'integer',
        'total_views' => 'integer',
        'avg_views' => 'decimal:2',
        'total_spend' => 'decimal:2',
        'avg_cvr_pct' => 'decimal:2',
        'extra_data' => 'array',
    ];

    /**
     * Scope: last N days.
     */
    public function scopeLastDays($query, int $days)
    {
        return $query->where('record_date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('record_date', 'desc');
    }
}
