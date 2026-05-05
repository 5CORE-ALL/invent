<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSalary extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'salary_pp',
        'increment',
        'other',
        'adv_inc_other',
        'bank_1',
        'bank_2',
        'upi_id',
    ];

    protected $casts = [
        'salary_pp' => 'decimal:2',
        'increment' => 'decimal:2',
        'other' => 'decimal:2',
        'adv_inc_other' => 'decimal:2',
    ];

    /**
     * Get the user that owns the salary.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
