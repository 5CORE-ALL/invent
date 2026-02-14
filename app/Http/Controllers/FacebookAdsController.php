<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ShopifySku;

class FacebookAdsController extends Controller
{
    public function getAds()
    {
        $accessToken = config('services.facebook.access_token');
        $adAccountId = config('services.facebook.ad_account_id');

        // dd($accessToken, $adAccountId);

        // Simple API call
        $response = Http::get("https://graph.facebook.com/v24.0/{$adAccountId}/ads", [
            'access_token' => $accessToken,
            'fields' => 'id,name,status,adset_id,campaign_id'
        ]);

        return $response->json();
    }

    public function getCampaigns()
    {
        $accessToken = config('services.facebook.access_token');
        $adAccountId = config('services.facebook.ad_account_id');

        $response = Http::get("https://graph.facebook.com/v19.0/{$adAccountId}/campaigns", [
            'access_token' => $accessToken,
            'fields' => 'id,name,status,objective'
        ]);

        return $response->json();
    }

    public function getAdSets()
    {
        $accessToken = config('services.facebook.access_token');
        $adAccountId = config('services.facebook.ad_account_id');

        $response = Http::get("https://graph.facebook.com/v19.0/{$adAccountId}/adsets", [
            'access_token' => $accessToken,
            'fields' => 'id,name,status,campaign_id,daily_budget'
        ]);

        return $response->json();
    }

    public function getInsights()
    {
        $accessToken = config('services.facebook.access_token');
        $adAccountId = config('services.facebook.ad_account_id');

        $response = Http::get("https://graph.facebook.com/v19.0/{$adAccountId}/insights", [
            'access_token' => $accessToken,
            'fields' => 'impressions,clicks,spend,ctr,cpc,conversions',
            'date_preset' => 'last_30d'
        ]);

        return $response->json();
    }

    public function facebookImageAds()
    {
        return view('marketing-masters.facebook.facebook-image-ads');
    }

    public function facebookImageAdsData() {
        $productMasters = DB::table('product_master')->orderBy('id', 'asc')->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;
            $row['s_l30']    = $shopify->shopify_l30 ?? 0;

            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function facebookVideoAds()
    {
        return view('marketing-masters.facebook.facebook-video-ads');
    }

    public function facebookVideoAdsData() {
        $productMasters = DB::table('product_master')->orderBy('id', 'asc')->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;
            $row['s_l30']    = $shopify->shopify_l30 ?? 0;

            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }
}
