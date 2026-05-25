<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPreviousRecord extends Model
{
    protected $fillable = [
        'user_id',
        'month_label',
        'gross_amount',
        'deductions_total',
        'net_amount',
        'notes',
        'imported_data',
        'imported_by',
        'imported_at',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'deductions_total' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'imported_data' => 'array',
        'imported_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
