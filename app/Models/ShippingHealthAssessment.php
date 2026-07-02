<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingHealthAssessment extends Model
{
    protected $fillable = [
        'channel',
        'channel_id',
        'health_score',
        'notes',
        'assessed_at',
        'user_id',
    ];

    protected $casts = [
        'health_score' => 'float',
        'assessed_at'  => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ShippingHealthAssessmentItem::class, 'assessment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
