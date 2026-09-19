<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatHealthEvent extends Model
{
    protected $fillable = ['type', 'user_id', 'channel_id', 'meta'];

    protected $casts = [
        'meta' => 'array',
    ];
}
