<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingHealthParameter extends Model
{
    protected $fillable = [
        'code',
        'label',
        'description',
        'value_type',
        'required_value',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function assessmentItems(): HasMany
    {
        return $this->hasMany(ShippingHealthAssessmentItem::class, 'parameter_id');
    }
}
