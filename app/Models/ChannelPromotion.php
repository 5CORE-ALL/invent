<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelPromotion extends Model
{
    use HasFactory;

    protected $fillable = ['channels', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
