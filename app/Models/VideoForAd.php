<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoForAd extends Model
{
    protected $table = 'videos_for_ads';

    protected $fillable = [
        'sku',
        'ads_topic_story',
        'ads_what',
        'ads_why_purpose',
        'ads_audience',
        'ads_benefit_audience',
        'ads_location',
        'ads_language',
        'ads_script_link',
        'ads_script_link_status',
        'ads_video_en_link',
        'ads_video_en_link_status',
        'ads_video_es_link',
        'ads_video_es_link_status',
    ];
}
