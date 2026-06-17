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
}
