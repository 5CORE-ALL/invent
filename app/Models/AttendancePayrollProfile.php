<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePayrollProfile extends Model
{
    protected $fillable = [
        'user_id',
        'hourly_rate',
        'currency',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
