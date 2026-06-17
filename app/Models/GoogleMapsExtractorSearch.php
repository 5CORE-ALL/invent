<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleMapsExtractorSearch extends Model
{
    protected $fillable = [
        'user_id',
        'query',
        'location',
        'result_limit',
        'status',
        'results_count',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'result_limit' => 'integer',
        'results_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(GoogleMapsExtractorResult::class, 'search_id');
    }
}
