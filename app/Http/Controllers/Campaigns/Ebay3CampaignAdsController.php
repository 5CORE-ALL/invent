<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Campaigns\Concerns\ProvidesEbayCampaignAdsBadgeSummary;
use App\Http\Controllers\Controller;
use App\Services\EbayChannelMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;

/**
 * eBay 3 mirror of {@see EbayCampaignAdsController} / {@see Ebay2CampaignAdsController}
 * — same SBID + DIL rule logic but driven off the eBay-3 dataset:
 *   - Campaign data: `ebay3_campaign_ads`
 *   - Metrics:       `ebay_3_metrics` (App\Models\Ebay3Metric)
 *   - Rule keys:     `ebay3_sbid_views` (Sbid Views caps / daily steps) and
 *                    `ebay3_dil` (DIL colour bands) in `ebay_sbid_rules`
 *                    (`ebay3` SCVR bands kept only for /ebay3-tabulator-view)
 *   - Token / push:  EbayThreeApiService + `ebay3:update-suggestedbid`
 */
class Ebay3CampaignAdsController extends Controller
{
    use ProvidesEbayCampaignAdsBadgeSummary;

    /**
     * Sbid (Views) settings — Min/Max caps + per-colour daily direction/step.
     * Stored under key `ebay3_sbid_views` (mirrors ebay1_sbid_views).
     */
    public function getSbidViewsRule()
    {
        return response()->json(\App\Support\SbidViewsRule::settings(\App\Support\SbidViewsRule::KEY_EBAY3));
    }

    public function saveSbidViewsRule(Request $request)
    {
        $settings = \App\Support\SbidViewsRule::sanitize($request->all());

        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => \App\Support\SbidViewsRule::KEY_EBAY3],
            ['rule' => json_encode($settings), 'updated_at' => now()]
        );

        return response()->json(['success' => true, 'rule' => $settings]);
    }

    public function index()
    {
        $dil = DB::table('ebay_sbid_rules')->where('key', 'ebay3_dil')->first();
        $dilData = $dil ? json_decode($dil->rule, true) : $this->defaultDilRule();

        return view('campaign.ebay3-campaign-ads', [
            'dilRule' => $dilData,
        ]);
    }

    public function getRule()
    {
        $rule = DB::table('ebay_sbid_rules')->where('key', 'ebay3')->first();
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
            ['key' => 'ebay3'],
            ['rule' => json_encode($rule), 'updated_at' => now()]
        );

        return response()->json(['success' => true, 'rule' => $rule]);
    }

    /**
     * Dilution rule — DIL% color bands stored under key `ebay3_dil` in
     * `ebay_sbid_rules`. DIL = (L30 sold / inventory) * 100. Bands evaluated
     * ascending by dil_max — first band where DIL <= dil_max wins.
     */
    public function getDilRule()
    {
        $rule = DB::table('ebay_sbid_rules')->where('key', 'ebay3_dil')->first();
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
            ['key' => 'ebay3_dil'],
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

        $metrics = \App\Models\Ebay3Metric::whereIn('item_id', $listingIds)->get()->keyBy('item_id');

        // Sbid (Views): settings + avg L7 views (prefer the UI's avg to match screen).
        $sbidViewsSettings = \App\Support\SbidViewsRule::settings(\App\Support\SbidViewsRule::KEY_EBAY3);
        $avgL7Views = $request->input('avg_l7_views');
        if (!is_numeric($avgL7Views)) {
            $l7Sum = 0.0; $l7Count = 0;
            foreach ($metrics as $m) { $l7Sum += (float) ($m->l7_views ?? 0); $l7Count++; }
            $avgL7Views = $l7Count > 0 ? ($l7Sum / $l7Count) : 0.0;
        }
        $avgL7Views = (float) $avgL7Views;

        $ads = DB::table('ebay3_campaign_ads')
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
            $service = new \App\Services\EbayThreeApiService();
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

            // Sbid (Views): adjust the current C Bid (bid_percentage) by the row's
            // L7 View band (direction/step), clamped to caps. No C Bid → skip.
            $metric  = $metrics->get($lid);
            $baseBid = (float) ($ad->bid_percentage ?? 0);
            $l7views = (float) ($metric?->l7_views ?? 0);
            $el30Sold = (float) ($metric?->ebay_l30 ?? 0);
            $newBid  = \App\Support\SbidViewsRule::apply($baseBid, $l7views, $avgL7Views, $sbidViewsSettings, $el30Sold);
            if ($newBid <= 0) {
                $results[] = ['listing_id' => $lid, 'status' => 'skipped', 'reason' => 'No current C Bid to adjust'];
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

    public function getCampaignList()
    {
        try {
            $service = new \App\Services\EbayThreeApiService();
            $token   = $service->generateBearerToken();

            $all    = [];
            $offset = 0;
            $limit  = 200;

            do {
                $resp  = Http::withToken($token)
                    ->get('https://api.ebay.com/sell/marketing/v1/ad_campaign', [
                        'limit'  => $limit,
                        'offset' => $offset,
                    ]);
                $data  = $resp->json();
                $batch = $data['campaigns'] ?? [];
                $total = (int)($data['total'] ?? 0);
                $all   = array_merge($all, $batch);
                $offset += $limit;
            } while (count($all) < $total && !empty($batch));

            $statusRank = ['RUNNING' => 0, 'PAUSED' => 1, 'SYSTEM_PAUSED' => 2];

            $campaigns = collect($all)
                ->filter(function ($c) {
                    $funding = $c['fundingStrategy']['fundingModel'] ?? null;
                    $status  = $c['campaignStatus'] ?? null;
                    return $funding === 'COST_PER_SALE' && $status !== 'ENDED';
                })
                ->map(fn($c) => [
                    'campaign_id'     => (string)($c['campaignId'] ?? ''),
                    'campaign_name'   => $c['campaignName'] ?? '',
                    'campaign_status' => $c['campaignStatus'] ?? '',
                    'start_date'      => $c['startDate'] ?? '',
                ])
                ->filter(fn($c) => $c['campaign_id'] !== '')
                ->sort(function ($a, $b) use ($statusRank) {
                    $rankA = $statusRank[$a['campaign_status']] ?? 99;
                    $rankB = $statusRank[$b['campaign_status']] ?? 99;
                    if ($rankA !== $rankB) {
                        return $rankA <=> $rankB;
                    }
                    return strcmp($b['start_date'], $a['start_date']);
                })
                ->values()
                ->map(function ($c, $i) {
                    $c['is_default'] = ($i === 0);
                    unset($c['start_date']);
                    return $c;
                });

            return response()->json($campaigns);
        } catch (\Exception $e) {
            // Fallback: local rows (only populated when sync step 1 found in-campaign ads).
            $campaigns = DB::table('ebay3_campaign_ads')
                ->where('funding_strategy', 'COST_PER_SALE')
                ->whereNotIn('campaign_status', ['ENDED'])
                ->whereNotNull('campaign_id')
                ->select('campaign_id', 'campaign_name', 'campaign_status')
                ->distinct()
                ->orderBy('campaign_name')
                ->get();

            return response()->json($campaigns);
        }
    }

    public function enrollInCampaign(Request $request)
    {
        $listingIds = $request->input('listing_ids', []);
        $campaignId = $request->input('campaign_id');

        if (empty($listingIds) || !$campaignId) {
            return response()->json(['error' => 'listing_ids and campaign_id required'], 422);
        }

        // New enrollments have no C Bid yet — start from ES Bid (suggested_bid),
        // falling back to the Sbid (Views) min cap. Daily cron then adjusts via Views.
        $sbidViewsSettings = \App\Support\SbidViewsRule::settings(\App\Support\SbidViewsRule::KEY_EBAY3);
        $minCap = (float) ($sbidViewsSettings['min_cap'] ?? 1);

        $ads = DB::table('ebay3_campaign_ads')
            ->whereIn('listing_id', $listingIds)
            ->get()
            ->keyBy('listing_id');

        $metrics = \App\Models\Ebay3Metric::whereIn('item_id', $listingIds)
            ->get()->keyBy('item_id');

        try {
            $service = new \App\Services\EbayThreeApiService();
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
            $adRow  = $ads->get($lid);
            $esBid  = (float)($adRow?->suggested_bid ?? 0);
            $bid    = $esBid > 0 ? $esBid : $minCap;

            if ($bid <= 0) {
                $results[] = ['listing_id' => $lid, 'sku' => $metric?->sku, 'status' => 'skipped', 'reason' => 'No ES Bid and no Sbid Views min cap'];
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
                    DB::table('ebay3_campaign_ads')
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
            Artisan::call('ebay3:update-suggestedbid');
            $output = Artisan::output();
            return response()->json(['success' => true, 'output' => $output]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Default SCVR bands — kept for /ebay3-tabulator-view via getRule/saveRule. */
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
        $query = DB::table('ebay3_campaign_ads as ca')
            ->leftJoin('ebay_3_metrics as em', 'em.item_id', '=', 'ca.listing_id')
            ->select(
                'ca.*',
                // Use SKU from ebay_3_metrics if matched, fallback to listing_id
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
     * Single eBay 3 row for /advertisement-master — KW + PMT combined from daily
     * ebay_3_priority_reports and ebay_3_general_reports (31-day window).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdvertisementMasterChannelRows(): array
    {
        $kwMetrics = $this->advertisementMasterKwMetrics();
        $pmtMetrics = $this->advertisementMasterPmtMetrics();

        return [
            self::advertisementMasterMetricRow('eBay 3', 'ebay3', (object) [
                'spend'  => $kwMetrics['spend'] + $pmtMetrics['spend'],
                'clicks' => $kwMetrics['clicks'] + $pmtMetrics['clicks'],
                'sold'   => $kwMetrics['sold'] + $pmtMetrics['sold'],
                'sales'  => $kwMetrics['sales'] + $pmtMetrics['sales'],
                'active' => $this->advertisementMasterActiveCount('ebay_3_priority_reports', 'ebay_3_campaign_ads'),
            ], false),
        ];
    }

    /**
     * Active (RUNNING) eBay 3 campaigns — keyword (CPC) L30 rows + promoted (CPS) campaigns.
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
            $metrics = EbayChannelMetricsService::latestDailyMetrics('eBay 3');

            return round((float) ($metrics?->total_sales ?? 0), 2);
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master eBay 3 net sales lookup failed: '.$e->getMessage());

            return 0.0;
        }
    }

    /**
     * @return array{spend: float, clicks: int, sold: int, sales: float}
     */
    protected function advertisementMasterKwMetrics(): array
    {
        return $this->advertisementMasterReportMetrics('ebay_3_priority_reports', 'kw');
    }

    /**
     * @return array{spend: float, clicks: int, sold: int, sales: float}
     */
    protected function advertisementMasterPmtMetrics(): array
    {
        return $this->advertisementMasterReportMetrics('ebay_3_general_reports', 'pmt');
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
            'marketplace' => 'ebay3',
        ];
    }
}
