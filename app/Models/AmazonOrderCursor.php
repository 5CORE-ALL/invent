<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonOrderCursor extends Model
{
    use HasFactory;

    protected $fillable = [
        'cursor_key',
        'next_token',
        'status',
        'started_at',
        'last_page_at',
        'completed_at',
        'orders_fetched',
        'pages_fetched',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_page_at' => 'datetime',
        'completed_at' => 'datetime',
        'orders_fetched' => 'integer',
        'pages_fetched' => 'integer',
    ];
}
