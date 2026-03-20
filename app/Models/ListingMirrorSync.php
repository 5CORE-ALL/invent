<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListingMirrorSync extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'channel',
        'sync_type',
        'status',
        'error_message',
        'source_data',
        'target_data',
        'response_data',
        'synced_at',
    ];

    protected $casts = [
        'source_data' => 'array',
        'target_data' => 'array',
        'response_data' => 'array',
        'synced_at' => 'datetime',
    ];
}
