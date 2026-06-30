<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePayrollPeriodLine extends Model
{
    protected $fillable = [
        'user_id',
        'period_from',
        'period_to',
        'manual_seconds',
        'hourly_rate',
        'currency',
        'adjustment',
        'updated_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'hourly_rate' => 'decimal:2',
        'adjustment' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
