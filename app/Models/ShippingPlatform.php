<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingPlatform extends Model
{
    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function reportLines(): HasMany
    {
        return $this->hasMany(ShippingReportLine::class, 'shipping_platform_id');
    }
}
