<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMediaNormalizedMetric extends Model
{
    protected $fillable = [
        'social_media_account_id',
        'social_media_post_id',
        'metric_date',
        'metric_name',
        'metric_value',
        'availability',
    ];

    protected $casts = [
        'metric_date' => 'date',
        'metric_value' => 'float',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialMediaAccount::class, 'social_media_account_id');
    }
}
