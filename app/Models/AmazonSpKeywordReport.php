<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonSpKeywordReport extends Model
{
    use HasFactory;

    protected $table = 'amazon_sp_keyword_reports';

    protected $fillable = [
        'profile_id',
        'ad_type',
        'report_date_range',
        'campaign_id',
        'campaignName',
        'ad_group_id',
        'adGroupName',
        'keyword_id',
        'keyword',
        'targeting',
        'keywordType',
        'matchType',
        'adKeywordStatus',
        'campaignStatus',
        'impressions', 'clicks', 'cost', 'costPerClick', 'clickThroughRate',
        'purchases1d', 'purchases7d', 'purchases14d', 'purchases30d',
        'sales1d', 'sales7d', 'sales14d', 'sales30d',
        'unitsSoldClicks1d', 'unitsSoldClicks7d', 'unitsSoldClicks14d', 'unitsSoldClicks30d',
        'acosClicks14d', 'roasClicks14d',
        'startDate', 'endDate',
    ];
}
