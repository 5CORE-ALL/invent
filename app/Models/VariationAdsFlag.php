<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VariationAdsFlag extends Model
{
    protected $table = 'variation_ads_flags';

    public $timestamps = false;

    protected $fillable = [
        'sku',
        'col_key',
        'checked',
        'user_id',
        'updated_at',
    ];

    protected $casts = [
        'checked' => 'boolean',
        'updated_at' => 'datetime',
    ];
}
