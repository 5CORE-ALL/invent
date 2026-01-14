<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonSdCampaignReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id', 'profile_id', 'report_date_range', 'ad_type',
        'addToCart', 'addToCartClicks', 'addToCartRate', 'addToCartViews',
        'addToList', 'addToListFromClicks', 'addToListFromViews',
        'qualifiedBorrows', 'qualifiedBorrowsFromClicks', 'qualifiedBorrowsFromViews',
        'royaltyQualifiedBorrows', 'royaltyQualifiedBorrowsFromClicks', 'royaltyQualifiedBorrowsFromViews',
        'brandedSearches', 'brandedSearchesClicks', 'brandedSearchesViews', 'brandedSearchRate',
        'campaignBudgetCurrencyCode', 'campaignName',
        'clicks', 'cost', 'date', 'detailPageViews', 'detailPageViewsClicks',
        'eCPAddToCart', 'eCPBrandSearch', 'endDate', 'impressions', 'impressionsViews',
        'newToBrandPurchases', 'newToBrandPurchasesClicks', 'newToBrandSalesClicks',
        'newToBrandUnitsSold', 'newToBrandUnitsSoldClicks',
        'purchases', 'purchasesClicks', 'purchasesPromotedClicks',
        'sales', 'salesClicks', 'salesPromotedClicks',
        'startDate', 'unitsSold', 'unitsSoldClicks',
        'videoCompleteViews', 'videoFirstQuartileViews', 'videoMidpointViews',
        'videoThirdQuartileViews', 'videoUnmutes', 'viewabilityRate', 'viewClickThroughRate','campaignStatus',
        'sbid', 'yes_sbid', 'note', 'last_sbid', 'sbid_m',
    ];
}
