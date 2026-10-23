<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackstageSpecification extends Model
{
    protected $table = 'backstage_specifications';

    protected $fillable = [
        'sku',
        'marketplace',
        'spec_key',
        'spec_key_norm',
        'spec_value',
        'category',
        'pushed_at',
    ];

    protected $casts = [
        'pushed_at' => 'datetime',
    ];
}
