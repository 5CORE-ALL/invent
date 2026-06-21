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
    /**
     * Per-request memoization for the Meta sheet aggregations. Computed once by
     * {@see loadFacebookContext()} on the first {@see metaChannelMetrics()} call
     * and reused so adding 8 typed sub-row variants does not multiply DB I/O.
     *
     * @var array{
     *     baseCids: array<string,bool>,
     *     nameToCid: array<string,string>,
     *     chMap: array<string,string>,
     *     adTypeMap: array<string,string>,
     *     spendByCid: array<string, array{spend: float, clicks: float}>,
     *     salesByCid: array<string, array{sold: float, sales: float}>
     * }|null
     */
    private ?array $cachedFbContext = null;

    /**
     * Channel-name delimiter used to mark "sub-row" channels (e.g.
     * "Facebook · G Video"). Detected by both {@see history()} and the
     * frontend so sub-rows don't double-count rolled-up totals.
     */
    public const SUBROW_SEPARATOR = ' · ';

    /**
     * Rolled-up Spend + TCOS for the four parent channels the /shopify-ads-master
     * page treats as "all channels" (Google Shopping, Google SERP, Facebook,
     * Instagram — sub-rows excluded so Facebook · G Video etc. don't double count).
     * Used by the Shopify row on /all-marketplace-master so its "Total Ad Spend"
     * and "TACOS %" agree with the badges that page shows.
     *
     * Side-effect-free (no snapshot writes) — safe to call multiple times.
     *
     * @return array{
     *     total_spend: float,
     *     net_sales: float,
     *     tcos_pct: float,
     *     breakdown: array<string, float>
     * }
     */
    public function getRolledUpSpend(): array
    {
        // Same set updateBadges() in the blade sums (parents only). loadFacebookContext()
        // gracefully returns no-op rows when Meta data is absent, so this works even
        // without an active Meta sheet.
        try {
            $rows = [
                $this->googleShoppingMetrics(),
                $this->googleSerpMetrics(),
                $this->metaChannelMetrics('Facebook', 'FB'),
                $this->metaChannelMetrics('Instagram', 'Insta'),
            ];
        } catch (\Throwable $e) {
            \Log::warning('ShopifyAdsMaster::getRolledUpSpend rows failed: ' . $e->getMessage());
            $rows = [];
        }

        $totalSpend = 0.0;
        $breakdown  = [];
        foreach ($rows as $r) {
            // metricRow() keys the channel name as 'channel' — not 'label'.
            $label = (string) ($r['channel'] ?? 'unknown');
            $spend = (float)  ($r['spend']   ?? 0);
            $totalSpend += $spend;
            $breakdown[$label] = round($spend, 2);
        }

        $netSales = 0.0;
        try {
            $netSales = $this->shopifyNetSales();
        } catch (\Throwable $e) {
            \Log::warning('ShopifyAdsMaster::getRolledUpSpend netSales failed: ' . $e->getMessage());
        }

        $tcos = $netSales > 0
            ? round(($totalSpend / $netSales) * 100, 2)
            : ($totalSpend > 0 ? 100.0 : 0.0);

        return [
            'total_spend' => round($totalSpend, 2),
            'net_sales'   => round($netSales, 2),
            'tcos_pct'    => $tcos,
            'breakdown'   => $breakdown,
        ];
    }

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
        // Google Shopping ↔ Google SERP partition the same `google_ads_campaigns` rows by
        // campaign-name word boundary on " SEARCH" (matches the /google/shopping/google-shopping
        // and /google/shopping/google-serp pages). Listing both as channels is symmetric to what
        // those pages show, with no double-counting between them.
        //
        // Facebook + Instagram each get four typed sub-rows (G Video / G Carousal / P Video /
        // P Carousal) mirroring the new `/facebook-ads/{type}` and `/instagram-ads/{type}`
        // child pages. Sub-rows carry `is_sub_row=true` so the rolled-up badges and the
        // history endpoint skip them — they're slices of the parent, not new channels.
        $sep = self::SUBROW_SEPARATOR;
        $rows = [
            $this->googleShoppingMetrics(),
            $this->googleSerpMetrics(),
            $this->metaChannelMetrics('Facebook', 'FB'),
            $this->metaChannelMetrics('Facebook'.$sep.'G Video',     'FB',    ['GROUP VIDEO'],     true),
            $this->metaChannelMetrics('Facebook'.$sep.'G Carousal',  'FB',    ['GROUP CAROUSAL'],  true),
            $this->metaChannelMetrics('Facebook'.$sep.'P Video',     'FB',    ['PARENT VIDEO'],    true),
            $this->metaChannelMetrics('Facebook'.$sep.'P Carousal',  'FB',    ['PARENT CAROUSAL'], true),
            $this->metaChannelMetrics('Instagram', 'Insta'),
            $this->metaChannelMetrics('Instagram'.$sep.'G Video',    'Insta', ['GROUP VIDEO'],     true),
            $this->metaChannelMetrics('Instagram'.$sep.'G Carousal', 'Insta', ['GROUP CAROUSAL'],  true),
            $this->metaChannelMetrics('Instagram'.$sep.'P Video',    'Insta', ['PARENT VIDEO'],    true),
            $this->metaChannelMetrics('Instagram'.$sep.'P Carousal', 'Insta', ['PARENT CAROUSAL'], true),
        ];

        $netSales = $this->shopifyNetSales();

        // TCOS = channel Spend / S Sales (store net sales), as a %. Computed
        // here because it needs the store-level net-sales figure.
        foreach ($rows as &$row) {
            $spend = (float) ($row['spend'] ?? 0);
            $row['tcos'] = $netSales > 0
                ? round(($spend / $netSales) * 100, 0)
                : ($spend > 0 ? 100 : 0);
        }
        unset($row);

        // Persist today's snapshot so the badge trend chart has history.
        // Never let a snapshot write break the data feed.
        try {
            $this->snapshotChannels($rows, $netSales);
        } catch (\Throwable $e) {
            \Log::warning('Shopify Ads Master snapshot failed: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 200,
            'message' => 'Shopify Ads Master data fetched successfully',
            'data' => $rows,
            // Net Sales (gross − discounts) for the last 30 days from the
            // /shopify page, surfaced as the "S Sales" badge.
            'shopify_net_sales' => $netSales,
        ]);
    }

    /** Pseudo-channel key used to store the store-level S Sales snapshot. */
    private const SSALES_CHANNEL = '__ssales__';

    /**
     * Marketplace sources/tags excluded from the /shopify Net Sales figure.
     * Mirrors ShopifyRawDataController::EXCLUDE_SOURCES so the badge matches
     * what that page shows.
     */
    private const SHOPIFY_EXCLUDE_SOURCES = [
        'amazon', 'shein', 'ebay', 'tiktok', 'temu',
        '179763773441', "macy's, inc.", "macy's", 'macys',
        'purchasing power', 'purchasingpower', 'reverb',
        'faire', 'best buy', 'bestbuy', 'best buy usa',
        'doba', '145019994113',
        'newegg', '189863297025',
        'depop', 'tiendamia',
    ];

    /**
     * Total Net Sales (gross − discounts) over the last 30 days (PST),
     * mirroring the /shopify page's Net Sales card: shopify_raw_orders with
     * the marketplace exclusions and the "XYZ" SKU filter applied.
     */
    private function shopifyNetSales(): float
    {
        try {
            $tz       = 'America/Los_Angeles';
            $dateFrom = Carbon::now($tz)->subDays(30)->startOfDay()->toDateString();
            $dateTo   = Carbon::now($tz)->endOfDay()->toDateString();

            $q = DB::table('shopify_raw_orders')
                ->where('order_date', '>=', $dateFrom)
                ->where('order_date', '<=', $dateTo);

            foreach (self::SHOPIFY_EXCLUDE_SOURCES as $term) {
                $t = strtolower($term);
                $q->whereRaw('LOWER(COALESCE(source_name,"")) NOT LIKE ?', ['%' . $t . '%'])
                  ->whereRaw('LOWER(COALESCE(tags,"")) NOT LIKE ?',        ['%' . $t . '%']);
            }
            $q->where('sku', 'NOT LIKE', '%XYZ%');

            return round((float) $q->sum('net_sales'), 2);
        } catch (\Throwable $e) {
            \Log::warning('Shopify net sales lookup failed: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Upsert one snapshot row per channel for today. Keyed on
     * (snapshot_date, channel) so repeated page loads simply refresh the
     * day's value rather than piling up duplicates.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function snapshotChannels(array $rows, float $netSales = 0.0): void
    {
        $today = Carbon::today()->toDateString();
        $now   = now();

        foreach ($rows as $row) {
            $channel = (string) ($row['channel'] ?? '');
            if ($channel === '') {
                continue;
            }
            DB::table('shopify_ads_master_metric_snapshots')->updateOrInsert(
                ['snapshot_date' => $today, 'channel' => $channel],
                [
                    'spend'      => (float) ($row['spend'] ?? 0),
                    'clicks'     => (float) ($row['clicks'] ?? 0),
                    'sold'       => (float) ($row['sold'] ?? 0),
                    'sales'      => (float) ($row['sales'] ?? 0),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        // Store-level S Sales kept as its own pseudo-channel row (the
        // net-sales figure lives in the `sales` column). Excluded from the
        // channel totals in history().
        DB::table('shopify_ads_master_metric_snapshots')->updateOrInsert(
            ['snapshot_date' => $today, 'channel' => self::SSALES_CHANNEL],
            ['sales' => $netSales, 'updated_at' => $now, 'created_at' => $now]
        );
    }

    /**
     * Badge trend history. Returns a per-day time-series for every badge
     * (spend / clicks / sold / sales / cvr / acos), aggregated across all
     * channels, plus the same broken out per channel so the chart can show
     * either the rolled-up total or a single channel.
     *
     *   GET /shopify-ads-master/history?days=32
     */
    public function history(Request $request)
    {
        $days = max(1, min(365, (int) $request->query('days', 32)));
        $from = Carbon::today()->subDays($days - 1)->toDateString();

        $rows = DB::table('shopify_ads_master_metric_snapshots')
            ->where('snapshot_date', '>=', $from)
            ->orderBy('snapshot_date')
            ->get(['snapshot_date', 'channel', 'spend', 'clicks', 'sold', 'sales']);

        // Group the raw measures by date (totals) and by date+channel.
        // The store-level S Sales pseudo-channel is kept aside so it never
        // inflates the channel totals.
        $byDate     = [];   // date => [spend, clicks, sold, sales]
        $byChannel  = [];   // channel => date => [...]
        $ssalesByDate = []; // date => net sales
        $allDates   = [];   // every date seen, incl. ssales-only days
        foreach ($rows as $r) {
            $d  = (string) $r->snapshot_date;
            $ch = (string) $r->channel;
            $allDates[$d] = true;

            if ($ch === self::SSALES_CHANNEL) {
                $ssalesByDate[$d] = (float) $r->sales;
                continue;
            }

            // Sub-row channels (e.g. "Facebook · G Video") are slices of their
            // parent (Facebook) — keep their own per-channel series so the
            // trend modal can lens to them, but skip from the rolled-up byDate
            // total so the parent isn't double-counted.
            $isSubRow = str_contains($ch, self::SUBROW_SEPARATOR);

            $byChannel[$ch][$d] = [
                'spend'  => (float) $r->spend,
                'clicks' => (float) $r->clicks,
                'sold'   => (float) $r->sold,
                'sales'  => (float) $r->sales,
            ];

            if ($isSubRow) {
                continue;
            }

            $byDate[$d] ??= ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0];
            $byDate[$d]['spend']  += (float) $r->spend;
            $byDate[$d]['clicks'] += (float) $r->clicks;
            $byDate[$d]['sold']   += (float) $r->sold;
            $byDate[$d]['sales']  += (float) $r->sales;
        }

        // Use the union of all dates so an ssales-only day still shows.
        $labels = array_keys($allDates);
        sort($labels);
        foreach ($labels as $d) {
            $byDate[$d] ??= ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0];
        }

        $metrics = $this->buildMetricSeries($byDate, $labels, $ssalesByDate);
        $metrics['ssales'] = array_map(fn ($d) => round($ssalesByDate[$d] ?? 0, 2), $labels);

        return response()->json([
            'status'   => 200,
            'days'     => $days,
            'labels'   => array_map(fn ($d) => date('M d', strtotime($d)), $labels),
            'metrics'  => $metrics,
            'channels' => $this->buildChannelSeries($byChannel, $labels, $ssalesByDate),
        ]);
    }

    /**
     * Turn the per-day raw measures into the 6 badge series (with CVR /
     * ACOS derived exactly like the badges / table do).
     *
     * @param  array<string, array<string, float>>  $byDate
     * @param  array<int, string>  $labels
     * @param  array<string, float>  $ssalesByDate  date => store net sales (for TCOS)
     * @return array<string, array<int, float>>
     */
    private function buildMetricSeries(array $byDate, array $labels, array $ssalesByDate = []): array
    {
        $series = ['spend' => [], 'clicks' => [], 'sold' => [], 'sales' => [], 'cvr' => [], 'acos' => [], 'tcos' => []];
        foreach ($labels as $d) {
            $m = $byDate[$d];
            $series['spend'][]  = round($m['spend'], 2);
            $series['clicks'][] = (int) round($m['clicks']);
            $series['sold'][]   = (int) round($m['sold']);
            $series['sales'][]  = round($m['sales'], 2);
            $series['cvr'][]    = $m['clicks'] > 0 ? round(($m['sold'] / $m['clicks']) * 100, 1) : 0;
            $series['acos'][]   = $m['sales'] > 0
                ? round(($m['spend'] / $m['sales']) * 100, 0)
                : ($m['spend'] > 0 ? 100 : 0);
            // TCOS = Spend / S Sales (store net sales).
            $ss = $ssalesByDate[$d] ?? 0;
            $series['tcos'][]   = $ss > 0
                ? round(($m['spend'] / $ss) * 100, 0)
                : ($m['spend'] > 0 ? 100 : 0);
        }
        return $series;
    }

    /**
     * Per-channel badge series, aligned to the same date labels (missing
     * days fill with 0 so every line spans the full range).
     *
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     * @param  array<int, string>  $labels
     * @param  array<string, float>  $ssalesByDate
     * @return array<string, array<string, array<int, float>>>
     */
    private function buildChannelSeries(array $byChannel, array $labels, array $ssalesByDate = []): array
    {
        $out = [];
        foreach ($byChannel as $channel => $perDay) {
            $byDate = [];
            foreach ($labels as $d) {
                $byDate[$d] = $perDay[$d] ?? ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0];
            }
            $out[$channel] = $this->buildMetricSeries($byDate, $labels, $ssalesByDate);
        }
        return $out;
    }

    private function googleShoppingMetrics(): array
    {
        return $this->googleAdsChannelMetrics('Google Shopping', 'shopping');
    }

    /**
     * Google SERP totals (campaigns whose name contains the word "SEARCH" — same scope as
     * /google/shopping/google-serp). Leading-space matcher avoids false-positives like
     * "RESEARCH". Complementary to {@see googleShoppingMetrics()} so the two channels
     * partition `google_ads_campaigns` rows without overlap.
     */
    private function googleSerpMetrics(): array
    {
        return $this->googleAdsChannelMetrics('Google SERP', 'serp');
    }

    /**
     * Shared L30 totals query for the two Google Ads sub-channels. `$scope = 'shopping'`
     * excludes SEARCH-named campaigns; `$scope = 'serp'` includes only SEARCH-named
     * campaigns. Anything else returns the unfiltered total (back-compat path).
     */
    private function googleAdsChannelMetrics(string $label, string $scope): array
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

            if ($scope === 'shopping') {
                $campaigns->whereRaw('UPPER(campaign_name) NOT LIKE ?', ['% SEARCH%']);
            } elseif ($scope === 'serp') {
                $campaigns->whereRaw('UPPER(campaign_name) LIKE ?', ['% SEARCH%']);
            }

            $row = DB::query()
                ->fromSub($campaigns, 'campaigns')
                ->selectRaw('COALESCE(SUM(spend), 0) as spend')
                ->selectRaw('COALESCE(SUM(clicks), 0) as clicks')
                ->selectRaw('COALESCE(SUM(sold), 0) as sold')
                ->selectRaw('COALESCE(SUM(sales), 0) as sales')
                ->first();

            return $this->metricRow($label, $row);
        } catch (\Throwable) {
            return $this->metricRow($label);
        }
    }

    /**
     * Totals for one Meta channel lens (CH = FB → /facebook-ads,
     * CH = Insta → /instagram-ads). Mirrors the merged view but lensed to
     * the campaigns tagged with $chCode, so each row matches its page.
     *
     * When `$adTypeList` is non-null, additionally lenses to campaigns whose
     * `ad_type` is in the list — used to back the typed sub-rows
     * (e.g. "Facebook · G Video" = CH=FB ∧ ad_type='GROUP VIDEO').
     *
     * `$isSubRow=true` flags the row so the frontend can skip it in the
     * rolled-up "All channels" badges (sub-rows are subsets of their parent).
     *
     * @param  list<string>|null  $adTypeList
     */
    private function metaChannelMetrics(string $label, string $chCode, ?array $adTypeList = null, bool $isSubRow = false): array
    {
        try {
            $ctx = $this->loadFacebookContext();
            if (empty($ctx['baseCids'])) {
                return $this->metricRow($label, null, $isSubRow);
            }

            $spend  = 0.0;
            $clicks = 0.0;
            $sold   = 0.0;
            $sales  = 0.0;

            foreach ($ctx['baseCids'] as $cid => $_) {
                if (($ctx['chMap'][$cid] ?? null) !== $chCode) {
                    continue;
                }
                if ($adTypeList !== null) {
                    $at = $ctx['adTypeMap'][$cid] ?? null;
                    if ($at === null || ! in_array($at, $adTypeList, true)) {
                        continue;
                    }
                }
                if (isset($ctx['spendByCid'][$cid])) {
                    $spend  += $ctx['spendByCid'][$cid]['spend'];
                    $clicks += $ctx['spendByCid'][$cid]['clicks'];
                }
                if (isset($ctx['salesByCid'][$cid])) {
                    $sold  += $ctx['salesByCid'][$cid]['sold'];
                    $sales += $ctx['salesByCid'][$cid]['sales'];
                }
            }

            return $this->metricRow($label, (object) compact('spend', 'clicks', 'sold', 'sales'), $isSubRow);
        } catch (\Throwable) {
            return $this->metricRow($label, null, $isSubRow);
        }
    }

    /**
     * Build all per-CID lookups for the latest Meta sheet in one shot, so the
     * 10 metaChannelMetrics() calls (2 parents + 8 typed sub-rows) reuse a
     * single read of the spend/sales batches.
     *
     * @return array{
     *     baseCids: array<string,bool>,
     *     nameToCid: array<string,string>,
     *     chMap: array<string,string>,
     *     adTypeMap: array<string,string>,
     *     spendByCid: array<string, array{spend: float, clicks: float}>,
     *     salesByCid: array<string, array{sold: float, sales: float}>
     * }
     */
    private function loadFacebookContext(): array
    {
        if ($this->cachedFbContext !== null) {
            return $this->cachedFbContext;
        }

        $empty = [
            'baseCids'   => [],
            'nameToCid'  => [],
            'chMap'      => [],
            'adTypeMap'  => [],
            'spendByCid' => [],
            'salesByCid' => [],
        ];

        $latestBatches = $this->facebookLatestBatchPerType();
        if (empty($latestBatches)) {
            return $this->cachedFbContext = $empty;
        }

        // Same base-type priority as getMergedView: campaign > spend > sales.
        $baseType = null;
        foreach (['campaign', 'spend', 'sales'] as $t) {
            if (isset($latestBatches[$t])) {
                $baseType = $t;
                break;
            }
        }
        if ($baseType === null) {
            return $this->cachedFbContext = $empty;
        }

        [$baseCids, $nameToCid] = $this->facebookBuildBaseCids($latestBatches[$baseType]);

        $spendByCid = [];
        if (isset($latestBatches['spend'])) {
            $rows = FacebookAllAdsSheet::query()
                ->where('import_batch_id', $latestBatches['spend'])
                ->get(['row_data']);

            foreach ($rows as $row) {
                $rd  = array_filter((array) ($row->row_data ?? []), fn ($_, $k) => ! str_starts_with($k, '__'), ARRAY_FILTER_USE_BOTH);
                $cid = $this->facebookFindCampaignId($rd) ?? $this->facebookNameLookup($rd, $nameToCid);
                if ($cid === null || $cid === '' || ! isset($baseCids[$cid])) {
                    continue;
                }
                $spendByCid[$cid] ??= ['spend' => 0.0, 'clicks' => 0.0];
                // round() per-campaign mirrors applyFormatter('usd_int') in getMergedView.
                $spendByCid[$cid]['spend']  += round($this->parseMetricValue($rd['Amount spent (USD)'] ?? null));
                $spendByCid[$cid]['clicks'] += $this->parseMetricValue($rd['Clicks (all)'] ?? null);
            }
        }

        $salesByCid = [];
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
                $salesByCid[$cid] ??= ['sold' => 0.0, 'sales' => 0.0];
                $salesByCid[$cid]['sold']  += $this->parseMetricValue($rd['Orders'] ?? null);
                // round() per-campaign mirrors applyFormatter('int') in getMergedView.
                $salesByCid[$cid]['sales'] += round($this->parseMetricValue($rd['Sales'] ?? null));
            }
        }

        return $this->cachedFbContext = [
            'baseCids'   => $baseCids,
            'nameToCid'  => $nameToCid,
            'chMap'      => $this->facebookChMap(),
            'adTypeMap'  => $this->facebookAdTypeMap(),
            'spendByCid' => $spendByCid,
            'salesByCid' => $salesByCid,
        ];
    }

    /**
     * Build a `Campaign ID → ad_type` map (latest tag wins per CID). Mirrors
     * {@see facebookChMap()} so typed sub-rows can be lensed without re-reading
     * the sheet for every (channel, ad_type) combination.
     *
     * @return array<string, string>
     */
    private function facebookAdTypeMap(): array
    {
        $rows = FacebookAllAdsSheet::query()
            ->whereNotNull('ad_type')
            ->where('ad_type', '!=', '')
            ->orderByDesc('id')
            ->get(['ad_type', 'row_data']);

        $map = [];
        foreach ($rows as $r) {
            $rd  = array_filter(
                (array) ($r->row_data ?? []),
                fn ($_, $k) => ! str_starts_with($k, '__'),
                ARRAY_FILTER_USE_BOTH
            );
            $cid = $this->facebookFindCampaignId($rd);
            if ($cid !== null && $cid !== '' && ! isset($map[$cid])) {
                $map[$cid] = $r->ad_type;
            }
        }

        return $map;
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
     * Build a `Campaign ID → ch` map (latest CH tag wins per campaign).
     * Mirrors FacebookAllAdsSheetController::buildChCarryMap() so the
     * Facebook row here can be lensed to the /facebook-ads channel (CH = FB).
     *
     * @return array<string, string>
     */
    private function facebookChMap(): array
    {
        $rows = FacebookAllAdsSheet::query()
            ->whereNotNull('ch')
            ->where('ch', '!=', '')
            ->orderByDesc('id')
            ->get(['ch', 'row_data']);

        $map = [];
        foreach ($rows as $r) {
            $rd  = array_filter(
                (array) ($r->row_data ?? []),
                fn ($_, $k) => ! str_starts_with($k, '__'),
                ARRAY_FILTER_USE_BOTH
            );
            $cid = $this->facebookFindCampaignId($rd);
            if ($cid !== null && $cid !== '' && ! isset($map[$cid])) {
                $map[$cid] = $r->ch;
            }
        }

        return $map;
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

    private function metricRow(string $channel, ?object $row = null, bool $isSubRow = false): array
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
            // tcos (Spend / S Sales) is filled in by data() once the
            // store-level net-sales figure is known.
            'tcos' => 0,
            // Sub-rows are typed slices of a parent channel (e.g.
            // "Facebook · G Video" is a subset of "Facebook"). Frontend
            // skips them when summing the rolled-up "All channels" badges.
            'is_sub_row' => $isSubRow,
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
