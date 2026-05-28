<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\FacebookAllAdsSheet;
use App\Models\ShopifyMetaCampaign;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopifyAdsMasterController extends Controller
{
    public function index(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $latestCampaign = ShopifyMetaCampaign::latest('updated_at')->first();

        return view('market-places.shopify_ads_master', [
            'mode' => $mode,
            'demo' => $demo,
            'latestUpdatedAt' => $latestCampaign?->updated_at
                ? $latestCampaign->updated_at->format('d F, Y h:i A')
                : null,
        ]);
    }

    public function data()
    {
        return response()->json([
            'status' => 200,
            'message' => 'Shopify Ads Master data fetched successfully',
            'data' => [
                $this->googleShoppingMetrics(),
                $this->facebookMetrics(),
            ],
        ]);
    }

    private function googleShoppingMetrics(): array
    {
        try {
            $bounds = $this->googleShoppingDateBoundaries();

            $campaigns = DB::table('google_ads_campaigns')
                ->whereNotNull('campaign_id')
                ->selectRaw('campaign_id')
                ->selectRaw('SUM(metrics_cost_micros) / 1000000 as spend')
                ->selectRaw('SUM(metrics_clicks) as clicks')
                ->selectRaw('SUM(ga4_actual_sold_units) as sold')
                ->selectRaw('CASE WHEN COALESCE(SUM(ga4_actual_revenue), 0) > 0 THEN COALESCE(SUM(ga4_actual_revenue), 0) ELSE COALESCE(SUM(ga4_ad_sales), 0) END as sales')
                ->groupBy('campaign_id');

            if ($bounds !== null) {
                $campaigns->whereNotNull('date')
                    ->whereBetween('date', [$bounds['start'], $bounds['end']]);
            }

            $row = DB::query()
                ->fromSub($campaigns, 'campaigns')
                ->selectRaw('COALESCE(SUM(spend), 0) as spend')
                ->selectRaw('COALESCE(SUM(clicks), 0) as clicks')
                ->selectRaw('COALESCE(SUM(sold), 0) as sold')
                ->selectRaw('COALESCE(SUM(sales), 0) as sales')
                ->first();

            return $this->metricRow('Google Shopping', $row);
        } catch (\Throwable) {
            return $this->metricRow('Google Shopping');
        }
    }

    private function facebookMetrics(): array
    {
        try {
            $latestBatches = $this->facebookLatestBatchPerType();
            if (empty($latestBatches)) {
                return $this->metricRow('Facebook');
            }

            // Determine base type (same priority as getMergedView: campaign > spend > sales).
            // Only campaigns present in the base batch are included in the totals.
            $baseType = null;
            foreach (['campaign', 'spend', 'sales'] as $t) {
                if (isset($latestBatches[$t])) {
                    $baseType = $t;
                    break;
                }
            }
            if ($baseType === null) {
                return $this->metricRow('Facebook');
            }

            // Step 1: collect valid campaign IDs + name→CID fallback from base batch.
            [$baseCids, $nameToCid] = $this->facebookBuildBaseCids($latestBatches[$baseType]);

            $spend  = 0.0;
            $clicks = 0.0;
            $sold   = 0.0;
            $sales  = 0.0;

            // Step 2: SPEND + CLK from the spend batch (filter to baseCids).
            if (isset($latestBatches['spend'])) {
                $rows = FacebookAllAdsSheet::query()
                    ->where('import_batch_id', $latestBatches['spend'])
                    ->get(['row_data']);

                foreach ($rows as $row) {
                    $rd      = array_filter((array) ($row->row_data ?? []), fn ($_, $k) => ! str_starts_with($k, '__'), ARRAY_FILTER_USE_BOTH);
                    $cid     = $this->facebookFindCampaignId($rd) ?? $this->facebookNameLookup($rd, $nameToCid);
                    if ($cid === null || $cid === '' || ! isset($baseCids[$cid])) {
                        continue;
                    }
                    // round() per-campaign mirrors applyFormatter('usd_int') in getMergedView,
                    // so our total matches the badge which sums already-rounded integers.
                    $spend  += round($this->parseMetricValue($rd['Amount spent (USD)'] ?? null));
                    $clicks += $this->parseMetricValue($rd['Clicks (all)'] ?? null);
                }
            }

            // Step 3: SOLD + SALES from the sales batch (filter to baseCids).
            if (isset($latestBatches['sales'])) {
                $rows = FacebookAllAdsSheet::query()
                    ->where('import_batch_id', $latestBatches['sales'])
                    ->get(['row_data']);

                foreach ($rows as $row) {
                    $rd  = array_filter((array) ($row->row_data ?? []), fn ($_, $k) => ! str_starts_with($k, '__'), ARRAY_FILTER_USE_BOTH);
                    $cid = $this->facebookFindCampaignId($rd) ?? $this->facebookNameLookup($rd, $nameToCid);
                    if ($cid === null || $cid === '' || ! isset($baseCids[$cid])) {
                        continue;
                    }
                    $sold  += $this->parseMetricValue($rd['Orders'] ?? null);
                    // round() per-campaign mirrors applyFormatter('int') in getMergedView.
                    $sales += round($this->parseMetricValue($rd['Sales'] ?? null));
                }
            }

            return $this->metricRow('Facebook', (object) compact('spend', 'clicks', 'sold', 'sales'));
        } catch (\Throwable) {
            return $this->metricRow('Facebook');
        }
    }

    /**
     * Load all rows from the base batch, return:
     *   [0] array<string, true>  $baseCids   — campaign IDs present in the base batch
     *   [1] array<string, string> $nameToCid  — lowercase campaign name → campaign ID
     *
     * Mirrors buildNameToCidLookup() + the CID-collection loop in getMergedView().
     *
     * @return array{array<string,true>, array<string,string>}
     */
    private function facebookBuildBaseCids(string $baseBatchId): array
    {
        $rows = FacebookAllAdsSheet::query()
            ->where('import_batch_id', $baseBatchId)
            ->get(['row_data']);

        $baseCids  = [];
        $nameToCid = [];

        foreach ($rows as $r) {
            $rd      = array_filter((array) ($r->row_data ?? []), fn ($_, $k) => ! str_starts_with($k, '__'), ARRAY_FILTER_USE_BOTH);
            $cid     = $this->facebookFindCampaignId($rd);
            if ($cid === null || $cid === '') {
                continue;
            }
            $baseCids[$cid] = true;
            $name = $rd['Campaign name'] ?? null;
            if (is_string($name) && trim($name) !== '') {
                $key = mb_strtolower(trim($name));
                if (! isset($nameToCid[$key])) {
                    $nameToCid[$key] = $cid;
                }
            }
        }

        return [$baseCids, $nameToCid];
    }

    /**
     * Mirrors FacebookAllAdsSheetController::findCampaignId().
     * Looks for Campaign ID / Campaign ID / campaign_id / Campaign activities column.
     */
    private function facebookFindCampaignId(array $rowData): ?string
    {
        foreach ($rowData as $key => $value) {
            $k = trim((string) $key);
            if (preg_match('/^campaign[\s_]?id$/i', $k)
                || preg_match('/^campaign\s+activities$/i', $k)) {
                $clean = trim((string) $value);
                if ($clean === '') {
                    continue;
                }
                $low = mb_strtolower($clean);
                if ($low === '(no name)' || $low === '{{campaign_name}}') {
                    continue;
                }

                return $clean;
            }
        }

        return null;
    }

    /**
     * Fallback: resolve CID via campaign name when the row has no Campaign ID column.
     * Mirrors the name-lookup block inside getMergedView().
     *
     * @param  array<string,string>  $nameToCid
     */
    private function facebookNameLookup(array $rowData, array $nameToCid): ?string
    {
        if (empty($nameToCid)) {
            return null;
        }
        $name = $rowData['Campaign name'] ?? null;
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        return $nameToCid[mb_strtolower(trim($name))] ?? null;
    }

    /**
     * Mirrors FacebookAllAdsSheetController::latestBatchPerType() exactly,
     * including the legacy-batch fallback that sniffs type from headers.
     *
     * @return array<string, string>
     */
    private function facebookLatestBatchPerType(): array
    {
        $batches = FacebookAllAdsSheet::query()
            ->select('import_batch_id', DB::raw('MIN(id) as first_id'))
            ->groupBy('import_batch_id')
            ->orderByDesc(DB::raw('MIN(id)'))
            ->limit(50)
            ->get();

        if ($batches->isEmpty()) {
            return [];
        }

        $firstIds  = $batches->pluck('first_id')->all();
        $firstRows = FacebookAllAdsSheet::whereIn('id', $firstIds)->pluck('row_data', 'id');

        $allowed = ['campaign', 'spend', 'sales'];
        $result  = [];

        foreach ($batches as $b) {
            $rd = $firstRows[$b->first_id] ?? null;
            if (! is_array($rd)) {
                continue;
            }

            $type = $rd['__upload_type'] ?? null;

            // Legacy batches uploaded before __upload_type was tagged:
            // sniff the format from the header keys (mirrors detectFormat()).
            if (! $type) {
                $headers = array_keys(array_filter(
                    $rd,
                    fn ($_, $k) => ! str_starts_with($k, '__'),
                    ARRAY_FILTER_USE_BOTH
                ));
                $type = $this->detectFacebookFormat($headers);
            }

            if ($type && in_array($type, $allowed, true) && ! isset($result[$type])) {
                $result[$type] = $b->import_batch_id;
            }
        }

        return $result;
    }

    /**
     * Mirrors FacebookAllAdsSheetController::detectFormat().
     * Infers the upload type from the column headers of a legacy batch.
     */
    private function detectFacebookFormat(array $headers): ?string
    {
        $joined = mb_strtolower(implode('|', $headers));

        if (mb_strpos($joined, 'campaign activities') !== false
            && mb_strpos($joined, 'sessions') !== false) {
            return 'sales';
        }
        if (mb_strpos($joined, 'amount spent') !== false
            || mb_strpos($joined, 'impressions') !== false
            || preg_match('/(^|\|)spend(\||$)/', $joined)) {
            return 'spend';
        }
        if (mb_strpos($joined, 'campaign id') !== false
            || mb_strpos($joined, 'campaign_id') !== false) {
            return 'campaign';
        }

        return null;
    }

    private function googleShoppingDateBoundaries(): ?array
    {
        $maxDate = DB::table('google_ads_campaigns')->whereNotNull('date')->max('date');
        if ($maxDate === null || $maxDate === '') {
            return null;
        }

        $end = Carbon::parse($maxDate)->startOfDay();
        $start = $end->copy()->subDays(29);

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ];
    }

    private function metricRow(string $channel, ?object $row = null): array
    {
        $spend = (float) ($row->spend ?? 0);
        $clicks = (float) ($row->clicks ?? 0);
        $sold = (float) ($row->sold ?? 0);
        $sales = (float) ($row->sales ?? 0);

        return [
            'channel' => $channel,
            'spend' => round($spend, 2),
            'clicks' => (int) round($clicks),
            'sold' => (int) round($sold),
            'sales' => round($sales, 2),
            'cvr' => $clicks > 0 ? round(($sold / $clicks) * 100, 1) : 0,
            // mirrors acosPct(): spend>0 & sales==0 → 100, both 0 → 0
            'acos' => $sales > 0
                ? round(($spend / $sales) * 100, 0)
                : ($spend > 0 ? 100 : 0),
        ];
    }

    private function parseMetricValue(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $number = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($number) ? (float) $number : 0;
    }
}
