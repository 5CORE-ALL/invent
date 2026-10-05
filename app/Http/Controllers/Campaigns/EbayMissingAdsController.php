<?php

namespace App\Http\Controllers\Campaigns;

use App\Models\EbayListingStatus;
use App\Support\Ads\EbayMissingListingQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * eBay 1 listings with no campaign, a price, and Shopify Inv > 0.
 */
class EbayMissingAdsController extends ListingMissingAdsController
{
    protected function cacheKey(): string
    {
        return 'ebay1_ads_missing_sidebar_count';
    }

    protected function pageTitle(): string
    {
        return 'eBay Missing Ads';
    }

    protected function pageSubtitle(): string
    {
        return 'eBay listings not in a campaign, with a price and Inv > 0.';
    }

    protected function adsUrl(): string
    {
        return route('ebay.campaign.ads');
    }

    protected function adsLabel(): string
    {
        return 'eBay Campaign Ads';
    }

    protected function idField(): string
    {
        return 'listing_id';
    }

    protected function idLabel(): string
    {
        return 'Listing ID';
    }

    public static function dataRouteName(): string
    {
        return 'ebay.ads.missing.data';
    }

    protected function countMissing(): int
    {
        return EbayMissingListingQuery::count('ebay_campaign_ads', 'ebay_metrics');
    }

    protected function collectMissingRows(bool $withImages = true): Collection
    {
        return EbayMissingListingQuery::rows('ebay_campaign_ads', 'ebay_metrics', $withImages);
    }

    public function getEbayMissingAdsData(): JsonResponse
    {
        return $this->data();
    }

    public function updateNrlData(Request $request): JsonResponse
    {
        $sku = $request->input('sku');
        $field = $request->input('field');
        $value = $request->input('value');

        $ebayListingStatus = EbayListingStatus::firstOrNew(['sku' => $sku]);
        $jsonData = $ebayListingStatus->value ?? [];
        if ($field === 'NRL') {
            $jsonData['nr_req'] = $value;
        } else {
            $jsonData[$field] = $value;
        }
        $ebayListingStatus->value = $jsonData;
        $ebayListingStatus->save();

        return response()->json([
            'status' => 200,
            'message' => 'Field updated successfully',
            'updated_json' => $jsonData,
        ]);
    }
}
