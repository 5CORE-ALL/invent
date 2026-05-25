<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSettlement extends Model
{
    protected $fillable = [
        'user_id',
        'last_working_date',
        'settlement_date',
        'earnings',
        'deductions',
        'net_settlement',
        'status',
        'notes',
        'processed_by',
    ];

    protected $casts = [
        'last_working_date' => 'date',
        'settlement_date' => 'date',
        'earnings' => 'array',
        'deductions' => 'array',
        'net_settlement' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
