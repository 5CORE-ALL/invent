<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleMapsExtractorResult extends Model
{
    protected $fillable = [
        'search_id',
        'source',
        'name',
        'phone',
        'address',
        'website',
        'email',
        'social_links',
        'maps_url',
        'category',
        'rating',
        'reviews_count',
        'raw_payload',
        'shopify_customer_id',
    ];

    protected $casts = [
        'social_links' => 'array',
        'raw_payload' => 'array',
        'rating' => 'decimal:2',
        'reviews_count' => 'integer',
    ];

    public function search(): BelongsTo
    {
        return $this->belongsTo(GoogleMapsExtractorSearch::class, 'search_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if ($model->getKey()) {
                return;
            }
            $model->incrementing = false;
            $model->setAttribute($model->getKeyName(), ((int) static::query()->max($model->getKeyName())) + 1);
        });
    }
}
