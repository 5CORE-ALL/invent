<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoAdsMaster extends Model
{
    use HasFactory;

    protected $table = 'video_ads_master';

    protected $fillable = [
        'target_type',
        'target_value',
        'name',
        'channel',
        'audience',
        'hook_name',
        'hook',
        'link',
    ];
}
