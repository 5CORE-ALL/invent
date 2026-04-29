<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoAdAudienceOption extends Model
{
    protected $table = 'video_ad_audience_options';

    protected $fillable = ['name', 'is_default'];
}
