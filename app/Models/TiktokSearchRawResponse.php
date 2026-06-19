<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TiktokSearchRawResponse extends Model
{
    protected $table = 'tiktok_search_raw_responses';

    protected $fillable = [
        'search_query',
        'marketplace',
        'region',
        'provider',
        'provider_run_id',
        'items_count',
        'raw_response',
    ];

    protected $casts = [
        'items_count' => 'integer',
    ];
}
