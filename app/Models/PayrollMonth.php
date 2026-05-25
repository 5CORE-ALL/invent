<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollMonth extends Model
{
    protected $fillable = [
        'month_label',
        'period_start',
        'period_end',
        'status',
        'is_locked',
        'payslip_format',
        'payslips_released_at',
        'it_statements_released_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'is_locked' => 'boolean',
        'payslips_released_at' => 'datetime',
        'it_statements_released_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function employeeSalaries(): HasMany
    {
        return $this->hasMany(PayrollEmployeeSalary::class);
    }

    public function salaryComponents(): HasMany
    {
        return $this->hasMany(PayrollSalaryComponent::class);
    }

    public function paymentDeductions(): HasMany
    {
        return $this->hasMany(PayrollPaymentDeduction::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(PayrollPayslip::class);
    }

    public function arrears(): HasMany
    {
        return $this->hasMany(PayrollArrear::class);
    }
}
