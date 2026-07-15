<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YoutubeGParent extends Model
{
    protected $table = 'youtube_g_parents';

    protected $fillable = [
        'parent',
        'g_parent',
        'user_id',
    ];
}
