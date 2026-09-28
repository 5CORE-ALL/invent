<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SocialMediaPost extends Model
{
    protected $fillable = [
        'social_media_account_id',
        'external_post_id',
        'content_type',
        'title',
        'published_at',
        'permalink',
        'media_url',
        'status',
        'metadata',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialMediaAccount::class, 'social_media_account_id');
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(SocialMediaPostMetric::class);
    }

    public function latestMetric(): HasOne
    {
        return $this->hasOne(SocialMediaPostMetric::class)->latestOfMany('metric_date');
    }
}
