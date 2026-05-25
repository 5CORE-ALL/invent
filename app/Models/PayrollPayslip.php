<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPayslip extends Model
{
    protected $fillable = [
        'payroll_month_id',
        'user_id',
        'format',
        'data',
        'released_at',
    ];

    protected $casts = [
        'data' => 'array',
        'released_at' => 'datetime',
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
