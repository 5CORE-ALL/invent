<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelSprice extends Model
{
    protected $table = 'channel_sprices';

    protected $fillable = ['sku', 'channel', 'sprice'];

    protected $casts = ['sprice' => 'float'];
}
