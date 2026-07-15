<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TiktokGParent extends Model
{
    protected $table = 'tiktok_g_parents';

    protected $fillable = [
        'parent',
        'g_parent',
        'user_id',
    ];
}
