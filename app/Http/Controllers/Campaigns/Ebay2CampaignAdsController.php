<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Campaigns\Concerns\ProvidesEbayCampaignAdsBadgeSummary;
use App\Http\Controllers\Controller;
use App\Models\Ebay2Metric;
use App\Models\ProductMaster;
use App\Services\EbayChannelMetricsService;
use App\Support\EbayCampaignReportRollup;
use App\Support\Marketplace\EbayCampaignEndedListingRemap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * eBay 2 mirror of {@see EbayCampaignAdsController}
 * — same Sbid Rule slabs as eBay 1 (`ebay1_sbid_slabs`) + DIL, driven off eBay-2 data:
 *   - Campaign data: `ebay2_campaign_ads`
 *   - Metrics:       `ebay_2_metrics` (App\Models\Ebay2Metric)
 *   - Rule keys:     `ebay1_sbid_slabs` (shared For L7 Views / CVR → S Bid) and
 *                    `ebay2_dil` (DIL colour bands) in `ebay_sbid_rules`
 *                    (`ebay2` SCVR bands kept only for legacy getRule/saveRule;
 *                     `ebay2_sbid_views` kept for /ebay2-tabulator-view)
 *   - Token / push:  Ebay2ApiService
 *   - Tabulator is Parents Only: parent-row L7 Views / CVR drive S Bid; push
 *     applies that family bid to every listing under the parent.
 */
class Ebay2CampaignAdsController extends Controller
{
    use ProvidesEbayCampaignAdsBadgeSummary;

    public const SBID_SLABS_KEY = 'ebay1_sbid_slabs';

    /**
     * Sbid (Views) settings — kept for /ebay2-tabulator-view.
     * Campaign-ads S Bid uses the shared Ebay 1 slab rule (`ebay1_sbid_slabs`).
     */
    public function getSbidViewsRule()
    {
        return response()->json(\App\Support\SbidViewsRule::settings(\App\Support\SbidViewsRule::KEY_EBAY2));
    }

    /**
     * Shared Ebay 1 Sbid Rule slabs (For L7 Views / CVR → S Bid).
     * Same source as /ebay/campaign-ads and /ebay3/campaign-ads.
     */
    public function getSbidSlabRule()
    {
        return response()->json([
            'rules' => $this->sbidSlabs(),
        ]);
    }

    public function saveSbidSlabRule(Request $request)
    {
        $rules = $request->input('rules', []);

        if (! is_array($rules)) {
            return response()->json(['error' => 'Invalid rule data'], 422);
        }

        $clean = [];
        foreach ($rules as $r) {
            if (! is_array($r)) {
                continue;
            }
            $clean[] = [
                'label' => isset($r['label']) ? (string) $r['label'] : '',
                'l7_views_min' => $this->numOrNull($r['l7_views_min'] ?? null),
                'l7_views_max' => $this->numOrNull($r['l7_views_max'] ?? null),
                'sbid' => $this->numOrNull($r['sbid'] ?? null) ?? 0,
            ];
        }

        if ($clean === []) {
            $clean = $this->defaultSbidSlabRules();
        }

        $rule = [
            'rules' => $clean,
            'es_bid' => $this->numOrNull($request->input('es_bid')),
        ];

        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => self::SBID_SLABS_KEY],
            ['rule' => json_encode($rule), 'updated_at' => now()]
        );

        return response()->json(['success' => true, 'rule' => $rule]);
    }

    private function numOrNull($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }

    public function saveSbidViewsRule(Request $request)
    {
        $settings = \App\Support\SbidViewsRule::sanitize($request->all());

        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => \App\Support\SbidViewsRule::KEY_EBAY2],
            ['rule' => json_encode($settings), 'updated_at' => now()]
        );

        return response()->json(['success' => true, 'rule' => $settings]);
    }

    public function index()
    {
        $dil = DB::table('ebay_sbid_rules')->where('key', 'ebay2_dil')->first();
        $dilData = $dil ? json_decode($dil->rule, true) : $this->defaultDilRule();

        return view('campaign.ebay2-campaign-ads', [
            'dilRule' => $dilData,
        ]);
    }

    public function getRule()
    {
        $rule = DB::table('ebay_sbid_rules')->where('key', 'ebay2')->first();
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

        usort($bands, fn($a, $b) => $a['scvr_max'] <=> $b['scvr_max']);

        $rule = [
            'l7_views_threshold'    => (float) $threshold,
            'l30_sold_es_bid_max'   => (float) $l30SoldMax,
            'bands'                 => $bands,
        ];

        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => 'ebay2'],
            ['rule' => json_encode($rule), 'updated_at' => now()]
        );

        return response()->json(['success' => true, 'rule' => $rule]);
    }

    /**
     * Dilution rule — DIL% color bands stored under key `ebay2_dil` in
     * `ebay_sbid_rules`. DIL = (L30 sold / inventory) * 100. Bands evaluated
     * ascending by dil_max — first band where DIL <= dil_max wins.
     */
    public function getDilRule()
    {
        $rule = DB::table('ebay_sbid_rules')->where('key', 'ebay2_dil')->first();
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
            ['key' => 'ebay2_dil'],
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

        $slabs = $this->sbidSlabs();

        $metrics = Ebay2Metric::whereIn('item_id', $listingIds)->get()->keyBy('item_id');
        $shopifyMap = $this->shopifyByNormSku($metrics->pluck('sku')->filter()->unique()->values()->all());

        $ads = DB::table('ebay2_campaign_ads')
            ->whereIn('listing_id', $listingIds)
            ->whereNotNull('campaign_id')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->get()
            ->keyBy('listing_id');

        $results = [];
        $success = 0;
        $failed  = 0;
        $skipped = 0;

        try {
            $service = new \App\Services\Ebay2ApiService();
            $token   = $service->generateBearerToken();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token error: ' . $e->getMessage()], 500);
        }

        $byCampaign = [];
        foreach ($listingIds as $lid) {
            $lid = (string)$lid;
            $ad  = $ads->get($lid);
            if (!$ad || !$ad->campaign_id) {
                $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'Not in a COST_PER_SALE campaign'];
                $skipped++;
                continue;
            }

            $metric   = $metrics->get($lid);
            $soldL30  = (float) ($metric?->ebay_l30 ?? 0);
            $views    = (float) ($metric?->views ?? 0);
            $l7Views  = (float) ($metric?->l7_views ?? 0);
            $scvr     = $views > 0 ? ($soldL30 / $views) * 100 : 0;
            $shopify  = $shopifyMap[$this->normSku($metric?->sku ?? '')] ?? null;
            $inv      = (float) ($shopify->inv ?? 0);
            $qty      = (float) ($shopify->quantity ?? 0);
            $dil      = $inv > 0 ? ($qty / $inv) * 100 : 0;
            $newBid   = $this->resolveSlabBid($scvr, $dil, $soldL30, $views, $l7Views, $slabs);
            if ($newBid <= 0) {
                $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'No matching Sbid Rule slab'];
                $skipped++;
                continue;
            }
            $byCampaign[$ad->campaign_id][] = ['listingId' => $lid, 'bidPercentage' => (string)$newBid];
        }

        foreach ($byCampaign as $campaignId => $requests) {
            try {
                $response = \Illuminate\Support\Facades\Http::withToken($token)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_bid_by_listing_id",
                        ['requests' => $requests]);

                if ($response->successful()) {
                    foreach ($requests as $r) {
                        DB::table('ebay2_campaign_ads')
                            ->where('listing_id', (string) $r['listingId'])
                            ->where('campaign_id', (string) $campaignId)
                            ->update([
                                'bid_percentage' => round((float) $r['bidPercentage'], 2),
                                'updated_at' => now(),
                            ]);
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
     * Apply the shared Ebay 1 Sbid Rule slabs and push each computed S Bid
     * to its eBay 2 campaign. PARENT SKUs (tabulator Parents Only) use
     * family-aggregated L7 Views / CVR and push that bid to every listing
     * under the parent.
     */
    public function pushSbidSlabsBySku(Request $request)
    {
        $skus = $request->input('skus', []);
        if (empty($skus) || !is_array($skus)) {
            return response()->json(['error' => 'No SKUs provided'], 422);
        }

        $slabs = $this->sbidSlabs();

        $lookupSkus = [];
        foreach ($skus as $sku) {
            $sku = (string) $sku;
            if ($this->isEbay2ParentSku($sku)) {
                foreach ($this->familySkusForParentKey($this->ebay2ParentKey($sku)) as $fs) {
                    $lookupSkus[] = $fs;
                }
            }
            $lookupSkus[] = $sku;
        }
        $lookupSkus = array_values(array_unique($lookupSkus));

        $metrics = Ebay2Metric::whereIn('sku', $lookupSkus)->get()
            ->keyBy(fn ($m) => $this->normSku($m->sku));
        $shopifyMap = $this->shopifyByNormSku($metrics->pluck('sku')->filter()->unique()->values()->all());

        $itemIds = $metrics->pluck('item_id')->filter()->unique()->values()->all();
        $ads = DB::table('ebay2_campaign_ads')
            ->whereIn('listing_id', $itemIds)
            ->whereNotNull('campaign_id')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->get()
            ->keyBy('listing_id');

        try {
            $service = new \App\Services\Ebay2ApiService();
            $token   = $service->generateBearerToken();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token error: ' . $e->getMessage()], 500);
        }

        $results = [];
        $success = 0; $failed = 0; $skipped = 0;
        $byCampaign = [];

        foreach ($skus as $sku) {
            $sku = (string) $sku;
            if ($this->isEbay2ParentSku($sku)) {
                $familySkus = $this->familySkusForParentKey($this->ebay2ParentKey($sku));
                if ($familySkus === []) {
                    $familySkus = [$sku];
                }
                $el30 = 0.0;
                $views = 0.0;
                $l7Views = 0.0;
                $listingEntries = [];
                foreach ($familySkus as $fs) {
                    $metric = $metrics->get($this->normSku($fs));
                    if (! $metric) {
                        continue;
                    }
                    $el30 += (float) ($metric->ebay_l30 ?? 0);
                    $views += (float) ($metric->views ?? 0);
                    $l7Views += (float) ($metric->l7_views ?? 0);
                    $lid = (string) ($metric->item_id ?? '');
                    if ($lid === '' || isset($listingEntries[$lid])) {
                        continue;
                    }
                    $ad = $ads->get($lid);
                    if (! $ad || ! $ad->campaign_id) {
                        continue;
                    }
                    $listingEntries[$lid] = [
                        'listingId' => $lid,
                        'campaign_id' => $ad->campaign_id,
                        'sku' => $fs,
                    ];
                }
                $scvr = $views > 0 ? ($el30 / $views) * 100 : 0;
                $bid = $this->resolveSlabBid($scvr, 0.0, $el30, $views, $l7Views, $slabs);
                if ($bid <= 0 || $listingEntries === []) {
                    $why = $listingEntries === []
                        ? 'No eBay listing in a COST_PER_SALE campaign'
                        : 'No matching Sbid Rule slab';
                    $results[] = ['sku' => $sku, 'status' => 'skipped', 'reason' => $why];
                    $skipped++;
                    continue;
                }
                foreach ($listingEntries as $entry) {
                    $byCampaign[$entry['campaign_id']][] = [
                        'listingId' => $entry['listingId'],
                        'bidPercentage' => (string) $bid,
                        'sku' => $entry['sku'],
                    ];
                }
                continue;
            }

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

            $soldL30 = (float) ($metric->ebay_l30 ?? 0);
            $views   = (float) ($metric->views ?? 0);
            $l7Views = (float) ($metric->l7_views ?? 0);
            $scvr    = $views > 0 ? ($soldL30 / $views) * 100 : 0;
            $shopify = $shopifyMap[$norm] ?? null;
            $inv     = (float) ($shopify->inv ?? 0);
            $qty     = (float) ($shopify->quantity ?? 0);
            $dil     = $inv > 0 ? ($qty / $inv) * 100 : 0;
            $bid     = $this->resolveSlabBid($scvr, $dil, $soldL30, $views, $l7Views, $slabs);
            if ($bid <= 0) {
                $results[] = ['sku' => $sku, 'status' => 'skipped', 'reason' => 'No matching Sbid Rule slab'];
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
                        DB::table('ebay2_campaign_ads')
                            ->where('listing_id', (string) $r['listingId'])
                            ->where('campaign_id', (string) $campaignId)
                            ->update([
                                'bid_percentage' => round((float) $r['bidPercentage'], 2),
                                'updated_at' => now(),
                            ]);
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

    public function getCampaignList()
    {
        $campaigns = DB::table('ebay2_campaign_ads')
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
        $listingIds = $request->input('listing_ids', []);
        $campaignId = $request->input('campaign_id');

        if (empty($listingIds) || !$campaignId) {
            return response()->json(['error' => 'listing_ids and campaign_id required'], 422);
        }

        $campaignName = DB::table('ebay2_campaign_ads')
            ->where('campaign_id', (string) $campaignId)
            ->value('campaign_name');

        $out = $this->enrollListings(
            $listingIds,
            (string) $campaignId,
            $campaignName !== null && $campaignName !== '' ? (string) $campaignName : null
        );
        if (! empty($out['error'])) {
            return response()->json(['error' => $out['error']], 500);
        }

        return response()->json($out);
    }

    /**
     * Enroll listings into a COST_PER_SALE campaign and mark them RUNNING.
     *
     * @param  list<string|int>  $listingIds
     * @return array{success:int,failed:int,skipped:int,results:array<int,array<string,mixed>>,error?:string}
     */
    public function enrollListings(array $listingIds, string $campaignId, ?string $campaignName = null): array
    {
        $slabs = $this->sbidSlabs();

        $ads = DB::table('ebay2_campaign_ads')
            ->whereIn('listing_id', $listingIds)
            ->get()
            ->keyBy('listing_id');

        $metrics = Ebay2Metric::whereIn('item_id', $listingIds)
            ->get()->keyBy('item_id');
        $shopifyMap = $this->shopifyByNormSku(
            $ads->pluck('sku')->merge($metrics->pluck('sku'))->filter()->unique()->values()->all()
        );

        try {
            $service = new \App\Services\Ebay2ApiService();
            $token   = $service->generateBearerToken();
        } catch (\Exception $e) {
            return [
                'success' => 0,
                'failed' => 0,
                'skipped' => 0,
                'results' => [],
                'error' => 'Token error: ' . $e->getMessage(),
            ];
        }

        $results = [];
        $success = 0;
        $failed  = 0;
        $skipped = 0;

        foreach ($listingIds as $requestedId) {
            $resolved = EbayCampaignEndedListingRemap::resolveEnrollListing(
                'ebay2_campaign_ads',
                Ebay2Metric::class,
                (string) $requestedId,
                $ads->get((string) $requestedId),
                $metrics->get((string) $requestedId),
                $token
            );
            $lid = $resolved['listing_id'];
            $metric = $resolved['metric'];
            $adRow = $resolved['ad'];
            $sku = $resolved['sku'];
            if ($resolved['skip']) {
                $results[] = ['listing_id' => $lid, 'sku' => $sku !== '' ? $sku : $metric?->sku, 'status' => 'skipped', 'reason' => $resolved['skip']];
                $skipped++;
                continue;
            }

            if (! $metric || \App\Support\Marketplace\EbayListingEnded::isEnded($metric->listing_status ?? null)) {
                $live = EbayCampaignEndedListingRemap::resolveLiveListing(
                    $sku !== '' ? $sku : (string) ($metric?->sku ?? ''),
                    $lid,
                    $token,
                    Ebay2Metric::class
                );
                $liveId = trim((string) ($live['listing_id'] ?? ''));
                $liveSku = trim((string) ($live['sku'] ?? $sku));
                if ($liveId !== '' && $liveId !== $lid) {
                    $lid = EbayCampaignEndedListingRemap::remapAdsRow('ebay2_campaign_ads', (string) $requestedId, $liveId, $liveSku !== '' ? $liveSku : $sku);
                    $adRow = DB::table('ebay2_campaign_ads')->where('listing_id', $lid)->first();
                    $metric = $live['metric'] ?: Ebay2Metric::where('item_id', $lid)->first();
                    $sku = $liveSku !== '' ? $liveSku : $sku;
                }
            }

            if (! $metric || \App\Support\Marketplace\EbayListingEnded::isEnded($metric->listing_status ?? null)) {
                DB::table('ebay2_campaign_ads')->where('listing_id', $lid)->update([
                    'campaign_status' => 'ENDED',
                    'updated_at' => now(),
                ]);
                $results[] = [
                    'listing_id' => $lid,
                    'sku' => $sku !== '' ? $sku : $metric?->sku,
                    'status' => 'skipped',
                    'reason' => 'Listing ended on eBay — no live listing for this SKU',
                ];
                $skipped++;
                continue;
            }

            $soldL30 = (float) ($metric?->ebay_l30 ?? 0);
            $views   = (float) ($metric?->views ?? 0);
            $l7Views = (float) ($metric?->l7_views ?? 0);
            $scvr    = $views > 0 ? ($soldL30 / $views) * 100 : 0;
            $shopifyKey = $this->normSku($metric?->sku ?? $sku);
            $shopify = $shopifyMap[$shopifyKey] ?? null;
            if (! $shopify && $shopifyKey !== '') {
                $shopifyMap = array_merge($shopifyMap, $this->shopifyByNormSku([$metric?->sku, $sku]));
                $shopify = $shopifyMap[$shopifyKey] ?? null;
            }
            $inv     = (float) ($shopify->inv ?? 0);
            $qty     = (float) ($shopify->quantity ?? 0);
            $dil     = $inv > 0 ? ($qty / $inv) * 100 : 0;
            $bid     = $this->resolveSlabBid($scvr, $dil, $soldL30, $views, $l7Views, $slabs);

            if ($bid <= 0) {
                $bid = (float) ($adRow?->suggested_bid ?? 0);
            }

            if ($bid <= 0) {
                $results[] = ['listing_id' => $lid, 'sku' => $sku !== '' ? $sku : $metric?->sku, 'status' => 'skipped', 'reason' => 'No matching Sbid Rule slab and no ES Bid'];
                $skipped++;
                continue;
            }

            try {
                $resp = \Illuminate\Support\Facades\Http::withToken($token)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/ad", [
                        'listingId'     => $lid,
                        'bidPercentage' => (string)$bid,
                    ]);

                if ($resp->successful() || $resp->status() === 201) {
                    $adData = $resp->json();
                    DB::table('ebay2_campaign_ads')
                        ->where('listing_id', $lid)
                        ->update($this->enrolledAdsUpdate($campaignId, $campaignName, $bid, $adData['adId'] ?? null));

                    $results[] = ['listing_id' => $lid, 'sku' => $sku !== '' ? $sku : $metric?->sku, 'status' => 'enrolled', 'bid' => $bid . '%'];
                    $success++;
                } else {
                    $errMsg = (string) ($resp->json()['errors'][0]['message'] ?? $resp->status());
                    if (EbayCampaignEndedListingRemap::isEndedListingError($errMsg) && $sku !== '') {
                        $live = EbayCampaignEndedListingRemap::resolveLiveListing($sku, $lid, $token, Ebay2Metric::class);
                        $liveId = trim((string) ($live['listing_id'] ?? ''));
                        $liveSku = trim((string) ($live['sku'] ?? $sku));
                        if ($liveId !== '' && $liveId !== $lid) {
                            $lid = EbayCampaignEndedListingRemap::remapAdsRow('ebay2_campaign_ads', (string) $requestedId, $liveId, $liveSku !== '' ? $liveSku : $sku);
                            $sku = $liveSku !== '' ? $liveSku : $sku;
                            $retry = \Illuminate\Support\Facades\Http::withToken($token)
                                ->withHeaders(['Content-Type' => 'application/json'])
                                ->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/ad", [
                                    'listingId' => $lid,
                                    'bidPercentage' => (string) $bid,
                                ]);
                            if ($retry->successful() || $retry->status() === 201) {
                                $adData = $retry->json();
                                DB::table('ebay2_campaign_ads')->where('listing_id', $lid)->update(
                                    $this->enrolledAdsUpdate($campaignId, $campaignName, $bid, $adData['adId'] ?? null)
                                );
                                $results[] = ['listing_id' => $lid, 'sku' => $sku, 'status' => 'enrolled', 'bid' => $bid.'%', 'reason' => 'Remapped ended listing to '.$lid];
                                $success++;
                                continue;
                            }
                        } else {
                            DB::table('ebay2_campaign_ads')->where('listing_id', $lid)->update([
                                'campaign_status' => 'ENDED',
                                'updated_at' => now(),
                            ]);
                        }
                    }
                    $results[] = ['listing_id' => $lid, 'sku' => $sku !== '' ? $sku : $metric?->sku, 'status' => 'failed', 'reason' => $errMsg];
                    $failed++;
                }
            } catch (\Exception $e) {
                $results[] = ['listing_id' => $lid, 'sku' => $sku !== '' ? $sku : $metric?->sku, 'status' => 'failed', 'reason' => $e->getMessage()];
                $failed++;
            }
        }

        return [
            'success' => $success,
            'failed'  => $failed,
            'skipped' => $skipped,
            'results' => $results,
        ];
    }

    public function autoEnrollEligibleHttp()
    {
        $out = $this->autoEnrollEligible();
        if (! empty($out['error'])) {
            return response()->json(['error' => $out['error']], 500);
        }

        return response()->json($out);
    }

    /**
     * Eligible (RECOMMENDED) listings with stock and a price, not already
     * RUNNING/PAUSED, are enrolled into the matching parent PMT campaign.
     *
     * @return array{success:int,failed:int,skipped:int,created_campaigns:int,results:array<int,array<string,mixed>>,error?:string}
     */
    public function autoEnrollEligible(bool $dryRun = false, ?int $limit = null): array
    {
        $listingIds = $this->eligibleListingIdsForAutoEnroll();
        if ($limit !== null && $limit > 0) {
            $listingIds = array_slice($listingIds, 0, $limit);
        }

        $empty = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'created_campaigns' => 0,
            'results' => [],
        ];

        if ($listingIds === []) {
            return $empty;
        }

        try {
            $token = (new \App\Services\Ebay2ApiService())->generateBearerToken();
        } catch (\Exception $e) {
            $empty['error'] = 'Token error: '.$e->getMessage();

            return $empty;
        }

        $campaigns = $this->fetchRunningCpsCampaigns($token);
        $created = 0;
        $allResults = [];
        $success = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($listingIds as $lid) {
            $sku = $this->resolvedSkuForListing((string) $lid);
            $parent = $this->productMasterParent($sku);
            $match = $this->matchCampaignForSku($sku, $campaigns, $parent);

            if (! $match && $this->isEbay2ParentSku($sku)) {
                if ($dryRun) {
                    $allResults[] = [
                        'listing_id' => $lid,
                        'sku' => $sku,
                        'status' => 'would_create_campaign',
                        'reason' => $sku,
                    ];
                    $skipped++;
                    continue;
                }
                $createdCampaign = $this->createParentCpsCampaign($token, $sku);
                if ($createdCampaign) {
                    $campaigns[] = $createdCampaign;
                    $match = $createdCampaign;
                    $created++;
                }
            }

            if (! $match) {
                $allResults[] = [
                    'listing_id' => $lid,
                    'sku' => $sku,
                    'status' => 'skipped',
                    'reason' => 'No matching RUNNING PMT campaign for '.$sku,
                ];
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $allResults[] = [
                    'listing_id' => $lid,
                    'sku' => $sku,
                    'status' => 'would_enroll',
                    'reason' => $match['campaign_name'],
                    'campaign_id' => $match['campaign_id'],
                ];
                $success++;
                continue;
            }

            $out = $this->enrollListings([(string) $lid], $match['campaign_id'], $match['campaign_name']);
            if (! empty($out['error'])) {
                $allResults[] = [
                    'listing_id' => $lid,
                    'sku' => $sku,
                    'status' => 'failed',
                    'reason' => $out['error'],
                ];
                $failed++;
                continue;
            }
            foreach ($out['results'] as $row) {
                $row['campaign_name'] = $match['campaign_name'];
                $allResults[] = $row;
            }
            $success += (int) ($out['success'] ?? 0);
            $failed += (int) ($out['failed'] ?? 0);
            $skipped += (int) ($out['skipped'] ?? 0);
            usleep(200000);
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'skipped' => $skipped,
            'created_campaigns' => $created,
            'results' => $allResults,
        ];
    }

    /** @return list<string> */
    private function eligibleListingIdsForAutoEnroll(): array
    {
        $liveIds = DB::table('ebay2_campaign_ads')
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->whereRaw("UPPER(TRIM(COALESCE(campaign_status, ''))) IN ('RUNNING', 'PAUSED', 'SYSTEM_PAUSED')")
            ->pluck('listing_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $query = DB::table('ebay2_campaign_ads as ca')
            ->leftJoin('ebay_2_metrics as em', function ($join) {
                $join->on('em.item_id', '=', 'ca.listing_id')
                    ->whereRaw("em.id = (
                        SELECT em2.id FROM ebay_2_metrics em2
                        WHERE em2.item_id = ca.listing_id
                        ORDER BY CASE WHEN UPPER(TRIM(em2.sku)) LIKE 'PARENT%' THEN 0 ELSE 1 END, em2.id
                        LIMIT 1
                    )");
            })
            ->where('ca.promote_with_ad', 'RECOMMENDED')
            ->whereRaw("UPPER(TRIM(COALESCE(em.listing_status, ''))) = 'ACTIVE'")
            ->whereRaw("COALESCE(em.sku, ca.sku) IS NOT NULL")
            ->whereRaw("COALESCE(em.sku, ca.sku) != ''")
            ->whereRaw('COALESCE(em.ebay_price, ca.price) > 0')
            ->whereRaw("(SELECT ss.inv FROM shopify_skus ss WHERE ss.sku = COALESCE(em.sku, ca.sku) LIMIT 1) > 0");

        if ($liveIds !== []) {
            $query->whereNotIn('ca.listing_id', $liveIds);
        }

        return $query
            ->distinct()
            ->pluck('ca.listing_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function resolvedSkuForListing(string $listingId): string
    {
        $metric = Ebay2Metric::query()
            ->where('item_id', $listingId)
            ->orderByRaw("CASE WHEN UPPER(TRIM(sku)) LIKE 'PARENT%' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
        if ($metric && trim((string) $metric->sku) !== '') {
            return trim((string) $metric->sku);
        }

        return trim((string) (DB::table('ebay2_campaign_ads')->where('listing_id', $listingId)->value('sku') ?? ''));
    }

    private function productMasterParent(string $sku): ?string
    {
        $norm = $this->normSku($sku);
        if ($norm === '') {
            return null;
        }

        $row = ProductMaster::whereNull('deleted_at')
            ->where(function ($q) use ($norm) {
                $q->whereRaw('UPPER(TRIM(sku)) = ?', [$norm])
                    ->orWhereRaw('UPPER(TRIM(sku)) = ?', ['PARENT '.$this->ebay2ParentKey($norm)]);
            })
            ->first(['parent', 'sku']);

        $parent = trim((string) ($row->parent ?? ''));
        if ($parent !== '') {
            return $parent;
        }
        if ($row && $this->isEbay2ParentSku((string) $row->sku)) {
            return $this->ebay2ParentKey((string) $row->sku);
        }

        return $this->isEbay2ParentSku($sku) ? $this->ebay2ParentKey($sku) : null;
    }

    /**
     * @param  list<array{campaign_id:string,campaign_name:string}>  $campaigns
     * @return array{campaign_id:string,campaign_name:string}|null
     */
    private function matchCampaignForSku(string $sku, array $campaigns, ?string $parent = null): ?array
    {
        $candidates = [];
        $norm = $this->normSku($sku);
        if ($norm !== '') {
            $candidates[] = $norm;
        }
        if ($this->isEbay2ParentSku($sku)) {
            $key = $this->ebay2ParentKey($sku);
            $candidates[] = $this->normSku('PARENT '.$key);
            $candidates[] = $key;
        }
        if ($parent) {
            $candidates[] = $this->normSku($parent);
            $candidates[] = $this->normSku('PARENT '.$parent);
        }
        $candidates = array_values(array_unique(array_filter($candidates)));

        $byName = [];
        foreach ($campaigns as $c) {
            $name = $this->normSku($c['campaign_name'] ?? '');
            if ($name !== '' && ! isset($byName[$name])) {
                $byName[$name] = $c;
            }
        }
        foreach ($candidates as $cand) {
            if (isset($byName[$cand])) {
                return $byName[$cand];
            }
        }

        return null;
    }

    /**
     * @return list<array{campaign_id:string,campaign_name:string}>
     */
    private function fetchRunningCpsCampaigns(string $token): array
    {
        try {
            $all = [];
            $offset = 0;
            $limit = 200;
            do {
                $resp = \Illuminate\Support\Facades\Http::withToken($token)
                    ->get('https://api.ebay.com/sell/marketing/v1/ad_campaign', [
                        'limit' => $limit,
                        'offset' => $offset,
                    ]);
                $data = $resp->json();
                $batch = $data['campaigns'] ?? [];
                $total = (int) ($data['total'] ?? 0);
                $all = array_merge($all, $batch);
                $offset += $limit;
            } while (count($all) < $total && $batch !== []);

            $out = [];
            foreach ($all as $c) {
                $funding = $c['fundingStrategy']['fundingModel'] ?? null;
                $status = strtoupper((string) ($c['campaignStatus'] ?? ''));
                $id = (string) ($c['campaignId'] ?? '');
                if ($funding !== 'COST_PER_SALE' || $status !== 'RUNNING' || $id === '') {
                    continue;
                }
                $out[] = [
                    'campaign_id' => $id,
                    'campaign_name' => (string) ($c['campaignName'] ?? ''),
                ];
            }
            if ($out !== []) {
                return $out;
            }
        } catch (\Exception $e) {
            // fall through to local table
        }

        return DB::table('ebay2_campaign_ads')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->where('campaign_status', 'RUNNING')
            ->whereNotNull('campaign_id')
            ->select('campaign_id', 'campaign_name')
            ->distinct()
            ->get()
            ->map(fn ($r) => [
                'campaign_id' => (string) $r->campaign_id,
                'campaign_name' => (string) $r->campaign_name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{campaign_id:string,campaign_name:string}|null
     */
    private function createParentCpsCampaign(string $token, string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        try {
            $resp = \Illuminate\Support\Facades\Http::withToken($token)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-EBAY-C-MARKETPLACE-ID' => 'EBAY-US',
                ])
                ->post('https://api.ebay.com/sell/marketing/v1/ad_campaign', [
                    'campaignName' => $name,
                    'startDate' => now('UTC')->format('Y-m-d\TH:i:s.000\Z'),
                    'marketplaceId' => 'EBAY_US',
                    'fundingStrategy' => [
                        'fundingModel' => 'COST_PER_SALE',
                        'bidPercentage' => '7.0',
                    ],
                ]);
            if (! $resp->successful() && $resp->status() !== 201) {
                return null;
            }
            $data = $resp->json();
            $id = (string) ($data['campaignId'] ?? '');
            if ($id === '') {
                return null;
            }

            return [
                'campaign_id' => $id,
                'campaign_name' => (string) ($data['campaignName'] ?? $name),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function enrolledAdsUpdate(string $campaignId, ?string $campaignName, float $bid, $adId): array
    {
        $row = [
            'campaign_id' => $campaignId,
            'funding_strategy' => 'COST_PER_SALE',
            'campaign_status' => 'RUNNING',
            'bid_percentage' => $bid,
            'promote_with_ad' => 'AD_ALREADY_CREATED',
            'ad_id' => $adId,
            'updated_at' => now(),
        ];
        if ($campaignName !== null && $campaignName !== '') {
            $row['campaign_name'] = $campaignName;
        }

        return $row;
    }

    /** Shared Ebay 1 View VS SBID slabs (For L7 Views → S Bid). */
    private function sbidSlabs(): array
    {
        $slabRow = DB::table('ebay_sbid_rules')->where('key', self::SBID_SLABS_KEY)->first();
        $slabs   = $slabRow ? (json_decode($slabRow->rule, true)['rules'] ?? []) : [];
        if (!is_array($slabs) || $slabs === []) {
            return $this->defaultSbidSlabRules();
        }

        return $slabs;
    }

    /** Default View VS SBID slabs: 0–100, 101–200, … 901–1000, then >1000. */
    private function defaultSbidSlabRules(): array
    {
        $rules = [];
        $bid = 15;
        for ($i = 0; $i < 10; $i++) {
            $min = $i === 0 ? 0 : ($i * 100) + 1;
            $max = ($i + 1) * 100;
            $rules[] = [
                'label' => $min.'–'.$max,
                'l7_views_min' => $min,
                'l7_views_max' => $max,
                'sbid' => $bid,
            ];
            $bid--;
        }
        $rules[] = [
            'label' => '>1000',
            'l7_views_min' => 1001,
            'l7_views_max' => null,
            'sbid' => $bid,
        ];

        return $rules;
    }

    /** Resolve S Bid from View VS SBID slabs (first matching L7 Views range wins). */
    private function resolveSlabBid(float $cvr, float $dil, float $esold, float $views, float $l7Views, array $slabs): float
    {
        foreach ($slabs as $s) {
            if ($this->slabInRange($l7Views, $s['l7_views_min'] ?? null, $s['l7_views_max'] ?? null)) {
                return (float) ($s['sbid'] ?? 0);
            }
        }

        return 0.0;
    }

    private function isEbay2ParentSku(string $sku): bool
    {
        return stripos($sku, 'PARENT') !== false;
    }

    private function ebay2ParentKey(string $sku, ?string $parentField = null): string
    {
        $sku = trim($sku);
        if (stripos($sku, 'PARENT') !== false) {
            return strtoupper(trim((string) preg_replace('/^PARENT\s+/i', '', $sku)));
        }

        return strtoupper(trim((string) $parentField));
    }

    /** @return array<int, string> */
    private function familySkusForParentKey(string $parentKey): array
    {
        $parentKey = trim($parentKey);
        if ($parentKey === '') {
            return [];
        }
        $upper = strtoupper($parentKey);

        return ProductMaster::whereNull('deleted_at')
            ->where(function ($q) use ($upper) {
                $q->whereRaw('UPPER(TRIM(parent)) = ?', [$upper])
                    ->orWhereRaw('UPPER(TRIM(sku)) = ?', ['PARENT ' . $upper])
                    ->orWhereRaw('UPPER(TRIM(sku)) = ?', [$upper]);
            })
            ->pluck('sku')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function slabInRange(float $val, $min, $max): bool
    {
        if ($min !== null && $min !== '' && $val < (float) $min) return false;
        if ($max !== null && $max !== '' && $val > (float) $max) return false;
        return true;
    }

    private function normSku(?string $s): string
    {
        $s = (string)$s;
        $s = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xE2\x80\x87", "\xE2\x80\x8B"], ' ', $s);
        return strtoupper(preg_replace('/\s+/u', ' ', trim($s)));
    }

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

    /** Default SCVR bands — kept for legacy getRule/saveRule. */
    private function defaultRule(): array
    {
        return [
            'l7_views_threshold'  => 70,
            'l30_sold_es_bid_max' => 0,
            'bands' => [
                ['scvr_max' => 4,    'bid' => 9.1, 'label' => 'Red',    'color' => '#dc3545'],
                ['scvr_max' => 7,    'bid' => 7.1, 'label' => 'Yellow', 'color' => '#ffc107'],
                ['scvr_max' => 13,   'bid' => 4.1, 'label' => 'Green',  'color' => '#198754'],
                ['scvr_max' => 9999, 'bid' => 2.1, 'label' => 'Pink',   'color' => '#e83e8c'],
            ]
        ];
    }

    /**
     * Missing ads: listing is not in any campaign, has a SKU match, a price, and inv > 0.
     */
    protected function cbidNullInStockCount(): int
    {
        return $this->missingAdsCountFor('ebay2_campaign_ads', 'ebay_2_metrics');
    }

    /**
     * Ended / missing-metrics campaign-ads rows (OPEN BOX, old item ids) →
     * current ACTIVE listing in ebay_2_metrics, then enroll uses that id.
     */
    private function remapStaleEligibleToLiveListings(): void
    {
        $stale = DB::table('ebay2_campaign_ads')
            ->where(function ($q) {
                $q->whereNull('campaign_id')
                    ->orWhere('campaign_id', '')
                    ->orWhereRaw("UPPER(TRIM(COALESCE(campaign_status, ''))) IN ('ENDED', 'INACTIVE')");
            })
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['listing_id', 'sku']);

        foreach ($stale as $row) {
            $oldId = (string) $row->listing_id;
            $live = EbayCampaignEndedListingRemap::resolveLiveListing(
                (string) $row->sku,
                $oldId,
                null,
                Ebay2Metric::class
            );
            $liveId = trim((string) ($live['listing_id'] ?? ''));
            if ($liveId === '' || $liveId === $oldId) {
                continue;
            }
            EbayCampaignEndedListingRemap::remapAdsRow(
                'ebay2_campaign_ads',
                $oldId,
                $liveId,
                $live['sku'] !== '' ? $live['sku'] : (string) $row->sku
            );
        }
    }

    /**
     * Eligible listings live in ebay_2_metrics but were never inserted into
     * ebay2_campaign_ads (apicentral.ebay2_metrics gap). Searching then filtering
     * Eligible (RECOMMENDED) returns an empty grid. Backfill matching ACTIVE
     * listings from the Recommendation API so they show and can be enrolled.
     */
    private function backfillMissingEligibleForSearch($search): void
    {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $metrics = DB::table('ebay_2_metrics')
            ->whereNotNull('item_id')
            ->where('item_id', '!=', '')
            ->whereRaw("UPPER(TRIM(COALESCE(listing_status, ''))) = 'ACTIVE'")
            ->where(function ($q) use ($search) {
                $q->where('sku', 'like', '%'.$search.'%')
                    ->orWhere('item_id', 'like', '%'.$search.'%');
            })
            ->select('item_id', 'sku', 'ebay_price')
            ->get();

        if ($metrics->isEmpty()) {
            return;
        }

        $byListing = $metrics->groupBy(fn ($m) => (string) $m->item_id)->map(function ($rows) {
            return $rows->first(fn ($r) => stripos((string) $r->sku, 'PARENT') === 0) ?? $rows->first();
        });

        $listingIds = $byListing->keys()->values();
        $existing = DB::table('ebay2_campaign_ads')
            ->whereIn('listing_id', $listingIds)
            ->pluck('listing_id')
            ->map(fn ($id) => (string) $id)
            ->flip();

        $missing = $byListing->filter(fn ($m, $id) => ! $existing->has((string) $id));
        if ($missing->isEmpty()) {
            return;
        }

        try {
            $token = (new \App\Services\Ebay2ApiService())->generateBearerToken();
        } catch (\Exception $e) {
            foreach ($missing as $lid => $metric) {
                $this->insertEligibleCampaignAdRow((string) $lid, $metric, null, null);
            }
            return;
        }

        foreach ($missing->chunk(20) as $chunk) {
            $ids = $chunk->keys()->map(fn ($id) => (string) $id)->values()->all();
            try {
                $resp = Http::withToken($token)
                    ->withHeaders([
                        'X-EBAY-C-MARKETPLACE-ID' => 'EBAY-US',
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://api.ebay.com/sell/recommendation/v1/find?filter=recommendationTypes:{AD}&limit=20',
                        ['listingIds' => $ids]);

                $recs = collect($resp->json()['listingRecommendations'] ?? [])->keyBy(fn ($r) => (string) ($r['listingId'] ?? ''));
                foreach ($chunk as $lid => $metric) {
                    $lid = (string) $lid;
                    $rec = $recs->get($lid);
                    $promote = $rec['marketing']['ad']['promoteWithAd'] ?? null;
                    $suggestedBid = null;
                    foreach ($rec['marketing']['ad']['bidPercentages'] ?? [] as $b) {
                        if (($b['basis'] ?? '') === 'ITEM' && isset($b['value'])) {
                            $suggestedBid = (float) $b['value'];
                            break;
                        }
                    }
                    if ($suggestedBid === null) {
                        foreach ($rec['marketing']['ad']['bidPercentages'] ?? [] as $b) {
                            if (($b['basis'] ?? '') === 'TRENDING' && isset($b['value'])) {
                                $suggestedBid = (float) $b['value'];
                                break;
                            }
                        }
                    }
                    $this->insertEligibleCampaignAdRow($lid, $metric, $promote, $suggestedBid);
                }
            } catch (\Exception $e) {
                foreach ($chunk as $lid => $metric) {
                    $this->insertEligibleCampaignAdRow((string) $lid, $metric, null, null);
                }
            }
        }
    }

    private function insertEligibleCampaignAdRow(string $listingId, object $metric, ?string $promote, $suggestedBid): void
    {
        DB::table('ebay2_campaign_ads')->updateOrInsert(
            ['listing_id' => $listingId, 'campaign_id' => null],
            [
                'campaign_name' => null,
                'funding_strategy' => null,
                'campaign_status' => null,
                'ad_id' => null,
                'sku' => $metric->sku ?? null,
                'bid_percentage' => null,
                'suggested_bid' => $suggestedBid,
                'price' => $metric->ebay_price ?? null,
                'promote_with_ad' => $promote,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function getData(Request $request)
    {
        EbayCampaignEndedListingRemap::remapEndedRows('ebay2_campaign_ads', Ebay2Metric::class);
        $this->remapStaleEligibleToLiveListings();
        $this->backfillMissingEligibleForSearch($request->input('search'));

        $query = DB::table('ebay2_campaign_ads as ca')
            ->leftJoin('ebay_2_metrics as em', function ($join) {
                $join->on('em.item_id', '=', 'ca.listing_id')
                    ->whereRaw("em.id = (
                        SELECT em2.id FROM ebay_2_metrics em2
                        WHERE em2.item_id = ca.listing_id
                        ORDER BY CASE WHEN UPPER(TRIM(em2.sku)) LIKE 'PARENT%' THEN 0 ELSE 1 END, em2.id
                        LIMIT 1
                    )");
            })
            ->select(
                'ca.*',
                // Use SKU from ebay_2_metrics if matched, fallback to listing_id
                DB::raw("COALESCE(em.sku, ca.sku, ca.listing_id) as resolved_sku"),
                DB::raw("CASE WHEN COALESCE(em.sku, ca.sku) IS NOT NULL AND COALESCE(em.sku, ca.sku) != '' THEN 1 ELSE 0 END as sku_matched"),
                DB::raw("COALESCE(em.ebay_price, ca.price) as metric_price"),
                'em.views',
                'em.l7_views',
                'em.ebay_l30',
                'em.listing_status',
                // Dilution inputs (from shopify_skus, matched by sku). Correlated subqueries
                // avoid row multiplication and keep every ad row visible even when unmatched.
                // DIL = (quantity / inv) * 100  — quantity = L30 sold, inv = stock on hand.
                DB::raw("(SELECT ss.inv FROM shopify_skus ss WHERE ss.sku = COALESCE(em.sku, ca.sku) LIMIT 1) as shopify_inv"),
                DB::raw("(SELECT ss.quantity FROM shopify_skus ss WHERE ss.sku = COALESCE(em.sku, ca.sku) LIMIT 1) as shopify_qty")
            );

        if ($request->filled('funding_strategy')) {
            $query->where('ca.funding_strategy', $request->funding_strategy);
        }
        if ($request->filled('campaign_status')) {
            $status = strtoupper(trim((string) $request->campaign_status));
            if ($status === 'ENDED') {
                $query->where(function ($q) {
                    $q->whereRaw("UPPER(TRIM(COALESCE(ca.campaign_status, ''))) IN ('ENDED', 'INACTIVE')")
                        ->orWhereRaw("UPPER(TRIM(COALESCE(em.listing_status, ''))) IN ('ENDED', 'INACTIVE', 'UNSOLD', 'COMPLETED', 'SOLD')");
                });
            } else {
                $query->where('ca.campaign_status', $request->campaign_status);
            }
        }
        if ($request->filled('promote_with_ad')) {
            $promote = $request->promote_with_ad;
            if ($promote === '__NONE__') {
                $query->where(function ($q) {
                    $q->whereNull('ca.promote_with_ad')
                      ->orWhere('ca.promote_with_ad', '');
                });
            } elseif ($promote === 'RECOMMENDED') {
                // Seller Hub Eligible = can still start an ad. Exclude ended
                // listings and anything already RUNNING/PAUSED (those still
                // often have promote_with_ad=RECOMMENDED leftover).
                $query->where(function ($q) {
                    $q->where('ca.promote_with_ad', 'RECOMMENDED')
                        ->orWhere(function ($q2) {
                            $q2->whereNull('ca.promote_with_ad')
                                ->orWhere('ca.promote_with_ad', '');
                        });
                })
                ->whereRaw("UPPER(TRIM(COALESCE(em.listing_status, ''))) = 'ACTIVE'")
                ->where(function ($q) {
                    $q->whereNull('ca.campaign_id')
                        ->orWhere('ca.campaign_id', '')
                        ->orWhereRaw("UPPER(TRIM(COALESCE(ca.campaign_status, ''))) IN ('ENDED', 'INACTIVE')");
                })
                ->whereNotExists(function ($q) {
                    $q->selectRaw('1')
                        ->from('ebay2_campaign_ads as live')
                        ->whereColumn('live.listing_id', 'ca.listing_id')
                        ->whereNotNull('live.campaign_id')
                        ->where('live.campaign_id', '!=', '')
                        ->whereRaw("UPPER(TRIM(COALESCE(live.campaign_status, ''))) IN ('RUNNING', 'PAUSED', 'SYSTEM_PAUSED')");
                });
            } elseif ($promote === 'AD_ALREADY_CREATED') {
                $query->where(function ($q) {
                    $q->where('ca.promote_with_ad', 'AD_ALREADY_CREATED')
                        ->orWhereRaw("UPPER(TRIM(COALESCE(ca.campaign_status, ''))) IN ('RUNNING', 'PAUSED', 'SYSTEM_PAUSED')");
                });
            } else {
                $query->where('ca.promote_with_ad', $promote);
            }
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('em.sku', 'like', "%{$search}%")
                  ->orWhere('ca.sku', 'like', "%{$search}%")
                  ->orWhere('ca.listing_id', 'like', "%{$search}%")
                  ->orWhere('ca.campaign_name', 'like', "%{$search}%")
                  ->orWhereExists(function ($q2) use ($search) {
                      $q2->selectRaw('1')
                          ->from('ebay_2_metrics as ems')
                          ->whereColumn('ems.item_id', 'ca.listing_id')
                          ->where('ems.sku', 'like', "%{$search}%");
                  });
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
     * Single eBay 2 row for /advertisement-master — KW + PMT from the latest
     * L30 snapshot on ebay_2_priority_reports and ebay_2_general_reports.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdvertisementMasterChannelRows(): array
    {
        $kwMetrics = $this->advertisementMasterKwMetrics();
        $pmtMetrics = $this->advertisementMasterPmtMetrics();

        return [
            self::advertisementMasterMetricRow('eBay 2', 'ebay2', (object) [
                'spend'  => $kwMetrics['spend'] + $pmtMetrics['spend'],
                'clicks' => $kwMetrics['clicks'] + $pmtMetrics['clicks'],
                'sold'   => $kwMetrics['sold'] + $pmtMetrics['sold'],
                'sales'  => $kwMetrics['sales'] + $pmtMetrics['sales'],
                'active' => $this->advertisementMasterActiveCount('ebay_2_priority_reports', 'ebay_2_campaign_ads'),
            ], false),
        ];
    }

    /**
     * Active (RUNNING) eBay 2 campaigns — keyword (CPC) L30 rows + promoted (CPS) campaigns.
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

    public static function advertisementMasterNetSales(): float
    {
        try {
            $metrics = EbayChannelMetricsService::latestDailyMetrics('eBay 2');

            return round((float) ($metrics?->total_sales ?? 0), 2);
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master eBay 2 net sales lookup failed: '.$e->getMessage());

            return 0.0;
        }
    }

    /**
     * @return array{spend: float, clicks: int, sold: int, sales: float}
     */
    protected function advertisementMasterKwMetrics(): array
    {
        return $this->advertisementMasterReportMetrics('ebay_2_priority_reports', 'kw');
    }

    /**
     * @return array{spend: float, clicks: int, sold: int, sales: float}
     */
    protected function advertisementMasterPmtMetrics(): array
    {
        return $this->advertisementMasterReportMetrics('ebay_2_general_reports', 'pmt');
    }

    /**
     * L30 rollup for /ebay2/campaign-ads badges and /advertisement-master.
     * L30 is the last 31 calendar days including today (Seller Hub "Past 31 days").
     * Only the latest sync day is summed — stale leftovers inflate spend.
     *
     * @return array{spend: float, clicks: int, sold: int, sales: float}
     */
    private function advertisementMasterReportMetrics(string $table, string $type): array
    {
        $empty = ['spend' => 0.0, 'clicks' => 0, 'sold' => 0, 'sales' => 0.0];

        if (! Schema::hasTable($table)) {
            return $empty;
        }

        $query = DB::table($table)->whereRaw("UPPER(TRIM(report_range)) = 'L30'");
        EbayCampaignReportRollup::restrictToLatestL30Snapshot($query, $table);

        if ($type === 'kw') {
            $row = $query
                ->selectRaw('COALESCE(SUM(cpc_clicks), 0) as clicks')
                ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")), 0) as sales')
                ->selectRaw('COALESCE(SUM(cpc_attributed_sales), 0) as sold')
                ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")), 0) as spend')
                ->first();
        } else {
            $row = $query
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
            'channel_key' => $channel,
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
            'marketplace' => 'ebay2',
        ];
    }
}
