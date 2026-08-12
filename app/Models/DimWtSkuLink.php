<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DimWtSkuLink extends Model
{
    protected $table = 'dim_wt_sku_links';

    protected $fillable = [
        'parent',
        'group_key',
        'sku',
        'sku_norm',
        'fingerprint',
        'wt_act',
        'l',
        'w',
        'h',
        'updated_by',
    ];

    protected $casts = [
        'wt_act' => 'float',
        'l' => 'float',
        'w' => 'float',
        'h' => 'float',
    ];
}
