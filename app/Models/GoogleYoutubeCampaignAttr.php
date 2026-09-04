<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleYoutubeCampaignAttr extends Model
{
    protected $table = 'google_youtube_campaign_attrs';

    protected $fillable = [
        'campaign_id',
        'category',
        'audience',
        'landing',
    ];
}
