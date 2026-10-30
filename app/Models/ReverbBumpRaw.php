<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReverbBumpRaw extends Model
{
    protected $table = 'reverb_bump_raw';

    protected $fillable = [
        'sku',
        'reverb_listing_id',
        'http_status',
        'raw_response',
        'error',
        'fetched_at',
    ];

    protected $casts = [
        'fetched_at' => 'datetime',
    ];
}
