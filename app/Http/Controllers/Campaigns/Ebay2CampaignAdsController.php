<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Campaigns\Concerns\ProvidesEbayCampaignAdsBadgeSummary;
use App\Http\Controllers\Controller;
use App\Models\Ebay2Metric;
use App\Models\ProductMaster;
use App\Services\EbayChannelMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * eBay 2 mirror of {@see EbayCampaignAdsController}
 * — own View VS SBID slabs (`ebay2_sbid_slabs`) + DIL, driven off eBay-2 data:
 *   - Campaign data: `ebay2_campaign_ads`
 *   - Metrics:       `ebay_2_metrics` (App\Models\Ebay2Metric)
 *   - Rule keys:     `ebay2_sbid_slabs` (For L7 Views → S Bid; EL30=0 → ES Bid)
 *                    and `ebay2_dil` (DIL colour bands) in `ebay_sbid_rules`
 *                    (`ebay2` SCVR bands kept only for legacy getRule/saveRule;
 *                     `ebay2_sbid_views` kept for /ebay2-tabulator-view)
 *   - Token / push:  Ebay2ApiService (Autopush on slab / 0-sold ES Bid change)
 *   - Tabulator is Parents Only: parent-row L7 Views / EL30 drive S Bid; push
 *     applies that family bid to every listing under the parent.
 */
class Ebay2CampaignAdsController extends Controller
{
    use ProvidesEbayCampaignAdsBadgeSummary;

    public const SBID_SLABS_KEY = 'ebay2_sbid_slabs';

    /**
     * Sbid (Views) settings — kept for /ebay2-tabulator-view.
     * Campaign-ads S Bid uses eBay-2-only slab rule (`ebay2_sbid_slabs`).
     */
    public function getSbidViewsRule()
    {
        return response()->json(\App\Support\SbidViewsRule::settings(\App\Support\SbidViewsRule::KEY_EBAY2));
    }

    /**
     * eBay 2 View VS SBID slabs (For L7 Views → S Bid; EL30=0 → ES Bid).
     * Stored under `ebay2_sbid_slabs` — not shared with eBay 1.
     */
    public function getSbidSlabRule()
    {
        $state = $this->sbidSlabState();

        return response()->json([
            'rules' => $state['rules'],
            'es_bid' => $state['es_bid'],
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

    /**
     * Default View VS SBID slabs: 0–100, 101–200, … 901–1000, then >1000.
     * Same steps as eBay 1 — eBay 2 stores its own copy.
     *
     * @return array<int, array<string, mixed>>
     */
    private function defaultSbidSlabRules(): array
    {
        $rules = [];
        $bid = 15;
        for ($i = 0; $i < 10; $i++) {
            $min = $i === 0 ? 0 : ($i * 100) + 1;
            $max = ($i + 1) * 100;
            $rules[] = [
                'label' => $min . '–' . $max,
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

    /** True when stored slabs are not yet the 0–100 / 101–200 / … / >1000 set. */
    private function sbidSlabsNeedViewStepMigrate(array $rules): bool
    {
        if ($rules === [] || count($rules) < 11) {
            return true;
        }
        $first = $rules[0] ?? [];

        return (float) ($first['l7_views_min'] ?? -1) !== 0.0
            || (float) ($first['l7_views_max'] ?? -1) !== 100.0;
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

        $state = $this->sbidSlabState();
        $slabs = $state['rules'];
        $esBidOverride = $state['es_bid'];

        $metrics = Ebay2Metric::whereIn('item_id', $listingIds)->get()->keyBy('item_id');

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
            $el30     = (float) ($metric?->ebay_l30 ?? 0);
            $l7Views  = (float) ($metric?->l7_views ?? 0);
            $esFallback = (float) ($ad->suggested_bid ?? 0);
            $newBid   = $this->resolveRowBid($el30, $l7Views, $esFallback, $slabs, $esBidOverride);
            if ($newBid <= 0) {
                $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => $el30 <= 0 ? 'EL30=0 and no ES Bid' : 'No matching Sbid Rule slab'];
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
     * Apply eBay 2 View VS SBID slabs and push each computed S Bid
     * to its eBay 2 campaign. PARENT SKUs (tabulator Parents Only) use
     * family-aggregated L7 Views / EL30 and push that bid to every listing
     * under the parent.
     */
    public function pushSbidSlabsBySku(Request $request)
    {
        $skus = $request->input('skus', []);
        if (empty($skus) || !is_array($skus)) {
            return response()->json(['error' => 'No SKUs provided'], 422);
        }

        $state = $this->sbidSlabState();
        $slabs = $state['rules'];
        $esBidOverride = $state['es_bid'];

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
                $l7Views = 0.0;
                $esFallback = 0.0;
                $listingEntries = [];
                foreach ($familySkus as $fs) {
                    $metric = $metrics->get($this->normSku($fs));
                    if (! $metric) {
                        continue;
                    }
                    $el30 += (float) ($metric->ebay_l30 ?? 0);
                    $l7Views += (float) ($metric->l7_views ?? 0);
                    $lid = (string) ($metric->item_id ?? '');
                    if ($lid === '' || isset($listingEntries[$lid])) {
                        continue;
                    }
                    $ad = $ads->get($lid);
                    if (! $ad || ! $ad->campaign_id) {
                        continue;
                    }
                    if ($esFallback <= 0) {
                        $esFallback = (float) ($ad->suggested_bid ?? 0);
                    }
                    $listingEntries[$lid] = [
                        'listingId' => $lid,
                        'campaign_id' => $ad->campaign_id,
                        'sku' => $fs,
                    ];
                }
                $bid = $this->resolveRowBid($el30, $l7Views, $esFallback, $slabs, $esBidOverride);
                if ($bid <= 0 || $listingEntries === []) {
                    $why = $listingEntries === []
                        ? 'No eBay listing in a COST_PER_SALE campaign'
                        : ($el30 <= 0 ? 'EL30=0 and no ES Bid' : 'No matching Sbid Rule slab');
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

            $el30 = (float) ($metric->ebay_l30 ?? 0);
            $l7Views = (float) ($metric->l7_views ?? 0);
            $esFallback = (float) ($ad->suggested_bid ?? 0);
            $bid = $this->resolveRowBid($el30, $l7Views, $esFallback, $slabs, $esBidOverride);
            if ($bid <= 0) {
                $results[] = ['sku' => $sku, 'status' => 'skipped', 'reason' => $el30 <= 0 ? 'EL30=0 and no ES Bid' : 'No matching Sbid Rule slab'];
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

        $state = $this->sbidSlabState();
        $slabs = $state['rules'];
        $esBidOverride = $state['es_bid'];

        $ads = DB::table('ebay2_campaign_ads')
            ->whereIn('listing_id', $listingIds)
            ->get()
            ->keyBy('listing_id');

        $metrics = Ebay2Metric::whereIn('item_id', $listingIds)
            ->get()->keyBy('item_id');

        try {
            $service = new \App\Services\Ebay2ApiService();
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
            $el30 = (float) ($metric?->ebay_l30 ?? 0);
            $l7Views = (float) ($metric?->l7_views ?? 0);
            $adRow = $ads->get($lid);
            $esFallback = (float) ($adRow?->suggested_bid ?? 0);
            $bid = $this->resolveRowBid($el30, $l7Views, $esFallback, $slabs, $esBidOverride);

            if ($bid <= 0) {
                $results[] = ['listing_id' => $lid, 'sku' => $metric?->sku, 'status' => 'skipped', 'reason' => 'No matching Sbid Rule slab and no ES Bid'];
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

    /**
     * eBay 2–only View VS SBID slabs (For L7 Views → S Bid; EL30=0 → ES Bid).
     *
     * @return array{rules: array<int, array<string, mixed>>, es_bid: float|null}
     */
    private function sbidSlabState(): array
    {
        $row = DB::table('ebay_sbid_rules')->where('key', self::SBID_SLABS_KEY)->first();
        $decoded = $row ? json_decode($row->rule, true) : null;
        $rules = is_array($decoded['rules'] ?? null) ? $decoded['rules'] : [];
        $esBid = $this->numOrNull($decoded['es_bid'] ?? null);

        if ($this->sbidSlabsNeedViewStepMigrate($rules)) {
            $rules = $this->defaultSbidSlabRules();
            DB::table('ebay_sbid_rules')->updateOrInsert(
                ['key' => self::SBID_SLABS_KEY],
                ['rule' => json_encode(['rules' => $rules, 'es_bid' => $esBid]), 'updated_at' => now()]
            );
        }

        return ['rules' => $rules, 'es_bid' => $esBid];
    }

    /** @return array<int, array<string, mixed>> */
    private function sbidSlabs(): array
    {
        return $this->sbidSlabState()['rules'];
    }

    /**
     * EL30 = 0 → ES Bid override (or listing suggested_bid).
     * Otherwise first matching For L7 Views slab.
     */
    private function resolveRowBid(float $el30, float $l7Views, float $esBidFallback, array $slabs, ?float $esBidOverride): float
    {
        if ($el30 <= 0) {
            if ($esBidOverride !== null && $esBidOverride > 0) {
                return $esBidOverride;
            }

            return $esBidFallback > 0 ? $esBidFallback : 0.0;
        }

        return $this->resolveSlabBid($l7Views, $slabs);
    }

    /** Resolve S Bid from View VS SBID slabs (For L7 Views only). */
    private function resolveSlabBid(float $l7Views, array $slabs): float
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

    public function getData(Request $request)
    {
        $query = DB::table('ebay2_campaign_ads as ca')
            ->leftJoin('ebay_2_metrics as em', 'em.item_id', '=', 'ca.listing_id')
            ->select(
                'ca.*',
                // Use SKU from ebay_2_metrics if matched, fallback to listing_id
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
     * Single eBay 2 row for /advertisement-master — KW + PMT combined from daily
     * ebay_2_priority_reports and ebay_2_general_reports (31-day window).
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
            'marketplace' => 'ebay2',
        ];
    }
}
