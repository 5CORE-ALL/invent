<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonDailySync extends Model
{
    use HasFactory;

    protected $fillable = [
        'sync_date',
        'status',
        'started_at',
        'completed_at',
        'last_page_at',
        'next_token',
        'orders_fetched',
        'pages_fetched',
        'items_fetched',
        'error_message',
        'retry_count',
    ];

    protected $casts = [
        'sync_date' => 'date',
        'started_at' => 'datetime',
        'last_page_at' => 'datetime',
        'completed_at' => 'datetime',
        'orders_fetched' => 'integer',
        'pages_fetched' => 'integer',
        'items_fetched' => 'integer',
        'retry_count' => 'integer',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_SKIPPED = 'skipped'; // For future dates

    /**
     * Scope to get pending or failed syncs
     */
    public function scopeNeedsSync($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_FAILED]);
    }

    /**
     * Scope to get completed syncs
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to get syncs for a date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('sync_date', [$startDate, $endDate]);
    }
}
