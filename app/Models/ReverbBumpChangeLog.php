<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReverbBumpChangeLog extends Model
{
    protected $table = 'reverb_bump_change_log';

    public $timestamps = false;

    protected $fillable = [
        'run_id',
        'source',
        'action',
        'status',
        'sku',
        'reverb_listing_id',
        'sold',
        'inv',
        'price',
        'views',
        'old_s_bump',
        'old_live_bump',
        'new_bump',
        'reason',
        'message',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
