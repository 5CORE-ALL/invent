<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Campaigns\Concerns\ProvidesEbayCampaignAdsBadgeSummary;
use App\Http\Controllers\Controller;
use App\Services\EbayChannelMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class EbayCampaignAdsController extends Controller
{
    use ProvidesEbayCampaignAdsBadgeSummary;
    public function index()
    {
        $rule = DB::table('ebay_sbid_rules')->where('key', 'ebay1')->first();
        $ruleData = $rule ? json_decode($rule->rule, true) : $this->defaultRule();
        // Backfill threshold for existing rule rows saved before this field was added.
        if (!isset($ruleData['l7_views_threshold'])) {
            $ruleData['l7_views_threshold'] = 70;
        }
        if (!isset($ruleData['l30_sold_es_bid_max'])) {
            $ruleData['l30_sold_es_bid_max'] = 0;
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
        $bands       = $request->input('bands', []);
        $threshold   = $request->input('l7_views_threshold', 70);
        $l30SoldMax  = $request->input('l30_sold_es_bid_max', 0);

        if (empty($bands) || !is_array($bands)) {
            return response()->json(['error' => 'Invalid rule data'], 422);
        }
        if (!is_numeric($threshold) || $threshold < 0) {
            return response()->json(['error' => 'l7_views_threshold must be a non-negative number'], 422);
        }
        if (!is_numeric($l30SoldMax) || $l30SoldMax < 0) {
            return response()->json(['error' => 'l30_sold_es_bid_max must be a non-negative number'], 422);
        }

        // Sort bands by scvr_max ascending
        usort($bands, fn($a, $b) => $a['scvr_max'] <=> $b['scvr_max']);

        $rule = [
            'l7_views_threshold'   => (float) $threshold,
            'l30_sold_es_bid_max' => (float) $l30SoldMax,
            'bands'                => $bands,
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

        // Load Sbid Rule slabs (CVR / Dil / Esold / Views L30 → S Bid).
        $slabRow = DB::table('ebay_sbid_rules')->where('key', 'ebay1_sbid_slabs')->first();
        $slabs   = $slabRow ? (json_decode($slabRow->rule, true)['rules'] ?? []) : [];

        // Get ebay metrics for these listings
        $metrics = \App\Models\EbayMetric::whereIn('item_id', $listingIds)->get()->keyBy('item_id');

        // Shopify inv/quantity for DIL, keyed by normalized SKU
        $shopifyMap = $this->shopifyByNormSku($metrics->pluck('sku')->filter()->unique()->values()->all());

        // Sbid (Views): settings + avg L7 views (prefer the UI's avg to match screen).
        $sbidViewsSettings = \App\Support\SbidViewsRule::settings();
        $avgL7Views = $request->input('avg_l7_views');
        if (!is_numeric($avgL7Views)) {
            $l7Sum = 0.0; $l7Count = 0;
            foreach ($metrics as $m) { $l7Sum += (float) ($m->l7_views ?? 0); $l7Count++; }
            $avgL7Views = $l7Count > 0 ? ($l7Sum / $l7Count) : 0.0;
        }
        $avgL7Views = (float) $avgL7Views;

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

            // Sbid (Views): adjust the current C Bid (bid_percentage) by the row's
            // L7 View band (direction/step), clamped to caps. No C Bid → skip.
            $metric  = $metrics->get($lid);
            $baseBid = (float) ($ad->bid_percentage ?? 0);
            $l7views = (float) ($metric?->l7_views ?? 0);
            $newBid  = \App\Support\SbidViewsRule::apply($baseBid, $l7views, $avgL7Views, $sbidViewsSettings);
            if ($newBid <= 0) {
                $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'No current C Bid to adjust'];
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
     * Apply the Sbid Rule slabs (ebay1_sbid_slabs) to a set of SKUs and push each
     * computed S Bid to its eBay campaign. Used by the "Apply to Visible Rows"
     * button in the /ebay-tabulator-view Sbid Rule modal.
     */
    public function pushSbidSlabsBySku(Request $request)
    {
        $skus = $request->input('skus', []);
        if (empty($skus) || !is_array($skus)) {
            return response()->json(['error' => 'No SKUs provided'], 422);
        }

        // Metrics keyed by normalized SKU (for l7_views + item_id).
        $metrics = \App\Models\EbayMetric::whereIn('sku', $skus)->get()
            ->keyBy(fn($m) => $this->normSku($m->sku));

        // Sbid (Views): adjustment settings + the average L7 views used for colour
        // bands. Prefer the avg the UI computed (so the pushed value matches the
        // screen); otherwise average l7_views across the SKUs in this request.
        $sbidViewsSettings = \App\Support\SbidViewsRule::settings();
        $avgL7Views = $request->input('avg_l7_views');
        if (!is_numeric($avgL7Views)) {
            $l7Sum = 0.0; $l7Count = 0;
            foreach ($metrics as $m) { $l7Sum += (float) ($m->l7_views ?? 0); $l7Count++; }
            $avgL7Views = $l7Count > 0 ? ($l7Sum / $l7Count) : 0.0;
        }
        $avgL7Views = (float) $avgL7Views;

        // Campaign ads keyed by listing_id (item_id).
        $itemIds = $metrics->pluck('item_id')->filter()->unique()->values()->all();
        $ads = DB::table('ebay_campaign_ads')
            ->whereIn('listing_id', $itemIds)
            ->whereNotNull('campaign_id')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->get()
            ->keyBy('listing_id');

        try {
            $service = new \App\Services\EbayApiService();
            $token   = $service->generateBearerToken();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token error: ' . $e->getMessage()], 500);
        }

        $results = [];
        $success = 0; $failed = 0; $skipped = 0;
        $byCampaign = [];

        foreach ($skus as $sku) {
            $norm   = $this->normSku($sku);
            $metric = $metrics->get($norm);
            if (!$metric || !$metric->item_id) {
                $results[] = ['sku' => $sku, 'status' => 'skipped', 'reason' => 'No eBay listing'];
                $skipped++;
                continue;
            }
            $lid = (string) $metric->item_id;
            $ad  = $ads->get($lid);
            if (!$ad || !$ad->campaign_id) {
                $results[] = ['sku' => $sku, 'status' => 'skipped', 'reason' => 'Not in a COST_PER_SALE campaign'];
                $skipped++;
                continue;
            }

            // Sbid (Views): adjust the current C Bid (bid_percentage) by the row's
            // L7 View band (direction/step), clamped to caps. No C Bid → skip.
            $baseBid = (float) ($ad->bid_percentage ?? 0);
            $l7      = (float) ($metric->l7_views ?? 0);
            $bid     = \App\Support\SbidViewsRule::apply($baseBid, $l7, $avgL7Views, $sbidViewsSettings);
            if ($bid <= 0) {
                $results[] = ['sku' => $sku, 'status' => 'skipped', 'reason' => 'No current C Bid to adjust'];
                $skipped++;
                continue;
            }
            $byCampaign[$ad->campaign_id][] = ['listingId' => $lid, 'bidPercentage' => (string) $bid, 'sku' => $sku];
        }

        foreach ($byCampaign as $campaignId => $requests) {
            $payload = array_map(fn($r) => ['listingId' => $r['listingId'], 'bidPercentage' => $r['bidPercentage']], $requests);
            try {
                $response = \Illuminate\Support\Facades\Http::withToken($token)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_bid_by_listing_id",
                        ['requests' => $payload]);

                if ($response->successful()) {
                    foreach ($requests as $r) {
                        $results[] = ['sku' => $r['sku'], 'status' => 'pushed', 'bid' => $r['bidPercentage'] . '%'];
                        $success++;
                    }
                } else {
                    foreach ($requests as $r) {
                        $results[] = ['sku' => $r['sku'], 'status' => 'failed', 'reason' => 'HTTP ' . $response->status()];
                        $failed++;
                    }
                }
            } catch (\Exception $e) {
                foreach ($requests as $r) {
                    $results[] = ['sku' => $r['sku'], 'status' => 'failed', 'reason' => $e->getMessage()];
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

    /** Resolve S Bid from slab rules (first matching slab wins); 0 = no match. */
    private function resolveSlabBid(float $cvr, float $dil, float $esold, float $views, array $slabs): float
    {
        foreach ($slabs as $s) {
            if ($this->slabInRange($cvr,   $s['cvr_min']   ?? null, $s['cvr_max']   ?? null)
                && $this->slabInRange($dil,   $s['dil_min']   ?? null, $s['dil_max']   ?? null)
                && $this->slabInRange($esold, $s['esold_min'] ?? null, $s['esold_max'] ?? null)
                && $this->slabInRange($views, $s['views_min'] ?? null, $s['views_max'] ?? null)) {
                return (float) ($s['sbid'] ?? 0);
            }
        }
        return 0.0;
    }

    private function slabInRange(float $val, $min, $max): bool
    {
        if ($min !== null && $min !== '' && $val < (float) $min) return false;
        if ($max !== null && $max !== '' && $val > (float) $max) return false;
        return true;
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
        $ruleConfig = $this->sbidRuleConfig();
        $bands        = $ruleConfig['bands'] ?? [];
        $dilBands     = $this->dilBands();

        // Get metrics for SCVR calculation
        $metrics = \App\Models\EbayMetric::whereIn('item_id', $listingIds)
            ->get()->keyBy('item_id');

        // Shopify inv/quantity for DIL, keyed by normalized SKU
        $shopifyMap = $this->shopifyByNormSku($metrics->pluck('sku')->filter()->unique()->values()->all());

        $ads = DB::table('ebay_campaign_ads')
            ->whereIn('listing_id', $listingIds)
            ->get()
            ->keyBy('listing_id');

        try {
            $service = new \App\Services\EbayApiService();
            $token   = $service->generateBearerToken();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token error: ' . $e->getMessage()], 500);
        }

        $results = [];
        $success = 0;
        $failed  = 0;

        // Listings whose ad already exists on eBay (create returns "already exists").
        // We retry these as a bid update by listing_id after the loop so bulk enroll
        // still works even when our local table is out of sync with eBay.
        $retryUpdate = [];

        $skipped = 0;
        foreach ($listingIds as $lid) {
            $lid    = (string)$lid;
            $metric = $metrics->get($lid);
            $views  = (float)($metric?->views ?? 0);
            $l7     = (float)($metric?->l7_views ?? 0);
            $l30    = (float)($metric?->ebay_l30 ?? 0);
            $scvr   = $views > 0 ? ($l30 / $views) * 100 : 0;

            $shop = $metric ? ($shopifyMap[$this->normSku($metric->sku)] ?? null) : null;
            $inv  = (float)($shop->inv ?? 0);
            $qty  = (float)($shop->quantity ?? 0);
            $dil  = $inv > 0 ? ($qty / $inv) * 100 : 0;

            $adRow = $ads->get($lid);
            $esBid = (float)($adRow?->suggested_bid ?? 0);

            if ($this->shouldUseEsBid($l30, $l7, $ruleConfig)) {
                $bid = $esBid;
            } else {
                $bid = $this->resolveCombinedBid($scvr, $bands, $dil, $dilBands, [
                    'ebay_price' => (float)($metric?->ebay_price ?? 0),
                    'ebay_l30'   => $l30,
                    'views'      => $views,
                ]);
            }

            if ($bid <= 0) {
                $results[] = ['listing_id' => $lid, 'sku' => $metric?->sku, 'status' => 'skipped', 'reason' => 'No SBID — ES Bid fallback with no ES Bid, or 0 CVR & DIL not Pink'];
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
                    $errMsg = $resp->json()['errors'][0]['message'] ?? (string) $resp->status();
                    // "An ad for listing Id ... already exists" — the ad is already in this
                    // campaign (our table just wasn't synced). Retry as a bid update below.
                    if (stripos((string) $errMsg, 'already exists') !== false) {
                        $retryUpdate[] = [
                            'listingId'     => $lid,
                            'bidPercentage' => (string) $bid,
                            'sku'           => $metric?->sku,
                            'bid'           => $bid,
                        ];
                    } elseif ($this->isEndedOrInvalidListing($errMsg)) {
                        // Listing is no longer active on eBay — can't be enrolled. Mark
                        // as skipped (not a real failure) and flag it locally so it can
                        // be filtered/cleaned up.
                        DB::table('ebay_campaign_ads')->where('listing_id', $lid)
                            ->update(['campaign_status' => 'ENDED', 'updated_at' => now()]);
                        $results[] = ['listing_id' => $lid, 'sku' => $metric?->sku, 'status' => 'skipped', 'reason' => $errMsg];
                        $skipped++;
                    } else {
                        $results[] = ['listing_id' => $lid, 'sku' => $metric?->sku, 'status' => 'failed', 'reason' => $errMsg];
                        $failed++;
                    }
                }
            } catch (\Exception $e) {
                $results[] = ['listing_id' => $lid, 'sku' => $metric?->sku, 'status' => 'failed', 'reason' => $e->getMessage()];
                $failed++;
            }
        }

        // Retry the "already exists" listings as a single bulk bid update on the
        // campaign. Ones that update successfully are synced locally and counted as
        // enrolled; ones that still fail (e.g. ad lives in a different campaign) are
        // reported as failed.
        if (!empty($retryUpdate)) {
            $payload = array_map(fn($r) => [
                'listingId'     => $r['listingId'],
                'bidPercentage' => $r['bidPercentage'],
            ], $retryUpdate);

            try {
                $resp = \Illuminate\Support\Facades\Http::withToken($token)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_bid_by_listing_id",
                        ['requests' => $payload]);

                if ($resp->successful()) {
                    // Map per-listing outcomes from the bulk response (when provided).
                    $perListing = [];
                    foreach (($resp->json()['responses'] ?? []) as $r) {
                        $rid = (string) ($r['listingId'] ?? '');
                        if ($rid === '') continue;
                        $code = (int) ($r['statusCode'] ?? 200);
                        $ok   = $code >= 200 && $code < 300 && empty($r['errors']);
                        $perListing[$rid] = $ok ? true : ($r['errors'][0]['message'] ?? 'Bid update failed');
                    }

                    foreach ($retryUpdate as $r) {
                        $rid = (string) $r['listingId'];
                        $outcome = $perListing[$rid] ?? true; // 200 with no detail → assume ok
                        if ($outcome === true) {
                            DB::table('ebay_campaign_ads')
                                ->where('listing_id', $rid)
                                ->update([
                                    'campaign_id'      => $campaignId,
                                    'funding_strategy' => 'COST_PER_SALE',
                                    'campaign_status'  => 'RUNNING',
                                    'bid_percentage'   => $r['bid'],
                                    'promote_with_ad'  => 'AD_ALREADY_CREATED',
                                    'updated_at'       => now(),
                                ]);
                            $results[] = ['listing_id' => $rid, 'sku' => $r['sku'], 'status' => 'enrolled', 'bid' => $r['bid'] . '%'];
                            $success++;
                        } elseif ($this->isEndedOrInvalidListing((string) $outcome)) {
                            DB::table('ebay_campaign_ads')->where('listing_id', $rid)
                                ->update(['campaign_status' => 'ENDED', 'updated_at' => now()]);
                            $results[] = ['listing_id' => $rid, 'sku' => $r['sku'], 'status' => 'skipped', 'reason' => $outcome];
                            $skipped++;
                        } else {
                            $results[] = ['listing_id' => $rid, 'sku' => $r['sku'], 'status' => 'failed', 'reason' => $outcome];
                            $failed++;
                        }
                    }
                } else {
                    $reason = 'Already exists; bid update failed (HTTP ' . $resp->status() . ')';
                    foreach ($retryUpdate as $r) {
                        $results[] = ['listing_id' => $r['listingId'], 'sku' => $r['sku'], 'status' => 'failed', 'reason' => $reason];
                        $failed++;
                    }
                }
            } catch (\Exception $e) {
                foreach ($retryUpdate as $r) {
                    $results[] = ['listing_id' => $r['listingId'], 'sku' => $r['sku'], 'status' => 'failed', 'reason' => $e->getMessage()];
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

    /** True when an eBay error message indicates the listing has ended / is invalid. */
    private function isEndedOrInvalidListing(string $msg): bool
    {
        $m = strtolower($msg);
        return str_contains($m, 'has ended')
            || str_contains($m, 'is invalid')
            || str_contains($m, 'invalid or has ended')
            || str_contains($m, 'no longer active');
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

    /** Parsed ebay1 SBID rule with defaults for missing keys. */
    private function sbidRuleConfig(): array
    {
        $rule = DB::table('ebay_sbid_rules')->where('key', 'ebay1')->first();
        $data = $rule ? (json_decode($rule->rule, true) ?: []) : $this->defaultRule();
        if (!isset($data['l7_views_threshold'])) {
            $data['l7_views_threshold'] = 70;
        }
        if (!isset($data['l30_sold_es_bid_max'])) {
            $data['l30_sold_es_bid_max'] = 0;
        }

        return $data;
    }

    /** True when S Bid should fall back to raw ES Bid (suggested_bid). */
    private function shouldUseEsBid(float $l30Sold, float $l7Views, array $rule): bool
    {
        $l30Max = (float) ($rule['l30_sold_es_bid_max'] ?? 0);
        $l7Thr  = (float) ($rule['l7_views_threshold'] ?? 70);

        return $l30Sold <= $l30Max || $l7Views < $l7Thr;
    }

    private function defaultRule(): array
    {
        return [
            'l7_views_threshold'    => 70,
            'l30_sold_es_bid_max'   => 0,
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

    /**
     * Single eBay row for /advertisement-master — KW + PMT combined from daily
     * ebay_priority_reports and ebay_general_reports (31-day window).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdvertisementMasterChannelRows(): array
    {
        $kwMetrics = $this->advertisementMasterKwMetrics();
        $pmtMetrics = $this->advertisementMasterPmtMetrics();

        return [
            self::advertisementMasterMetricRow('eBay', 'ebay', (object) [
                'spend'  => $kwMetrics['spend'] + $pmtMetrics['spend'],
                'clicks' => $kwMetrics['clicks'] + $pmtMetrics['clicks'],
                'sold'   => $kwMetrics['sold'] + $pmtMetrics['sold'],
                'sales'  => $kwMetrics['sales'] + $pmtMetrics['sales'],
                'active' => $this->advertisementMasterActiveCount('ebay_priority_reports', 'ebay_campaign_ads'),
            ], false),
        ];
    }

    /**
     * Active (RUNNING) eBay campaigns: keyword (CPC) campaigns from the priority-report
     * L30 rows plus promoted (CPS / COST_PER_SALE) campaigns from the campaign-ads table.
     * Defensive column/table checks keep it at 0 rather than erroring if a schema differs.
     */
    protected function advertisementMasterActiveCount(string $priorityTable, string $campaignAdsTable): int
    {
        $kw = 0;
        if (Schema::hasTable($priorityTable)
            && Schema::hasColumn($priorityTable, 'campaignStatus')
            && Schema::hasColumn($priorityTable, 'campaign_id')) {
            $kw = (int) DB::table($priorityTable)
                ->whereRaw("UPPER(TRIM(report_range)) = 'L30'")
                ->whereRaw("UPPER(TRIM(campaignStatus)) = 'RUNNING'")
                ->whereNotNull('campaign_id')
                ->distinct()
                ->count('campaign_id');
        }

        $pmt = 0;
        if (Schema::hasTable($campaignAdsTable)
            && Schema::hasColumn($campaignAdsTable, 'campaign_status')
            && Schema::hasColumn($campaignAdsTable, 'campaign_id')) {
            $q = DB::table($campaignAdsTable)
                ->whereRaw("UPPER(TRIM(campaign_status)) = 'RUNNING'")
                ->whereNotNull('campaign_id');
            if (Schema::hasColumn($campaignAdsTable, 'funding_strategy')) {
                $q->where('funding_strategy', 'COST_PER_SALE');
            }
            $pmt = (int) $q->distinct()->count('campaign_id');
        }

        return $kw + $pmt;
    }

    /**
     * eBay L30 store sales from marketplace_daily_metrics (Channel Master source).
     */
    public static function advertisementMasterNetSales(): float
    {
        try {
            $metrics = EbayChannelMetricsService::latestDailyMetrics('eBay');

            return round((float) ($metrics?->total_sales ?? 0), 2);
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master eBay net sales lookup failed: '.$e->getMessage());

            return 0.0;
        }
    }

    /**
     * @return array{spend: float, clicks: int, sold: int, sales: float}
     */
    protected function advertisementMasterKwMetrics(): array
    {
        return $this->advertisementMasterReportMetrics('ebay_priority_reports', 'kw');
    }

    /**
     * @return array{spend: float, clicks: int, sold: int, sales: float}
     */
    protected function advertisementMasterPmtMetrics(): array
    {
        return $this->advertisementMasterReportMetrics('ebay_general_reports', 'pmt');
    }

    /**
     * @return array{spend: float, clicks: int, sold: int, sales: float}
     */
    private function advertisementMasterReportMetrics(string $table, string $type): array
    {
        $empty = ['spend' => 0.0, 'clicks' => 0, 'sold' => 0, 'sales' => 0.0];

        if (! Schema::hasTable($table)) {
            return $empty;
        }

        $startDate = now()->subDays(31)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        if ($type === 'kw') {
            $row = DB::table($table)
                ->where('report_range', '>=', $startDate)
                ->where('report_range', '<=', $endDate)
                ->where('report_range', 'NOT LIKE', 'L%')
                ->selectRaw('COALESCE(SUM(cpc_clicks), 0) as clicks')
                ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")), 0) as sales')
                ->selectRaw('COALESCE(SUM(cpc_attributed_sales), 0) as sold')
                ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")), 0) as spend')
                ->first();
        } else {
            $row = DB::table($table)
                ->where('report_range', '>=', $startDate)
                ->where('report_range', '<=', $endDate)
                ->where('report_range', 'NOT LIKE', 'L%')
                ->selectRaw('COALESCE(SUM(clicks), 0) as clicks')
                ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(sale_amount, "USD ", ""), ",", "")), 0) as sales')
                ->selectRaw('COALESCE(SUM(sales), 0) as sold')
                ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")), 0) as spend')
                ->first();
        }

        if ($row === null) {
            return $empty;
        }

        return [
            'spend'  => round((float) ($row->spend ?? 0), 2),
            'clicks' => (int) round((float) ($row->clicks ?? 0)),
            'sold'   => (int) round((float) ($row->sold ?? 0)),
            'sales'  => round((float) ($row->sales ?? 0), 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function advertisementMasterMetricRow(string $channel, string $source, ?object $row, bool $isSubRow = false): array
    {
        $spend  = (float) ($row->spend ?? 0);
        $clicks = (float) ($row->clicks ?? 0);
        $sold   = (float) ($row->sold ?? 0);
        $sales  = (float) ($row->sales ?? 0);

        return [
            'channel'     => $channel,
            'source'      => $source,
            'spend'       => round($spend, 2),
            'clicks'      => (int) round($clicks),
            'sold'        => (int) round($sold),
            'sales'       => round($sales, 2),
            'cvr'         => $clicks > 0 ? round(($sold / $clicks) * 100, 1) : 0,
            'acos'        => $sales > 0
                ? round(($spend / $sales) * 100, 0)
                : ($spend > 0 ? 100 : 0),
            'tcos'        => 0,
            'active'      => (int) ($row->active ?? 0),
            'is_sub_row'  => $isSubRow,
            'marketplace' => 'ebay',
        ];
    }
}
