<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YoutubeVideoAd extends Model
{
    protected $table = 'youtube_video_ads_sheet';

    protected $fillable = [
        'import_batch_id',
        'source_filename',
        'row_index',
        'row_data',
        'ad_type',
        'uploaded_by',
    ];

    /** Allowed Ad Type dropdown values shown on the page. */
    public const AD_TYPES = [
        'SKIPPABLE',
        'NON-SKIPPABLE',
        'BUMPER',
        'IN-FEED',
        'SHORTS',
    ];

    protected $casts = [
        'row_data' => 'array',
    ];
}
