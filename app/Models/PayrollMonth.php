<?php

namespace App\Models;

use Carbon\Carbon;
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

    /**
     * Earliest date a user may manually override (edit) this month's hours: the
     * 2nd day of the month *after* the payroll period ends. Until then last
     * month's working hours are still being finalised, so overrides are blocked
     * to stop premature/wrong edits.
     */
    public function overrideUnlockDate(): ?Carbon
    {
        if (! $this->period_end) {
            return null;
        }

        // period_end is the last day of the period month → +1 day lands on the
        // 1st of the next month, +1 more lands on the 2nd.
        return $this->period_end->copy()->addDay()->startOfMonth()->addDay()->startOfDay();
    }

    /** True while overrides are still locked (before the unlock date). */
    public function isOverrideLocked(): bool
    {
        $unlock = $this->overrideUnlockDate();

        return $unlock !== null && Carbon::now()->lt($unlock);
    }

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
