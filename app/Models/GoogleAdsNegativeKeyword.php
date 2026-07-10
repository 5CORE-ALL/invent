<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoogleAdsNegativeKeyword extends Model
{
    use HasFactory;

    protected $table = 'google_ads_negative_keywords';

    public const LEVEL_CAMPAIGN = 'CAMPAIGN';

    public const LEVEL_AD_GROUP = 'AD_GROUP';

    public const LEVEL_SHARED_SET = 'SHARED_SET';

    protected $fillable = [
        'level',
        'customer_id',
        'campaign_id',
        'campaign_name',
        'ad_group_id',
        'ad_group_name',
        'shared_set_id',
        'shared_set_name',
        'criterion_id',
        'criterion_resource_name',
        'keyword_text',
        'match_type',
        'criterion_type',
        'status',
    ];

    protected $casts = [
        'customer_id' => 'string',
        'campaign_id' => 'string',
        'ad_group_id' => 'string',
        'shared_set_id' => 'string',
        'criterion_id' => 'string',
    ];
}
