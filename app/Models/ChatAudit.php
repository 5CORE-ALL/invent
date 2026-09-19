<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatAudit extends Model
{
    protected $fillable = [
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'channel_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
