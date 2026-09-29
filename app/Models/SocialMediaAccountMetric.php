<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMediaAccountMetric extends Model
{
    protected $fillable = [
        'social_media_account_id',
        'metric_date',
        'metrics',
        'raw_metrics',
    ];

    protected $casts = [
        'metric_date' => 'date',
        'metrics' => 'array',
        'raw_metrics' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialMediaAccount::class, 'social_media_account_id');
    }
}
