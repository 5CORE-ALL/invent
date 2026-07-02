<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingHealthAssessmentItem extends Model
{
    protected $fillable = [
        'assessment_id',
        'parameter_id',
        'parameter_label',
        'value_type',
        'required_value',
        'current_value',
        'meets_required',
    ];

    protected $casts = [
        'meets_required' => 'boolean',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ShippingHealthAssessment::class, 'assessment_id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(ShippingHealthParameter::class, 'parameter_id');
    }
}
