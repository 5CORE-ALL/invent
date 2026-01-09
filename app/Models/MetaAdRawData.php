<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetaAdRawData extends Model
{
    use HasFactory;

    protected $table = 'meta_ads_raw_data';

    protected $fillable = [
        'ad_id',
        'ad_name',
        'campaign_id',
        'campaign_name',
        'adset_id',
        'status',
        'effective_object_story_id',
        'preview_shareable_link',
        'source_ad_id',
        'creative_data',
        'sync_date',
        'ad_created_time',
        'ad_updated_time',
        'raw_data',
    ];

    protected $casts = [
        'creative_data' => 'array',
        'raw_data' => 'array',
        'sync_date' => 'date',
        'ad_created_time' => 'datetime',
        'ad_updated_time' => 'datetime',
    ];
}
