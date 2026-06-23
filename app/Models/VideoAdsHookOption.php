<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoAdsHookOption extends Model
{
    use HasFactory;

    protected $table = 'video_ads_hook_options';

    protected $fillable = ['name'];
}
