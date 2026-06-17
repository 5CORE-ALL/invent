<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TiktokVideoAd extends Model
{
    protected $table = 'tiktok_video_ads_sheet';

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
        'SPARK AD',
        'NON-SPARK AD',
        'CAROUSEL',
        'VIDEO SHOPPING',
    ];

    protected $casts = [
        'row_data' => 'array',
    ];
}
