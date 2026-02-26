<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SerpApiRawResponse extends Model
{
    use HasFactory;

    protected $table = 'serp_api_raw_responses';

    protected $fillable = [
        'search_query',
        'page',
        'marketplace',
        'request_params',
        'http_status',
        'raw_body',
        'success',
    ];

    protected $casts = [
        'request_params' => 'array',
        'success' => 'boolean',
        'page' => 'integer',
        'http_status' => 'integer',
    ];
}
