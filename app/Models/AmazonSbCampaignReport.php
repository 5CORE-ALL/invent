<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonSbCampaignReport extends Model
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

        'impressions', 'impressionsViews', 'clicks', 'cost', 'spend', 'costPerClick', 'costType',
        'sales', 'salesClicks', 'salesPromoted', 'salesPromotedClicks',
        'purchases', 'purchasesClicks', 'purchasesPromoted', 'purchasesPromotedClicks',
        'unitsSold', 'unitsSoldClicks',

        'purchases1d', 'purchases7d', 'purchases14d', 'purchases30d',
        'sales1d', 'sales7d', 'sales14d', 'sales30d',
        'unitsSoldClicks1d', 'unitsSoldClicks7d', 'unitsSoldClicks14d', 'unitsSoldClicks30d',
        'purchasesSameSku1d', 'purchasesSameSku7d', 'purchasesSameSku14d', 'purchasesSameSku30d',
        'attributedSalesSameSku1d', 'attributedSalesSameSku7d', 'attributedSalesSameSku14d', 'attributedSalesSameSku30d',
        'unitsSoldSameSku1d', 'unitsSoldSameSku7d', 'unitsSoldSameSku14d', 'unitsSoldSameSku30d',

        'newToBrandPurchases', 'newToBrandPurchasesClicks', 'newToBrandPurchasesRate', 'newToBrandPurchasesPercentage',
        'newToBrandSales', 'new_to_brand_sales_clicks', 'newToBrandSalesPercentage',
        'newToBrandUnitsSold', 'newToBrandUnitsSoldClicks', 'newToBrandUnitsSoldPercentage',

        'detailPageViews', 'detailPageViewsClicks', 'eCPAddToCart', 'eCPBrandSearch', 'campaignStatus',

        'brandedSearches', 'brandedSearchesClicks', 'brandedSearchesViews', 'brandedSearchRate',
        'addToCart', 'addToCartClicks', 'addToCartRate', 'addToCartViews',
        'addToList', 'addToListFromClicks', 'addToListFromViews',

        'qualifiedBorrows', 'qualifiedBorrowsFromClicks', 'qualifiedBorrowsFromViews',
        'royaltyQualifiedBorrows', 'royaltyQualifiedBorrowsFromClicks', 'royaltyQualifiedBorrowsFromViews',

        'kindleEditionNormalizedPagesRead14d', 'kindleEditionNormalizedPagesRoyalties14d',
        'campaignBudgetAmount', 'campaignBudgetCurrencyCode', 'campaignBudgetType',

        'video5SecondViews', 'video5SecondViewRate', 'videoCompleteViews',
        'videoFirstQuartileViews', 'videoMidpointViews', 'videoThirdQuartileViews', 'videoUnmutes',
        'viewabilityRate', 'viewClickThroughRate', 'viewableImpressions',

        'campaignBiddingStrategy', 'creativeType', 'tactic',
        'newToBrandDetailPageViewRate', 'newToBrandDetailPageViews', 'newToBrandDetailPageViewsClicks',
        'newToBrandECPDetailPageView', 'topOfSearchImpressionShare',
    ];
}
