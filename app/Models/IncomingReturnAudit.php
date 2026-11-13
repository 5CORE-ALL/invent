<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomingReturnAudit extends Model
{
    protected $table = 'incoming_return_audits';

    protected $fillable = [
        'user_id',
        'user_name',
        'recovered_usd',
        'incentive_rs',
        'passed_count',
        'total_count',
        'items',
        'sample',
        'remarks',
    ];

    protected $casts = [
        'items' => 'array',
        'sample' => 'array',
        'recovered_usd' => 'float',
        'incentive_rs' => 'float',
        'passed_count' => 'integer',
        'total_count' => 'integer',
    ];
}
