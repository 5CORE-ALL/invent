<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonSearchRawResponse extends Model
{
    use HasFactory;

    protected $table = 'amazon_search_raw_responses';

    protected $fillable = [
        'search_query',
        'marketplace',
        'page',
        'raw_response',
        'pages_count',
    ];

    protected $casts = [
        'page' => 'integer',
        'pages_count' => 'integer',
    ];
}
