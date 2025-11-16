<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelDailyCount extends Model
{
    use HasFactory;

    protected $fillable = ['channel_name', 'counts'];

    protected $casts = [
        'counts' => 'array',
    ];
}
