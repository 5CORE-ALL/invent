<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonSpCampaignReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_id',
        'campaign_id',
        'campaignName',
        'ad_type',
        'report_date_range',
        'startDate',
        'endDate',
        'date',

        'impressions', 'clicks', 'cost', 'spend', 'purchases1d', 'purchases7d',
        'purchases14d', 'purchases30d', 'sales1d', 'sales7d', 'sales14d', 'sales30d',
        'unitsSoldClicks1d', 'unitsSoldClicks7d', 'unitsSoldClicks14d', 'unitsSoldClicks30d',
        'attributedSalesSameSku1d', 'attributedSalesSameSku7d', 'attributedSalesSameSku14d', 'attributedSalesSameSku30d',
        'unitsSoldSameSku1d', 'unitsSoldSameSku7d', 'unitsSoldSameSku14d', 'unitsSoldSameSku30d',
        'clickThroughRate', 'costPerClick', 'qualifiedBorrows', 'addToList',
        'campaignBudgetAmount', 'campaignBudgetCurrencyCode',
        'royaltyQualifiedBorrows', 'purchasesSameSku1d', 'purchasesSameSku7d', 'purchasesSameSku14d', 
        'purchasesSameSku30d', 'kindleEditionNormalizedPagesRead14d', 'kindleEditionNormalizedPagesRoyalties14d', 'campaignBiddingStrategy', 'campaignStatus',
        'sbid', 'yes_sbid', 'note', 'last_sbid', 'sbid_m', 'pink_dil_paused_at',
    ];
}
