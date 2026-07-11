<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonSpNegativeKeyword extends Model
{
    use HasFactory;

    public const LEVEL_CAMPAIGN = 'CAMPAIGN';

    public const LEVEL_AD_GROUP = 'AD_GROUP';

    protected $table = 'amazon_sp_negative_keywords';

    protected $fillable = [
        'profile_id',
        'level',
        'keyword_id',
        'campaign_id',
        'campaignName',
        'ad_group_id',
        'adGroupName',
        'keywordText',
        'matchType',
        'state',
    ];
}
