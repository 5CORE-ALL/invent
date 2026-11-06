<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Campaigns\Concerns\ProvidesEbayCampaignAdsBadgeSummary;
use App\Http\Controllers\Controller;
use App\Models\Ebay3Metric;
use App\Services\EbayChannelMetricsService;
use App\Support\Marketplace\EbayCampaignEndedListingRemap;
use App\Support\CpMasterDil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * eBay 3 mirror of {@see EbayCampaignAdsController} / {@see Ebay2CampaignAdsController},
 * driven off eBay-3 data:
 *   - Campaign data: `ebay3_campaign_ads`
 *   - Metrics:       `ebay_3_metrics` (App\Models\Ebay3Metric)
 *   - Token / push:  EbayThreeApiService
 */
class Ebay3CampaignAdsController extends Controller
{
    use ProvidesEbayCampaignAdsBadgeSummary;

    public function index()
    {
        return view('campaign.ebay3-campaign-ads');
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

        $ads = DB::table('ebay3_campaign_ads')
            ->whereIn('listing_id', $listingIds)
            ->get()
            ->keyBy('listing_id');

        $metrics = Ebay3Metric::whereIn('item_id', $listingIds)
            ->get()->keyBy('item_id');
        $shopifyMap = $this->shopifyByNormSku(
            $ads->pluck('sku')->merge($metrics->pluck('sku'))->filter()->unique()->values()->all()
        );

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
        // Use live campaign status — do not assume RUNNING (eBay may SYSTEM_PAUSE).
        $liveStatus = $this->fetchCampaignStatus($token, (string) $campaignId) ?: 'RUNNING';

        foreach ($listingIds as $requestedId) {
            $resolved = EbayCampaignEndedListingRemap::resolveEnrollListing(
                'ebay3_campaign_ads',
                Ebay3Metric::class,
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

            $bid = (float) ($adRow?->suggested_bid ?? 0);

            if ($bid <= 0) {
                $results[] = ['listing_id' => $lid, 'sku' => $sku !== '' ? $sku : $metric?->sku, 'status' => 'skipped', 'reason' => 'No ES Bid'];
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
                        ->update([
                            'campaign_id'      => $campaignId,
                            'funding_strategy' => 'COST_PER_SALE',
                            'campaign_status'  => $liveStatus,
                            'bid_percentage'   => $bid,
                            'promote_with_ad'  => 'AD_ALREADY_CREATED',
                            'ad_id'            => $adData['adId'] ?? null,
                            'updated_at'       => now(),
                        ]);

                    $results[] = ['listing_id' => $lid, 'sku' => $sku !== '' ? $sku : $metric?->sku, 'status' => 'enrolled', 'bid' => $bid . '%'];
                    $success++;
                } else {
                    $errMsg = (string) ($resp->json()['errors'][0]['message'] ?? $resp->status());
                    if (EbayCampaignEndedListingRemap::isEndedListingError($errMsg) && $sku !== '') {
                        $liveId = EbayCampaignEndedListingRemap::liveItemIdForSku($sku, $lid, $token, Ebay3Metric::class);
                        if ($liveId !== '' && $liveId !== $lid) {
                            $lid = EbayCampaignEndedListingRemap::remapAdsRow('ebay3_campaign_ads', (string) $requestedId, $liveId, $sku);
                            $retry = \Illuminate\Support\Facades\Http::withToken($token)
                                ->withHeaders(['Content-Type' => 'application/json'])
                                ->post("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}/ad", [
                                    'listingId' => $lid,
                                    'bidPercentage' => (string) $bid,
                                ]);
                            if ($retry->successful() || $retry->status() === 201) {
                                $adData = $retry->json();
                                DB::table('ebay3_campaign_ads')->where('listing_id', $lid)->update([
                                    'campaign_id' => $campaignId,
                                    'funding_strategy' => 'COST_PER_SALE',
                                    'campaign_status' => $liveStatus,
                                    'bid_percentage' => $bid,
                                    'promote_with_ad' => 'AD_ALREADY_CREATED',
                                    'ad_id' => $adData['adId'] ?? null,
                                    'updated_at' => now(),
                                ]);
                                $results[] = ['listing_id' => $lid, 'sku' => $sku, 'status' => 'enrolled', 'bid' => $bid.'%', 'reason' => 'Remapped ended listing to '.$lid];
                                $success++;
                                continue;
                            }
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

        return response()->json([
            'success' => $success,
            'failed'  => $failed,
            'skipped' => $skipped,
            'results' => $results,
        ]);
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

    /**
     * Missing ads: listing is not in any campaign, has a SKU match, a price, and inv > 0.
     */
    protected function cbidNullInStockCount(): int
    {
        return $this->missingAdsCountFor('ebay3_campaign_ads', 'ebay_3_metrics');
    }

    public function getData(Request $request)
    {
        EbayCampaignEndedListingRemap::remapEndedRows('ebay3_campaign_ads', Ebay3Metric::class);

        // Local campaign_status goes stale between daily syncs (eBay can SYSTEM_PAUSE
        // mid-day). Refresh from Marketing API so the Status column matches eBay.
        $this->refreshCampaignStatusesFromEbay();

        $query = DB::table('ebay3_campaign_ads as ca')
            ->leftJoin('ebay_3_metrics as em', 'em.item_id', '=', 'ca.listing_id')
            ->select(
                'ca.*',
                // Use SKU from ebay_3_metrics if matched, fallback to listing_id
                DB::raw("COALESCE(em.sku, ca.sku, ca.listing_id) as resolved_sku"),
                DB::raw("CASE WHEN COALESCE(em.sku, ca.sku) IS NOT NULL AND COALESCE(em.sku, ca.sku) != '' THEN 1 ELSE 0 END as sku_matched"),
                DB::raw("COALESCE(em.ebay_price, ca.price) as metric_price"),
                'em.views',
                'em.l7_views',
                'em.ebay_l30',
                'em.ebay_l60',
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
        CpMasterDil::hydrate($data);

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
            'marketplace' => 'ebay3',
        ];
    }

    /**
     * Pull live campaignStatus from eBay and write it onto local ebay3_campaign_ads rows.
     * Cached briefly so table reloads don't hit Marketing API every time.
     */
    private function refreshCampaignStatusesFromEbay(): void
    {
        $cacheKey = 'ebay3_campaign_ads_status_refreshed';
        if (Cache::has($cacheKey)) {
            return;
        }

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
                if (!$resp->successful()) {
                    break;
                }
                $data  = $resp->json();
                $batch = $data['campaigns'] ?? [];
                $total = (int) ($data['total'] ?? 0);
                $all   = array_merge($all, $batch);
                $offset += $limit;
            } while (count($all) < $total && !empty($batch));

            foreach ($all as $c) {
                $cid    = (string) ($c['campaignId'] ?? '');
                $status = (string) ($c['campaignStatus'] ?? '');
                $name   = $c['campaignName'] ?? null;
                if ($cid === '' || $status === '') {
                    continue;
                }
                $update = ['campaign_status' => $status, 'updated_at' => now()];
                if (is_string($name) && $name !== '') {
                    $update['campaign_name'] = $name;
                }
                DB::table('ebay3_campaign_ads')
                    ->where('campaign_id', $cid)
                    ->where(function ($q) use ($status, $name) {
                        $q->where('campaign_status', '!=', $status)
                          ->orWhereNull('campaign_status');
                        if (is_string($name) && $name !== '') {
                            $q->orWhereNull('campaign_name')
                              ->orWhere('campaign_name', '!=', $name);
                        }
                    })
                    ->update($update);
            }

            Cache::put($cacheKey, true, now()->addMinutes(5));
        } catch (\Throwable $e) {
            Log::warning('ebay3 campaign status refresh failed: '.$e->getMessage());
        }
    }

    private function fetchCampaignStatus(string $token, string $campaignId): ?string
    {
        try {
            $resp = Http::withToken($token)
                ->get("https://api.ebay.com/sell/marketing/v1/ad_campaign/{$campaignId}");
            if (!$resp->successful()) {
                return null;
            }
            $status = $resp->json()['campaignStatus'] ?? null;
            return is_string($status) && $status !== '' ? $status : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
