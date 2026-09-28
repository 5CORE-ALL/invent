<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMediaPostMetric extends Model
{
    protected $fillable = [
        'social_media_post_id',
        'metric_date',
        'metrics',
        'raw_metrics',
    ];

    protected $casts = [
        'metric_date' => 'date',
        'metrics' => 'array',
        'raw_metrics' => 'array',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialMediaPost::class, 'social_media_post_id');
    }
}
