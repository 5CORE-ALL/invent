<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StdPricingSprcDil extends Model
{
    protected $table = 'std_pricing_sprc_dil';

    protected $fillable = [
        'rules',
        'cvr_adj',
        'clearance_nroi',
        'lmp_rules',
        'formula',
    ];

    protected $casts = [
        'rules' => 'array',
        'cvr_adj' => 'array',
        'clearance_nroi' => 'float',
        'lmp_rules' => 'array',
        'formula' => 'array',
    ];
}
