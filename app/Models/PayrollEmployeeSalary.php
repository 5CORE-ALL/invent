<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEmployeeSalary extends Model
{
    protected $fillable = [
        'payroll_month_id',
        'user_id',
        'salary_pp',
        'increment',
        'other',
        'adv_inc_other',
        'hours_worked',
        'gross_amount',
        'lop_amount',
        'arrears_amount',
        'payments_total',
        'deductions_total',
        'net_amount',
        'bank_1',
        'bank_2',
        'upi_id',
        'is_new_hire',
    ];

    protected $casts = [
        'salary_pp' => 'decimal:2',
        'increment' => 'decimal:2',
        'other' => 'decimal:2',
        'adv_inc_other' => 'decimal:2',
        'hours_worked' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'lop_amount' => 'decimal:2',
        'arrears_amount' => 'decimal:2',
        'payments_total' => 'decimal:2',
        'deductions_total' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'is_new_hire' => 'boolean',
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
