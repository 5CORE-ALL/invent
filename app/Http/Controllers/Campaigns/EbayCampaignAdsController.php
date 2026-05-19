<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class EbayCampaignAdsController extends Controller
{
    public function index()
    {
        $rule = DB::table('ebay_sbid_rules')->where('key', 'ebay1')->first();
        $ruleData = $rule ? json_decode($rule->rule, true) : $this->defaultRule();
        return view('campaign.ebay-campaign-ads', ['sbidRule' => $ruleData]);
    }

    public function getRule()
    {
        $rule = DB::table('ebay_sbid_rules')->where('key', 'ebay1')->first();
        return response()->json($rule ? json_decode($rule->rule, true) : $this->defaultRule());
    }

    public function saveRule(Request $request)
    {
        $bands = $request->input('bands', []);

        if (empty($bands) || !is_array($bands)) {
            return response()->json(['error' => 'Invalid rule data'], 422);
        }

        // Sort bands by scvr_max ascending
        usort($bands, fn($a, $b) => $a['scvr_max'] <=> $b['scvr_max']);

        $rule = ['bands' => $bands];

        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => 'ebay1'],
            ['rule' => json_encode($rule), 'updated_at' => now()]
        );

        return response()->json(['success' => true, 'rule' => $rule]);
    }

    public function pushSelected(Request $request)
    {
        $listingIds = $request->input('listing_ids', []);
        if (empty($listingIds)) {
            return response()->json(['error' => 'No listings selected'], 422);
        }

        // Load rule
        $sbidRule  = DB::table('ebay_sbid_rules')->where('key', 'ebay1')->first();
        $bands     = $sbidRule ? (json_decode($sbidRule->rule, true)['bands'] ?? []) : [];

        // Get ebay metrics for these listings
        $metrics = \App\Models\EbayMetric::whereIn('item_id', $listingIds)->get()->keyBy('item_id');

        // Load campaign ads for these listings
        $ads = DB::table('ebay_campaign_ads')
            ->whereIn('listing_id', $listingIds)
            ->whereNotNull('campaign_id')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->get()
            ->keyBy('listing_id');

        $results   = [];
        $success   = 0;
        $failed    = 0;
        $skipped   = 0;

        // Get eBay access token
        try {
            $service = new \App\Services\EbayApiService();
            $token   = $service->generateBearerToken();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token error: ' . $e->getMessage()], 500);
        }

        // Group by campaign_id
        $byCampaign = [];
        foreach ($listingIds as $lid) {
            $lid = (string)$lid;
            $ad  = $ads->get($lid);
            if (!$ad || !$ad->campaign_id) {
                $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'Not in a COST_PER_SALE campaign'];
                $skipped++;
                continue;
            }

            // Calculate bid from SCVR
            $metric = $metrics->get($lid);
            $views  = (float)($metric?->views ?? 0);
            $l30    = (float)($metric?->ebay_l30 ?? 0);
            $scvr   = $views > 0 ? ($l30 / $views) * 100 : 0;

            $newBid = $this->getBidFromBands($scvr, $bands);
            $byCampaign[$ad->campaign_id][] = ['listingId' => $lid, 'bidPercentage' => (string)$newBid];
        }

        // Push to eBay API per campaign
        foreach ($byCampaign as $campaignId => $requests) {
            try {
                $response = \Illuminate\Support\Facades\Http::withToken($token)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_bid_by_listing_id",
                        ['requests' => $requests]);

                if ($response->successful()) {
                    foreach ($requests as $r) {
                        $results[] = ['listing_id' => $r['listingId'], 'status' => 'pushed', 'bid' => $r['bidPercentage'] . '%'];
                        $success++;
                    }
                } else {
                    foreach ($requests as $r) {
                        $results[] = ['listing_id' => $r['listingId'], 'status' => 'failed', 'reason' => $response->status()];
                        $failed++;
                    }
                }
            } catch (\Exception $e) {
                foreach ($requests as $r) {
                    $results[] = ['listing_id' => $r['listingId'], 'status' => 'failed', 'reason' => $e->getMessage()];
                    $failed++;
                }
            }
        }

        return response()->json([
            'success' => $success,
            'failed'  => $failed,
            'skipped' => $skipped,
            'results' => $results,
        ]);
    }

    private function getBidFromBands(float $scvr, array $bands): float
    {
        foreach ($bands as $band) {
            if ($scvr <= (float)($band['scvr_max'] ?? 9999)) {
                return (float)($band['bid'] ?? 9.1);
            }
        }
        return (float)(end($bands)['bid'] ?? 2.1);
    }

    public function getCampaignList()
    {
        $campaigns = DB::table('ebay_campaign_ads')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->where('campaign_status', 'RUNNING')
            ->whereNotNull('campaign_id')
            ->select('campaign_id', 'campaign_name')
            ->distinct()
            ->orderBy('campaign_name')
            ->get();

        return response()->json($campaigns);
    }

    public function enrollInCampaign(Request $request)
    {
        $listingIds  = $request->input('listing_ids', []);
        $campaignId  = $request->input('campaign_id');

        if (empty($listingIds) || !$campaignId) {
            return response()->json(['error' => 'listing_ids and campaign_id required'], 422);
        }

        // Load rule
        $sbidRule = DB::table('ebay_sbid_rules')->where('key', 'ebay1')->first();
        $bands    = $sbidRule ? (json_decode($sbidRule->rule, true)['bands'] ?? []) : [];

        // Get metrics for SCVR calculation
        $metrics = \App\Models\EbayMetric::whereIn('item_id', $listingIds)
            ->get()->keyBy('item_id');

        try {
            $service = new \App\Services\EbayApiService();
            $token   = $service->generateBearerToken();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token error: ' . $e->getMessage()], 500);
        }

        $results = [];
        $success = 0;
        $failed  = 0;

        foreach ($listingIds as $lid) {
            $lid    = (string)$lid;
            $metric = $metrics->get($lid);
            $views  = (float)($metric?->views ?? 0);
            $l30    = (float)($metric?->ebay_l30 ?? 0);
            $scvr   = $views > 0 ? ($l30 / $views) * 100 : 0;
            $bid    = $this->getBidFromBands($scvr, $bands);

            try {
                // Create ad in campaign with listing_id + bid
                $resp = \Illuminate\Support\Facades\Http::withToken($token)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/ad", [
                        'listingId'     => $lid,
                        'bidPercentage' => (string)$bid,
                    ]);

                if ($resp->successful() || $resp->status() === 201) {
                    $adData = $resp->json();
                    // Update our local table
                    DB::table('ebay_campaign_ads')
                        ->where('listing_id', $lid)
                        ->whereNull('campaign_id')
                        ->update([
                            'campaign_id'      => $campaignId,
                            'funding_strategy' => 'COST_PER_SALE',
                            'campaign_status'  => 'RUNNING',
                            'bid_percentage'   => $bid,
                            'promote_with_ad'  => 'AD_ALREADY_CREATED',
                            'ad_id'            => $adData['adId'] ?? null,
                            'updated_at'       => now(),
                        ]);

                    $results[] = ['listing_id' => $lid, 'sku' => $metric?->sku, 'status' => 'enrolled', 'bid' => $bid . '%'];
                    $success++;
                } else {
                    $errMsg = $resp->json()['errors'][0]['message'] ?? $resp->status();
                    $results[] = ['listing_id' => $lid, 'sku' => $metric?->sku, 'status' => 'failed', 'reason' => $errMsg];
                    $failed++;
                }
            } catch (\Exception $e) {
                $results[] = ['listing_id' => $lid, 'sku' => $metric?->sku, 'status' => 'failed', 'reason' => $e->getMessage()];
                $failed++;
            }
        }

        return response()->json([
            'success' => $success,
            'failed'  => $failed,
            'results' => $results,
        ]);
    }

    public function pushSbid()
    {
        try {
            Artisan::call('ebay:update-suggestedbid');
            $output = Artisan::output();
            return response()->json(['success' => true, 'output' => $output]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function defaultRule(): array
    {
        return [
            'bands' => [
                ['scvr_max' => 4,    'bid' => 9.1, 'label' => 'Red',    'color' => '#dc3545'],
                ['scvr_max' => 7,    'bid' => 7.1, 'label' => 'Yellow', 'color' => '#ffc107'],
                ['scvr_max' => 13,   'bid' => 4.1, 'label' => 'Green',  'color' => '#198754'],
                ['scvr_max' => 9999, 'bid' => 2.1, 'label' => 'Pink',   'color' => '#e83e8c'],
            ]
        ];
    }

    public function getData(Request $request)
    {
        $query = DB::table('ebay_campaign_ads as ca')
            ->leftJoin('ebay_metrics as em', 'em.item_id', '=', 'ca.listing_id')
            ->select(
                'ca.*',
                // Use SKU from ebay_metrics if matched, fallback to listing_id
                DB::raw("COALESCE(em.sku, ca.listing_id) as resolved_sku"),
                DB::raw("CASE WHEN em.sku IS NOT NULL THEN 1 ELSE 0 END as sku_matched"),
                'em.ebay_price as metric_price',
                'em.views',
                'em.ebay_l30'
            );

        if ($request->filled('funding_strategy')) {
            $query->where('ca.funding_strategy', $request->funding_strategy);
        }
        if ($request->filled('campaign_status')) {
            $query->where('ca.campaign_status', $request->campaign_status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('em.sku', 'like', "%{$search}%")
                  ->orWhere('ca.listing_id', 'like', "%{$search}%")
                  ->orWhere('ca.campaign_name', 'like', "%{$search}%");
            });
        }

        $total = (clone $query)->count();
        $data  = $query->orderBy('ca.id', 'desc')->get();

        return response()->json([
            'total' => $total,
            'data'  => $data,
        ]);
    }
}
