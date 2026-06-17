<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSalaryComponent extends Model
{
    protected $fillable = [
        'payroll_month_id',
        'user_id',
        'type',
        'label',
        'amount',
        'is_one_time',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_one_time' => 'boolean',
    ];

    public function payrollMonth(): BelongsTo
    {
        return $this->belongsTo(PayrollMonth::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
