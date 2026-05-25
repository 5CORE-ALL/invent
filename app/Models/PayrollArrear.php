<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollArrear extends Model
{
    protected $fillable = [
        'user_id',
        'payroll_month_id',
        'amount',
        'adjustment_type',
        'period_from',
        'period_to',
        'description',
        'status',
        'applied_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_from' => 'date',
        'period_to' => 'date',
        'applied_at' => 'datetime',
    ];

    public function signedAmount(): float
    {
        $amount = (float) $this->amount;

        return ($this->adjustment_type ?? 'add') === 'deduct' ? -$amount : $amount;
    }

    public function displayLabel(): string
    {
        $note = trim((string) ($this->description ?? ''));

        if ($note !== '') {
            return $note;
        }

        return ($this->adjustment_type ?? 'add') === 'deduct' ? 'Arrear (deduction)' : 'Arrear';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payrollMonth(): BelongsTo
    {
        return $this->belongsTo(PayrollMonth::class);
    }
}
