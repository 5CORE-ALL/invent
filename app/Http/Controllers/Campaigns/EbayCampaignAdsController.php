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
        // Backfill threshold for existing rule rows saved before this field was added.
        if (!isset($ruleData['l7_views_threshold'])) {
            $ruleData['l7_views_threshold'] = 70;
        }

        $dil = DB::table('ebay_sbid_rules')->where('key', 'ebay1_dil')->first();
        $dilData = $dil ? json_decode($dil->rule, true) : $this->defaultDilRule();

        return view('campaign.ebay-campaign-ads', [
            'sbidRule' => $ruleData,
            'dilRule'  => $dilData,
        ]);
    }

    public function getRule()
    {
        $rule = DB::table('ebay_sbid_rules')->where('key', 'ebay1')->first();
        return response()->json($rule ? json_decode($rule->rule, true) : $this->defaultRule());
    }

    public function saveRule(Request $request)
    {
        $bands     = $request->input('bands', []);
        $threshold = $request->input('l7_views_threshold', 70);

        if (empty($bands) || !is_array($bands)) {
            return response()->json(['error' => 'Invalid rule data'], 422);
        }
        if (!is_numeric($threshold) || $threshold < 0) {
            return response()->json(['error' => 'l7_views_threshold must be a non-negative number'], 422);
        }

        // Sort bands by scvr_max ascending
        usort($bands, fn($a, $b) => $a['scvr_max'] <=> $b['scvr_max']);

        $rule = [
            'l7_views_threshold' => (float) $threshold,
            'bands'              => $bands,
        ];

        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => 'ebay1'],
            ['rule' => json_encode($rule), 'updated_at' => now()]
        );

        return response()->json(['success' => true, 'rule' => $rule]);
    }

    /**
     * Dilution rule — configurable DIL% color bands.
     * Stored in ebay_sbid_rules under key 'ebay1_dil' (no extra table needed).
     * DIL = (L30 sold / inventory) * 100. Bands evaluated ascending by dil_max,
     * first band where DIL <= dil_max wins.
     */
    public function getDilRule()
    {
        $rule = DB::table('ebay_sbid_rules')->where('key', 'ebay1_dil')->first();
        return response()->json($rule ? json_decode($rule->rule, true) : $this->defaultDilRule());
    }

    public function saveDilRule(Request $request)
    {
        $bands = $request->input('bands', []);

        if (empty($bands) || !is_array($bands)) {
            return response()->json(['error' => 'Invalid rule data'], 422);
        }

        usort($bands, fn($a, $b) => $a['dil_max'] <=> $b['dil_max']);

        $rule = ['bands' => $bands];

        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => 'ebay1_dil'],
            ['rule' => json_encode($rule), 'updated_at' => now()]
        );

        return response()->json(['success' => true, 'rule' => $rule]);
    }

    private function defaultDilRule(): array
    {
        return [
            'bands' => [
                ['dil_max' => 16.66, 'bid' => 9.1, 'label' => 'Red',    'color' => '#a00211'],
                ['dil_max' => 25,    'bid' => 7.1, 'label' => 'Yellow', 'color' => '#ffc107'],
                ['dil_max' => 50,    'bid' => 4.1, 'label' => 'Green',  'color' => '#28a745'],
                ['dil_max' => 9999,  'bid' => 2.1, 'label' => 'Pink',   'color' => '#e83e8c'],
            ]
        ];
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
        $dilBands  = $this->dilBands();

        // Get ebay metrics for these listings
        $metrics = \App\Models\EbayMetric::whereIn('item_id', $listingIds)->get()->keyBy('item_id');

        // Shopify inv/quantity for DIL, keyed by normalized SKU
        $shopifyMap = $this->shopifyByNormSku($metrics->pluck('sku')->filter()->unique()->values()->all());

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

            // Calculate bid from SCVR + DIL (Pink in either → push Pink bid)
            $metric = $metrics->get($lid);
            $views  = (float)($metric?->views ?? 0);
            $l30    = (float)($metric?->ebay_l30 ?? 0);
            $scvr   = $views > 0 ? ($l30 / $views) * 100 : 0;

            $shop = $metric ? ($shopifyMap[$this->normSku($metric->sku)] ?? null) : null;
            $inv  = (float)($shop->inv ?? 0);
            $qty  = (float)($shop->quantity ?? 0);
            $dil  = $inv > 0 ? ($qty / $inv) * 100 : 0;

            $newBid = $this->resolveCombinedBid($scvr, $bands, $dil, $dilBands, [
                'ebay_price' => (float)($metric?->ebay_price ?? 0),
                'ebay_l30'   => $l30,
                'views'      => $views,
            ]);
            if ($newBid <= 0) {
                $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'No SBID — 0 CVR & DIL not Pink'];
                $skipped++;
                continue;
            }
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

    /**
     * Dynamic SBID — bid from SCVR bands.
     * Returns 0.0 when SCVR (CVR) is 0 — no L30 sales means no signal to bid on.
     * Callers MUST treat 0 as "skip / no SBID".
     *
     * $ctx may carry extra metric values (ebay_price, ebay_l30, views) so that a
     * matched band can resolve its bid dynamically from a nested sub-rule.
     */
    private function getBidFromBands(float $scvr, array $bands, array $ctx = []): float
    {
        if ($scvr <= 0) {
            return 0.0;
        }
        $ctx['scvr'] = $scvr;
        foreach ($bands as $band) {
            if ($scvr <= (float)($band['scvr_max'] ?? 9999)) {
                return $this->resolveBandBid($band, $ctx);
            }
        }
        $last = end($bands);
        return $last ? $this->resolveBandBid($last, $ctx) : 2.1;
    }

    /**
     * Resolve a single band's bid. If the band carries a dynamic sub-rule, the bid
     * is chosen from its sub-bands using the configured metric value; otherwise the
     * band's flat bid is used.
     *
     * sub = ['metric' => 'ebay_price'|'scvr'|'ebay_l30'|'views',
     *        'bands'  => [['max' => float, 'bid' => float], ...]]
     */
    private function resolveBandBid(array $band, array $ctx): float
    {
        $sub = $band['sub'] ?? null;
        if (is_array($sub) && !empty($sub['metric']) && !empty($sub['bands']) && is_array($sub['bands'])) {
            $val = (float)($ctx[$sub['metric']] ?? 0);
            foreach ($sub['bands'] as $sb) {
                if ($val <= (float)($sb['max'] ?? 9999)) {
                    return (float)($sb['bid'] ?? $band['bid'] ?? 2.1);
                }
            }
            $lastSub = end($sub['bands']);
            return (float)($lastSub['bid'] ?? $band['bid'] ?? 2.1);
        }
        return (float)($band['bid'] ?? 9.1);
    }

    /**
     * Combined SCVR + DIL bid.
     * If EITHER the SCVR value or the DIL value lands in its Pink (catch-all / last)
     * band, the Pink bid is returned (e.g. 2.1) — even when both are Pink. Otherwise
     * the normal SCVR rule decides (and still returns 0 / skip when SCVR = 0).
     */
    private function resolveCombinedBid(float $scvr, array $sbidBands, float $dil, array $dilBands, array $ctx = []): float
    {
        if ($this->isPinkBand($dil, $dilBands)) {
            return $this->pinkBid($dilBands);
        }
        if ($this->isPinkBand($scvr, $sbidBands)) {
            return $this->pinkBid($sbidBands);
        }
        return $this->getBidFromBands($scvr, $sbidBands, $ctx);
    }

    /** True when $value falls in the last (catch-all / Pink) band. */
    private function isPinkBand(float $value, array $bands): bool
    {
        $n = count($bands);
        if ($n === 0) {
            return false;
        }
        foreach ($bands as $i => $band) {
            $max = (float)($band['scvr_max'] ?? $band['dil_max'] ?? 9999);
            if ($value <= $max) {
                return $i === $n - 1;
            }
        }
        return true;
    }

    /** Bid of the last (Pink / catch-all) band. */
    private function pinkBid(array $bands): float
    {
        $last = end($bands);
        return (float)($last['bid'] ?? 2.1);
    }

    /** Normalize a SKU for matching shopify_skus (unicode spaces → single space, upper). */
    private function normSku(?string $s): string
    {
        $s = (string)$s;
        $s = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xE2\x80\x87", "\xE2\x80\x8B"], ' ', $s);
        return strtoupper(preg_replace('/\s+/u', ' ', trim($s)));
    }

    /** Load DIL bands from rule (fallback to defaults). */
    private function dilBands(): array
    {
        $dil = DB::table('ebay_sbid_rules')->where('key', 'ebay1_dil')->first();
        return $dil ? (json_decode($dil->rule, true)['bands'] ?? $this->defaultDilRule()['bands']) : $this->defaultDilRule()['bands'];
    }

    /** Shopify rows keyed by normalized SKU for DIL (inv / quantity). */
    private function shopifyByNormSku(array $skus): array
    {
        $map = [];
        foreach (\App\Models\ShopifySku::whereIn('sku', $skus)->get() as $s) {
            $k = $this->normSku($s->sku);
            if ($k !== '' && !isset($map[$k])) {
                $map[$k] = $s;
            }
        }
        return $map;
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
        $dilBands = $this->dilBands();

        // Get metrics for SCVR calculation
        $metrics = \App\Models\EbayMetric::whereIn('item_id', $listingIds)
            ->get()->keyBy('item_id');

        // Shopify inv/quantity for DIL, keyed by normalized SKU
        $shopifyMap = $this->shopifyByNormSku($metrics->pluck('sku')->filter()->unique()->values()->all());

        try {
            $service = new \App\Services\EbayApiService();
            $token   = $service->generateBearerToken();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token error: ' . $e->getMessage()], 500);
        }

        $results = [];
        $success = 0;
        $failed  = 0;

        $skipped = 0;
        foreach ($listingIds as $lid) {
            $lid    = (string)$lid;
            $metric = $metrics->get($lid);
            $views  = (float)($metric?->views ?? 0);
            $l30    = (float)($metric?->ebay_l30 ?? 0);
            $scvr   = $views > 0 ? ($l30 / $views) * 100 : 0;

            $shop = $metric ? ($shopifyMap[$this->normSku($metric->sku)] ?? null) : null;
            $inv  = (float)($shop->inv ?? 0);
            $qty  = (float)($shop->quantity ?? 0);
            $dil  = $inv > 0 ? ($qty / $inv) * 100 : 0;

            $bid    = $this->resolveCombinedBid($scvr, $bands, $dil, $dilBands, [
                'ebay_price' => (float)($metric?->ebay_price ?? 0),
                'ebay_l30'   => $l30,
                'views'      => $views,
            ]);

            if ($bid <= 0) {
                $results[] = ['listing_id' => $lid, 'sku' => $metric?->sku, 'status' => 'skipped', 'reason' => 'No SBID — 0 CVR & DIL not Pink'];
                $skipped++;
                continue;
            }

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
            'skipped' => $skipped,
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
            'l7_views_threshold' => 70,
            'bands' => [
                ['scvr_max' => 4,    'bid' => 10.1, 'label' => 'Red',    'color' => '#dc3545'],
                ['scvr_max' => 7,    'bid' => 8.1,  'label' => 'Yellow', 'color' => '#ffc107'],
                ['scvr_max' => 13,   'bid' => 5.1,  'label' => 'Green',  'color' => '#198754'],
                ['scvr_max' => 9999, 'bid' => 2.1,  'label' => 'Pink',   'color' => '#e83e8c'],
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
                'em.l7_views',
                'em.ebay_l30',
                // Dilution inputs (from shopify_skus, matched by sku). Correlated subqueries
                // avoid row multiplication and keep every ad row visible even when unmatched.
                // DIL = (quantity / inv) * 100  — quantity = L30 sold, inv = stock on hand.
                DB::raw("(SELECT ss.inv FROM shopify_skus ss WHERE ss.sku = em.sku LIMIT 1) as shopify_inv"),
                DB::raw("(SELECT ss.quantity FROM shopify_skus ss WHERE ss.sku = em.sku LIMIT 1) as shopify_qty")
            );

        if ($request->filled('funding_strategy')) {
            $query->where('ca.funding_strategy', $request->funding_strategy);
        }
        if ($request->filled('campaign_status')) {
            $query->where('ca.campaign_status', $request->campaign_status);
        }
        if ($request->filled('promote_with_ad')) {
            $promote = $request->promote_with_ad;
            if ($promote === '__NONE__') {
                $query->where(function ($q) {
                    $q->whereNull('ca.promote_with_ad')
                      ->orWhere('ca.promote_with_ad', '');
                });
            } else {
                $query->where('ca.promote_with_ad', $promote);
            }
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
