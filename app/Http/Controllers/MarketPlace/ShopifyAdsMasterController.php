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
     * Timezone the history snapshots are stamped in. Pacific
     * (America/Los_Angeles) auto-switches between PST and PDT, so "today"
     * always means the current Pacific business day — matching the other
     * Pacific-based sales windows across the app.
     */
    public const SNAPSHOT_TIMEZONE = 'America/Los_Angeles';

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

    /**
     * Rolled-up parent channels for Advertisement Master (sub-rows excluded so
     * Facebook · G Video etc. do not double-count the Shopify parent total).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdvertisementMasterChannelRows(): array
    {
        $sep = self::SUBROW_SEPARATOR;
        $parentMetrics = ['spend' => 0.0, 'clicks' => 0, 'sold' => 0, 'sales' => 0.0, 'active' => 0];
        $children = [];

        $flatChildSources = [
            ['Google Shopping', 'shopify_google_shopping', $this->googleShoppingMetrics()],
            ['Google SERP', 'shopify_google_serp', $this->googleSerpMetrics()],
            ['Youtube ads', 'shopify_youtube_ads', $this->googleYoutubeAdsMetrics()],
        ];

        foreach ($flatChildSources as [$label, $source, $row]) {
            $parentMetrics['spend'] += (float) ($row['spend'] ?? 0);
            $parentMetrics['clicks'] += (int) ($row['clicks'] ?? 0);
            $parentMetrics['sold'] += (int) ($row['sold'] ?? 0);
            $parentMetrics['sales'] += (float) ($row['sales'] ?? 0);
            $parentMetrics['active'] += (int) ($row['active'] ?? 0);

            $children[] = self::advertisementMasterMetricRow(
                'Shopify'.$sep.$label,
                $source,
                (object) $row,
                true
            );
        }

        $facebookMetrics = $this->metaChannelMetrics('Facebook', 'FB');
        $parentMetrics['spend'] += (float) ($facebookMetrics['spend'] ?? 0);
        $parentMetrics['clicks'] += (int) ($facebookMetrics['clicks'] ?? 0);
        $parentMetrics['sold'] += (int) ($facebookMetrics['sold'] ?? 0);
        $parentMetrics['sales'] += (float) ($facebookMetrics['sales'] ?? 0);
        $parentMetrics['active'] += (int) ($facebookMetrics['active'] ?? 0);

        $facebookRow = self::advertisementMasterMetricRow(
            'Shopify'.$sep.'Facebook',
            'shopify_facebook',
            (object) $facebookMetrics,
            true
        );

        $facebookSubTypes = [
            ['G Video', 'shopify_facebook_g_video', ['GROUP VIDEO']],
            ['G Carousal', 'shopify_facebook_g_carousal', ['GROUP CAROUSAL']],
            ['P Video', 'shopify_facebook_p_video', ['PARENT VIDEO']],
            ['P Carousal', 'shopify_facebook_p_carousal', ['PARENT CAROUSAL']],
        ];

        $facebookChildren = [];
        foreach ($facebookSubTypes as [$suffix, $source, $adTypes]) {
            $subMetrics = $this->metaChannelMetrics('Facebook'.$sep.$suffix, 'FB', $adTypes, true);
            $facebookChildren[] = self::advertisementMasterMetricRow(
                'Shopify'.$sep.'Facebook'.$sep.$suffix,
                $source,
                (object) $subMetrics,
                true
            );
        }
        $facebookRow['_children'] = $facebookChildren;
        $children[] = $facebookRow;

        $instagramMetrics = $this->metaChannelMetrics('Instagram', 'Insta');
        $parentMetrics['spend'] += (float) ($instagramMetrics['spend'] ?? 0);
        $parentMetrics['clicks'] += (int) ($instagramMetrics['clicks'] ?? 0);
        $parentMetrics['sold'] += (int) ($instagramMetrics['sold'] ?? 0);
        $parentMetrics['sales'] += (float) ($instagramMetrics['sales'] ?? 0);
        $parentMetrics['active'] += (int) ($instagramMetrics['active'] ?? 0);

        $instagramRow = self::advertisementMasterMetricRow(
            'Shopify'.$sep.'Instagram',
            'shopify_instagram',
            (object) $instagramMetrics,
            true
        );

        $instagramSubTypes = [
            ['G Video', 'shopify_instagram_g_video', ['GROUP VIDEO']],
            ['G Carousal', 'shopify_instagram_g_carousal', ['GROUP CAROUSAL']],
            ['P Video', 'shopify_instagram_p_video', ['PARENT VIDEO']],
            ['P Carousal', 'shopify_instagram_p_carousal', ['PARENT CAROUSAL']],
        ];

        $instagramChildren = [];
        foreach ($instagramSubTypes as [$suffix, $source, $adTypes]) {
            $subMetrics = $this->metaChannelMetrics('Instagram'.$sep.$suffix, 'Insta', $adTypes, true);
            $instagramChildren[] = self::advertisementMasterMetricRow(
                'Shopify'.$sep.'Instagram'.$sep.$suffix,
                $source,
                (object) $subMetrics,
                true
            );
        }
        $instagramRow['_children'] = $instagramChildren;
        $children[] = $instagramRow;

        $parent = self::advertisementMasterMetricRow('Shopify', 'shopify', (object) $parentMetrics, false);
        $parent['_children'] = $children;

        return [$parent];
    }

    /**
     * Shopify L30 store net sales — same source as the S Sales badge on
     * /shopify-ads-master.
     */
    public static function advertisementMasterNetSales(): float
    {
        try {
            return round((new self())->shopifyNetSales(), 2);
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master Shopify net sales lookup failed: '.$e->getMessage());

            return 0.0;
        }
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
        // Facebook + Instagram are expandable parent rows (Tabulator data tree, same
        // UX as /advertisement-master): each carries four typed sub-rows (G Video /
        // G Carousal / P Video / P Carousal) nested under `_children`, mirroring the
        // `/facebook-ads/{type}` and `/instagram-ads/{type}` child pages. Children keep
        // `is_sub_row=true` so the rolled-up badges and history endpoint skip them —
        // they're slices of the parent, not new channels.
        $sep = self::SUBROW_SEPARATOR;

        $facebook = $this->metaChannelMetrics('Facebook', 'FB');
        $facebook['_children'] = [
            $this->metaChannelMetrics('Facebook'.$sep.'G Video',     'FB',    ['GROUP VIDEO'],     true),
            $this->metaChannelMetrics('Facebook'.$sep.'G Carousal',  'FB',    ['GROUP CAROUSAL'],  true),
            $this->metaChannelMetrics('Facebook'.$sep.'P Video',     'FB',    ['PARENT VIDEO'],    true),
            $this->metaChannelMetrics('Facebook'.$sep.'P Carousal',  'FB',    ['PARENT CAROUSAL'], true),
        ];

        $instagram = $this->metaChannelMetrics('Instagram', 'Insta');
        $instagram['_children'] = [
            $this->metaChannelMetrics('Instagram'.$sep.'G Video',    'Insta', ['GROUP VIDEO'],     true),
            $this->metaChannelMetrics('Instagram'.$sep.'G Carousal', 'Insta', ['GROUP CAROUSAL'],  true),
            $this->metaChannelMetrics('Instagram'.$sep.'P Video',    'Insta', ['PARENT VIDEO'],    true),
            $this->metaChannelMetrics('Instagram'.$sep.'P Carousal', 'Insta', ['PARENT CAROUSAL'], true),
        ];

        $rows = [
            $this->googleShoppingMetrics(),
            $this->googleSerpMetrics(),
            $this->googleYoutubeAdsMetrics(),
            $facebook,
            $instagram,
        ];

        $netSales = $this->shopifyNetSales();

        // TCOS = channel Spend / S Sales (store net sales), as a %. Applied
        // recursively so nested children get it too.
        $this->applyTcosToRows($rows, $netSales);

        // Trend dots: compare each metric against the previous Pacific-day
        // snapshot (per channel) so the table can show a green (improved) /
        // red (declined) dot. Read *before* today's snapshot write below so
        // "previous" never means today. Spend + ACOS are inverted downstream
        // (a higher value is worse → red).
        $pacificToday  = Carbon::now(self::SNAPSHOT_TIMEZONE)->toDateString();
        $prevByChannel = $this->previousSnapshotByChannel($pacificToday);
        $this->attachTrends($rows, $prevByChannel);

        // Persist today's snapshot so the badge trend chart has history. The
        // snapshot table is flat (one row per channel), so flatten the tree
        // first. Never let a snapshot write break the data feed.
        try {
            $this->snapshotChannels($this->flattenRows($rows), $netSales);
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

    /**
     * Apply TCOS (Spend / S Sales) to every row and, recursively, to any
     * nested `_children` so parent and child rows both carry the figure.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function applyTcosToRows(array &$rows, float $netSales): void
    {
        foreach ($rows as &$row) {
            $spend = (float) ($row['spend'] ?? 0);
            $row['tcos'] = $netSales > 0
                ? round(($spend / $netSales) * 100, 0)
                : ($spend > 0 ? 100 : 0);

            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->applyTcosToRows($row['_children'], $netSales);
            }
        }
        unset($row);
    }

    /**
     * Flatten a nested `_children` tree into a single list (parents first,
     * then their children) so snapshotting can persist one row per channel.
     * The `_children` key is stripped from each emitted row.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function flattenRows(array $rows): array
    {
        $flat = [];
        foreach ($rows as $row) {
            $children = $row['_children'] ?? [];
            unset($row['_children']);
            $flat[] = $row;
            if (! empty($children) && is_array($children)) {
                $flat = array_merge($flat, $this->flattenRows($children));
            }
        }

        return $flat;
    }

    /**
     * Most-recent snapshot strictly before $today (per channel), used to work
     * out each metric's day-over-day direction for the trend dots. Rows are
     * read ascending so the latest prior date wins per channel.
     *
     * @return array<string, array{spend: float, clicks: float, sold: float, sales: float}>
     */
    private function previousSnapshotByChannel(string $today): array
    {
        $rows = DB::table('shopify_ads_master_metric_snapshots')
            ->where('snapshot_date', '<', $today)
            ->orderBy('snapshot_date')
            ->get(['channel', 'spend', 'clicks', 'sold', 'sales', 'active']);

        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r->channel] = [
                'spend'  => (float) $r->spend,
                'clicks' => (float) $r->clicks,
                'sold'   => (float) $r->sold,
                'sales'  => (float) $r->sales,
                'active' => (float) ($r->active ?? 0),
            ];
        }

        return $map;
    }

    /**
     * Tag every row (and nested child) with a `trend` map — one direction per
     * metric ('up' | 'down' | 'flat') comparing the current value to the
     * previous Pacific-day snapshot for that channel. Channels with no prior
     * snapshot get an empty map (no dot shown).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, float>>  $prevByChannel
     */
    private function attachTrends(array &$rows, array $prevByChannel): void
    {
        foreach ($rows as &$row) {
            $channel = (string) ($row['channel'] ?? '');
            $row['trend'] = $this->computeTrend($row, $prevByChannel[$channel] ?? null);

            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->attachTrends($row['_children'], $prevByChannel);
            }
        }
        unset($row);
    }

    /**
     * Direction of each displayed metric vs the previous day. CVR / ACOS are
     * re-derived from the previous day's raw measures exactly like the current
     * row so the comparison is apples-to-apples. The colour meaning (which way
     * is "good") is applied on the frontend, where Spend + ACOS are inverted.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, float>|null  $prev
     * @return array<string, string>
     */
    private function computeTrend(array $row, ?array $prev): array
    {
        if ($prev === null) {
            return [];
        }

        $prevSpend  = (float) ($prev['spend'] ?? 0);
        $prevClicks = (float) ($prev['clicks'] ?? 0);
        $prevSold   = (float) ($prev['sold'] ?? 0);
        $prevSales  = (float) ($prev['sales'] ?? 0);
        $prevActive = (float) ($prev['active'] ?? 0);
        $prevCvr    = $prevClicks > 0 ? ($prevSold / $prevClicks) * 100 : 0;
        $prevAcos   = $prevSales > 0
            ? ($prevSpend / $prevSales) * 100
            : ($prevSpend > 0 ? 100 : 0);

        $dir = static fn (float $cur, float $was): string => $cur > $was
            ? 'up'
            : ($cur < $was ? 'down' : 'flat');

        return [
            'spend'  => $dir((float) ($row['spend'] ?? 0),  $prevSpend),
            'clicks' => $dir((float) ($row['clicks'] ?? 0), $prevClicks),
            'sold'   => $dir((float) ($row['sold'] ?? 0),   $prevSold),
            'sales'  => $dir((float) ($row['sales'] ?? 0),  $prevSales),
            'active' => $dir((float) ($row['active'] ?? 0),  $prevActive),
            'cvr'    => $dir((float) ($row['cvr'] ?? 0),     $prevCvr),
            'acos'   => $dir((float) ($row['acos'] ?? 0),    $prevAcos),
        ];
    }

    /** Pseudo-channel key used to store the store-level S Sales snapshot. */
    private const SSALES_CHANNEL = '__ssales__';

    /**
     * Marketplace sources/tags excluded from the /shopify Net Sales figure.
     * References ShopifyRawDataController::EXCLUDE_SOURCES so the badge always
     * matches what the /shopify and /all-marketplace-master pages show — adding
     * a new marketplace there will flow here automatically.
     */
    private function shopifyExcludeSources(): array
    {
        return \App\Http\Controllers\ShopifyRawDataController::EXCLUDE_SOURCES;
    }

    /**
     * Total Net Sales (gross − discounts) over the last 30 days (PST),
     * mirroring the /shopify page's Net Sales card: shopify_raw_orders with
     * the marketplace exclusions and the "XYZ" SKU filter applied.
     */
    private function shopifyNetSales(): float
    {
        try {
            [$dateFrom, $dateTo] = \App\Http\Controllers\ShopifyRawDataController::shopifyDirectL30Range();

            return app(\App\Http\Controllers\ShopifyRawDataController::class)
                ->sumDirectNetSales($dateFrom, $dateTo);
        } catch (\Throwable $e) {
            \Log::warning('Shopify net sales lookup failed: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Save (upsert) every channel row into the history table for the current
     * Pacific (PDT/PST) business day. One row per (snapshot_date, channel),
     * so repeated page loads within the same Pacific day refresh the day's
     * value rather than piling up duplicates — giving the badge trend chart a
     * clean daily history. The store-level S Sales figure is stored under its
     * own pseudo-channel row.
     *
     * @param  array<int, array<string, mixed>>  $rows  flattened channel rows
     */
    private function snapshotChannels(array $rows, float $netSales = 0.0): void
    {
        $now   = Carbon::now(self::SNAPSHOT_TIMEZONE);
        $today = $now->toDateString();

        foreach ($rows as $row) {
            $channel = (string) ($row['channel'] ?? '');
            if ($channel === '') {
                continue;
            }
            $this->saveSnapshotRow($today, $channel, [
                'spend'  => (float) ($row['spend'] ?? 0),
                'clicks' => (float) ($row['clicks'] ?? 0),
                'sold'   => (float) ($row['sold'] ?? 0),
                'sales'  => (float) ($row['sales'] ?? 0),
                'active' => (int) ($row['active'] ?? 0),
            ], $now);
        }

        // Store-level S Sales kept as its own pseudo-channel row (the
        // net-sales figure lives in the `sales` column). Excluded from the
        // channel totals in history().
        $this->saveSnapshotRow($today, self::SSALES_CHANNEL, ['sales' => $netSales], $now);
    }

    /**
     * Upsert a single history row for (snapshot_date, channel). The original
     * `created_at` is preserved on the first insert; only `updated_at` moves
     * on subsequent saves the same Pacific day.
     *
     * @param  array<string, float>  $measures
     */
    private function saveSnapshotRow(string $date, string $channel, array $measures, Carbon $now): void
    {
        $query = DB::table('shopify_ads_master_metric_snapshots')
            ->where('snapshot_date', $date)
            ->where('channel', $channel);

        if ($query->exists()) {
            (clone $query)->update(array_merge($measures, ['updated_at' => $now]));

            return;
        }

        DB::table('shopify_ads_master_metric_snapshots')->insert(array_merge($measures, [
            'snapshot_date' => $date,
            'channel'       => $channel,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]));
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
        // Anchor the window to the Pacific business day so it lines up with
        // the timezone the snapshots are stamped in.
        $from = Carbon::now(self::SNAPSHOT_TIMEZONE)->subDays($days - 1)->toDateString();

        $rows = DB::table('shopify_ads_master_metric_snapshots')
            ->where('snapshot_date', '>=', $from)
            ->orderBy('snapshot_date')
            ->get(['snapshot_date', 'channel', 'spend', 'clicks', 'sold', 'sales', 'active']);

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
                'active' => (float) ($r->active ?? 0),
            ];

            if ($isSubRow) {
                continue;
            }

            $byDate[$d] ??= ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0, 'active' => 0.0];
            $byDate[$d]['spend']  += (float) $r->spend;
            $byDate[$d]['clicks'] += (float) $r->clicks;
            $byDate[$d]['sold']   += (float) $r->sold;
            $byDate[$d]['sales']  += (float) $r->sales;
            $byDate[$d]['active'] += (float) ($r->active ?? 0);
        }

        // Use the union of all dates so an ssales-only day still shows.
        $labels = array_keys($allDates);
        sort($labels);
        foreach ($labels as $d) {
            $byDate[$d] ??= ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0, 'active' => 0.0];
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
        $series = ['spend' => [], 'clicks' => [], 'sold' => [], 'sales' => [], 'active' => [], 'cvr' => [], 'acos' => [], 'tcos' => []];
        foreach ($labels as $d) {
            $m = $byDate[$d];
            $series['spend'][]  = round($m['spend'], 2);
            $series['clicks'][] = (int) round($m['clicks']);
            $series['sold'][]   = (int) round($m['sold']);
            $series['sales'][]  = round($m['sales'], 2);
            $series['active'][] = (int) round($m['active'] ?? 0);
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
                $byDate[$d] = $perDay[$d] ?? ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0, 'active' => 0.0];
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
     * YouTube ads totals (campaigns whose name ends with " YT" — same scope as
     * /google/shopping/youtube-ads).
     */
    private function googleYoutubeAdsMetrics(): array
    {
        return $this->googleAdsChannelMetrics('Youtube ads', 'youtube');
    }

    /**
     * Shared L30 totals query for Google Ads sub-channels:
     *   shopping — excludes SEARCH and YT (matches /google/shopping/google-shopping)
     *   serp     — SEARCH-named campaigns only
     *   youtube  — names ending with " YT"
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
                // GA4 actual revenue only — same as /google/shopping/google-shopping and
                // /google/shopping/google-serp (no fallback to ga4_ad_sales / Google Ads
                // conversionsValue, which can show e.g. $102 when GA4 reports $0).
                ->selectRaw('COALESCE(SUM(ga4_actual_revenue), 0) as sales')
                ->groupBy('campaign_id');

            if ($bounds !== null) {
                $campaigns->whereNotNull('date')
                    ->whereBetween('date', [$bounds['start'], $bounds['end']]);
            }

            if ($scope === 'shopping') {
                $campaigns->whereRaw('UPPER(campaign_name) NOT LIKE ?', ['% SEARCH%'])
                    ->whereRaw('UPPER(campaign_name) NOT LIKE ?', ['% YT']);
            } elseif ($scope === 'serp') {
                $campaigns->whereRaw('UPPER(campaign_name) LIKE ?', ['% SEARCH%']);
            } elseif ($scope === 'youtube') {
                $campaigns->whereRaw('UPPER(campaign_name) LIKE ?', ['% YT']);
            }

            $row = DB::query()
                ->fromSub($campaigns, 'campaigns')
                ->selectRaw('COALESCE(SUM(spend), 0) as spend')
                ->selectRaw('COALESCE(SUM(clicks), 0) as clicks')
                ->selectRaw('COALESCE(SUM(sold), 0) as sold')
                ->selectRaw('COALESCE(SUM(sales), 0) as sales')
                ->first();

            if ($row !== null) {
                $row->active = $this->googleAdsActiveCount($scope, $bounds);
            }

            return $this->metricRow($label, $row);
        } catch (\Throwable) {
            return $this->metricRow($label);
        }
    }

    /**
     * Count ACTIVE (campaign_status = ENABLED on the latest date row) Google Ads campaigns
     * in the same window + name scope as {@see googleAdsChannelMetrics()} — matches the
     * ACTIVE badge on the /google/shopping/* grids.
     *
     * @param  array{start: string, end: string}|null  $bounds
     */
    private function googleAdsActiveCount(string $scope, ?array $bounds): int
    {
        $latest = DB::table('google_ads_campaigns')
            ->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, MAX(`date`) as max_d')
            ->groupBy('campaign_id');

        if ($bounds !== null) {
            $latest->whereNotNull('date')->whereBetween('date', [$bounds['start'], $bounds['end']]);
        }
        if ($scope === 'shopping') {
            $latest->whereRaw('UPPER(campaign_name) NOT LIKE ?', ['% SEARCH%'])
                ->whereRaw('UPPER(campaign_name) NOT LIKE ?', ['% YT']);
        } elseif ($scope === 'serp') {
            $latest->whereRaw('UPPER(campaign_name) LIKE ?', ['% SEARCH%']);
        } elseif ($scope === 'youtube') {
            $latest->whereRaw('UPPER(campaign_name) LIKE ?', ['% YT']);
        }

        return (int) DB::table('google_ads_campaigns as g')
            ->joinSub($latest, 'l', function ($j) {
                $j->on('g.campaign_id', '=', 'l.campaign_id')
                    ->on('g.date', '=', 'l.max_d');
            })
            ->whereRaw('UPPER(TRIM(COALESCE(g.campaign_status, ""))) = ?', ['ENABLED'])
            ->distinct()
            ->count('g.campaign_id');
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
            $active = 0;

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
                if (! empty($ctx['activeCids'][$cid])) {
                    $active++;
                }
            }

            return $this->metricRow($label, (object) compact('spend', 'clicks', 'sold', 'sales', 'active'), $isSubRow);
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
            'activeCids' => [],
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
        $activeCids = [];
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

                // "Campaign delivery" = Active marks a live campaign (Meta Spend export).
                $delivery = trim((string) ($rd['Campaign delivery'] ?? ''));
                if (strcasecmp($delivery, 'Active') === 0) {
                    $activeCids[$cid] = true;
                }
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
            'activeCids' => $activeCids,
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
            'active' => (int) ($row->active ?? 0),
            // Sub-rows are typed slices of a parent channel (e.g.
            // "Facebook · G Video" is a subset of "Facebook"). Frontend
            // skips them when summing the rolled-up "All channels" badges.
            'is_sub_row' => $isSubRow,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function advertisementMasterMetricRow(string $channel, string $source, ?object $row, bool $isSubRow = false): array
    {
        $spend = (float) ($row->spend ?? 0);
        $clicks = (float) ($row->clicks ?? 0);
        $sold = (float) ($row->sold ?? 0);
        $sales = (float) ($row->sales ?? 0);

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
            'marketplace' => 'shopify',
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
