<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\FacebookAllAdsSheet;
use App\Models\FacebookB2bB2cOption;
use App\Models\ShopifyMetaCampaign;
use App\Support\GoogleYoutubeCampaignSales;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
     * Facebook (CH=FB) spend for Active campaigns only — matches the default
     * Status=active filter on /facebook-ads (Spend badge).
     * Used by /all-marketplace-master FB Marketplace Ads% / Spend / N PFT / N ROI
     * and /facebook-ads TCOS.
     *
     * @return array{spend: float, clicks: float, sold: float, sales: float, active: int}
     */
    public function getFacebookChannelSpend(): array
    {
        try {
            $row = $this->metaChannelMetrics('Facebook', 'FB', null, false, true);
        } catch (\Throwable $e) {
            \Log::warning('ShopifyAdsMaster::getFacebookChannelSpend failed: ' . $e->getMessage());
            $row = [];
        }

        return [
            'spend'  => round((float) ($row['spend'] ?? 0), 2),
            'clicks' => (float) ($row['clicks'] ?? 0),
            'sold'   => (float) ($row['sold'] ?? 0),
            'sales'  => (float) ($row['sales'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
        ];
    }

    /**
     * Rolled-up Spend + TCOS for the parent channels the /shopify-ads-master
     * SPEND / TCOS badges sum (Google Shopping, Google SERP, Youtube ads,
     * TikTok Video Ads, Facebook, Instagram — sub-rows excluded so Facebook · G Video etc. don't
     * double count). Used by the Shopify row on /all-marketplace-master so its
     * Spend and Ads%/TACOS match those badges.
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
                $this->googleYoutubeAdsMetrics(),
                $this->tiktokVideoAdsMetrics(),
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
            ['TikTok Video Ads', 'shopify_tiktok_video_ads', $this->tiktokVideoAdsMetrics()],
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

        $facebookChildren = [];
        foreach ($this->metaAdTypeLenses('shopify_facebook') as [$suffix, $source, $adTypes]) {
            $subMetrics = $this->metaChannelMetrics('Facebook'.$sep.$suffix, 'FB', $adTypes, true);
            $child = self::advertisementMasterMetricRow(
                'Shopify'.$sep.'Facebook'.$sep.$suffix,
                $source,
                (object) $subMetrics,
                true
            );
            $href = $this->metaAdTypePageUrl($adTypes[0] ?? '');
            if ($href !== null) {
                $child['href'] = $href;
            }
            $facebookChildren[] = $child;
        }
        foreach ($this->facebookB2bAdvertisementChildren('Facebook', 'FB') as $child) {
            $facebookChildren[] = $child;
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

        $instagramChildren = [];
        foreach ($this->metaAdTypeLenses('shopify_instagram', false) as [$suffix, $source, $adTypes]) {
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
        // UX as /advertisement-master). Instagram keeps the four video/carousel
        // types. Facebook also lists every other sheet type (Music Store, Music
        // School, and types saved later). Children keep `is_sub_row=true` so the
        // rolled-up badges and history endpoint skip them — they're slices of the
        // parent, not new channels.
        $sep = self::SUBROW_SEPARATOR;

        $facebook = $this->metaChannelMetrics('Facebook', 'FB');
        $facebook['_children'] = [];
        foreach ($this->metaAdTypeLenses('shopify_facebook') as [$suffix, , $adTypes]) {
            $facebook['_children'][] = $this->metaChannelMetrics('Facebook'.$sep.$suffix, 'FB', $adTypes, true);
        }
        foreach ($this->facebookB2bOptionNames() as $tag) {
            $facebook['_children'][] = $this->metaChannelMetrics('Facebook'.$sep.$tag, 'FB', null, true, false, $tag);
        }

        $instagram = $this->metaChannelMetrics('Instagram', 'Insta');
        $instagram['_children'] = [];
        foreach ($this->metaAdTypeLenses('shopify_instagram', false) as [$suffix, , $adTypes]) {
            $instagram['_children'][] = $this->metaChannelMetrics('Instagram'.$sep.$suffix, 'Insta', $adTypes, true);
        }
        $rows = [
            $this->googleShoppingMetrics(),
            $this->googleSerpMetrics(),
            $this->googleYoutubeAdsMetrics(),
            $this->tiktokVideoAdsMetrics(),
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
        // "previous" never means today. ACOS / TCOS are inverted downstream
        // (a higher value is worse → red); spend increasing is green.
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
     * is "good") is applied on the frontend, where ACOS / TCOS are inverted.
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
        // Same window as /google/shopping/google-shopping: the last completed
        // California day, never the incomplete Pacific "today".
        $endC = $this->completedCaliforniaChartEnd();
        $from = $endC->copy()->subDays($days - 1)->toDateString();

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
        $savedKeys  = [];   // channel|date already stored — do not overwrite
        foreach ($rows as $r) {
            // DATE columns can come back as Y-m-d or Y-m-d H:i:s. Keep the
            // calendar day only so a snapshot is not dropped as "no data".
            $d  = substr((string) $r->snapshot_date, 0, 10);
            $ch = (string) $r->channel;
            $savedKeys[$ch.'|'.$d] = true;

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

        // Continuous calendar window ending on the last completed California day.
        // Days this page never snapshotted are rebuilt from the source tables
        // before the chart draws.
        $end = $endC->toDateString();
        $labels = [];
        $cursor = Carbon::parse($from, self::SNAPSHOT_TIMEZONE)->startOfDay();
        $endC = Carbon::parse($end, self::SNAPSHOT_TIMEZONE)->startOfDay();
        while ($cursor->lte($endC)) {
            $labels[] = $cursor->toDateString();
            $cursor->addDay();
        }

        $refreshedSold = [];
        $this->fillHistoryFromSources($labels, $byChannel, $ssalesByDate, $refreshedSold);
        $this->persistCalculatedHistory($byChannel, $ssalesByDate, $savedKeys, $refreshedSold);
        $byDate = $this->rollupParentChannels($byChannel);

        $metrics = $this->buildMetricSeries($byDate, $labels, $ssalesByDate);
        $metrics['ssales'] = array_map(
            fn ($d) => array_key_exists($d, $ssalesByDate) ? round($ssalesByDate[$d], 2) : null,
            $labels
        );
        return response()->json([
            'status'   => 200,
            'days'     => $days,
            'labels'   => array_map(
                fn ($d) => Carbon::parse($d, self::SNAPSHOT_TIMEZONE)->format('M d'),
                $labels
            ),
            'metrics'  => $metrics,
            'channels' => $this->buildChannelSeries($byChannel, $labels, $ssalesByDate),
        ]);
    }

    /**
     * Fill calendar days that /shopify-ads-master itself never snapshotted.
     *
     * Google Ads and Shopify orders are stored per day, so each missing day
     * is the same L30 window the badges use (that day and the 29 before it).
     * Meta and TikTok campaign snapshots are already that L30 total for the
     * day they were saved, so a missing day uses that day's snapshot sum.
     *
     * @param  array<int, string>  $labels
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     * @param  array<string, float>  $ssalesByDate
     * @param  array<string, bool>  $refreshedSold  channel|date whose sold/sales came from the source
     */
    private function fillHistoryFromSources(array $labels, array &$byChannel, array &$ssalesByDate, array &$refreshedSold): void
    {
        if ($labels === []) {
            return;
        }

        try {
            $this->fillGoogleHistory($labels, $byChannel, $refreshedSold);
        } catch (\Throwable $e) {
            \Log::warning('ShopifyAdsMaster history Google backfill failed: ' . $e->getMessage());
        }

        try {
            $this->fillMetaHistory($labels, $byChannel, $refreshedSold);
        } catch (\Throwable $e) {
            \Log::warning('ShopifyAdsMaster history Meta backfill failed: ' . $e->getMessage());
        }

        try {
            $this->fillTiktokHistory($labels, $byChannel, $refreshedSold);
        } catch (\Throwable $e) {
            \Log::warning('ShopifyAdsMaster history TikTok backfill failed: ' . $e->getMessage());
        }

        try {
            $this->fillShopifySalesHistory($labels, $ssalesByDate);
        } catch (\Throwable $e) {
            \Log::warning('ShopifyAdsMaster history Shopify sales backfill failed: ' . $e->getMessage());
        }
    }

    /**
     * Store source-calculated days that this page never snapshotted.
     * Existing rows are left alone. The chart then reads this table.
     *
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     * @param  array<string, float>  $ssalesByDate
     * @param  array<string, bool>  $savedKeys  channel|date
     * @param  array<string, bool>  $refreshedSold  channel|date
     */
    private function persistCalculatedHistory(array $byChannel, array $ssalesByDate, array $savedKeys, array $refreshedSold = []): void
    {
        $now = Carbon::now(self::SNAPSHOT_TIMEZONE)->toDateTimeString();
        $rows = [];

        foreach ($byChannel as $channel => $perDay) {
            foreach ($perDay as $date => $m) {
                if (isset($savedKeys[$channel.'|'.$date])) {
                    continue;
                }
                $rows[] = [
                    'snapshot_date' => $date,
                    'channel'       => $channel,
                    'spend'         => round((float) ($m['spend'] ?? 0), 2),
                    'clicks'        => round((float) ($m['clicks'] ?? 0), 2),
                    'sold'          => round((float) ($m['sold'] ?? 0), 2),
                    'sales'         => round((float) ($m['sales'] ?? 0), 2),
                    'active'        => (int) round((float) ($m['active'] ?? 0)),
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
        }

        foreach ($ssalesByDate as $date => $sales) {
            if (isset($savedKeys[self::SSALES_CHANNEL.'|'.$date])) {
                continue;
            }
            $rows[] = [
                'snapshot_date' => $date,
                'channel'       => self::SSALES_CHANNEL,
                'spend'         => 0,
                'clicks'        => 0,
                'sold'          => 0,
                'sales'         => round((float) $sales, 2),
                'active'        => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            try {
                DB::table('shopify_ads_master_metric_snapshots')->insertOrIgnore($chunk);
            } catch (\Throwable $e) {
                \Log::warning('ShopifyAdsMaster history persist failed: ' . $e->getMessage());
            }
        }

        // Page-open rows keep spend and clicks. Sold and ads sales are
        // rewritten from the source window so a later GA4 sync, or YouTube's
        // actual-else-conversions rule, is what the chart stores.
        foreach ($refreshedSold as $key => $_) {
            [$channel, $date] = explode('|', (string) $key, 2);
            $m = $byChannel[$channel][$date] ?? null;
            if ($m === null) {
                continue;
            }
            try {
                DB::table('shopify_ads_master_metric_snapshots')
                    ->where('snapshot_date', $date)
                    ->where('channel', $channel)
                    ->update([
                        'spend'      => round((float) ($m['spend'] ?? 0), 2),
                        'clicks'     => round((float) ($m['clicks'] ?? 0), 2),
                        'sold'       => round((float) ($m['sold'] ?? 0), 2),
                        'sales'      => round((float) ($m['sales'] ?? 0), 2),
                        'updated_at' => $now,
                    ]);
            } catch (\Throwable $e) {
                \Log::warning('ShopifyAdsMaster history sold refresh failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Parent-channel totals for the rolled-up chart. Sub-rows stay out so
     * Facebook · G Video is not added on top of Facebook.
     *
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     * @return array<string, array<string, float>>
     */
    private function rollupParentChannels(array $byChannel): array
    {
        $byDate = [];
        foreach ($byChannel as $channel => $perDay) {
            if (str_contains((string) $channel, self::SUBROW_SEPARATOR)) {
                continue;
            }
            foreach ($perDay as $date => $m) {
                $byDate[$date] ??= ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0, 'active' => 0.0];
                $byDate[$date]['spend']  += (float) ($m['spend'] ?? 0);
                $byDate[$date]['clicks'] += (float) ($m['clicks'] ?? 0);
                $byDate[$date]['sold']   += (float) ($m['sold'] ?? 0);
                $byDate[$date]['sales']  += (float) ($m['sales'] ?? 0);
                $byDate[$date]['active'] += (float) ($m['active'] ?? 0);
            }
        }

        return $byDate;
    }

    /**
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     * @param  array{spend: float, clicks: float, sold: float, sales: float, active?: float}  $measures
     */
    private function putHistoryDay(array &$byChannel, string $channel, string $date, array $measures): void
    {
        if (isset($byChannel[$channel][$date])) {
            return;
        }

        $byChannel[$channel][$date] = [
            'spend'  => round((float) ($measures['spend'] ?? 0), 2),
            'clicks' => (float) ($measures['clicks'] ?? 0),
            'sold'   => (float) ($measures['sold'] ?? 0),
            'sales'  => round((float) ($measures['sales'] ?? 0), 2),
            'active' => (float) ($measures['active'] ?? 0),
        ];
    }

    /**
     * Replace a day this page already snapshotted with the source measures.
     * Returns true when the day already existed.
     *
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     * @param  array{spend?: float, clicks?: float, sold?: float, sales?: float}  $measures
     * @param  array<string, bool>  $refreshedSold
     */
    private function refreshSoldSales(array &$byChannel, string $channel, string $date, array $measures, array &$refreshedSold, bool $syncSpend = false): bool
    {
        if (! isset($byChannel[$channel][$date])) {
            return false;
        }

        $sold = round((float) ($measures['sold'] ?? 0), 2);
        $sales = round((float) ($measures['sales'] ?? 0), 2);
        $spend = round((float) ($measures['spend'] ?? $byChannel[$channel][$date]['spend'] ?? 0), 2);
        $clicks = round((float) ($measures['clicks'] ?? $byChannel[$channel][$date]['clicks'] ?? 0), 2);
        $currentSold = round((float) ($byChannel[$channel][$date]['sold'] ?? 0), 2);
        $currentSales = round((float) ($byChannel[$channel][$date]['sales'] ?? 0), 2);
        $currentSpend = round((float) ($byChannel[$channel][$date]['spend'] ?? 0), 2);
        $currentClicks = round((float) ($byChannel[$channel][$date]['clicks'] ?? 0), 2);
        $spendSame = ! $syncSpend || (abs($currentSpend - $spend) < 0.005 && abs($currentClicks - $clicks) < 0.005);
        if (abs($currentSold - $sold) < 0.005 && abs($currentSales - $sales) < 0.005 && $spendSame) {
            return true;
        }

        $byChannel[$channel][$date]['sold'] = $sold;
        $byChannel[$channel][$date]['sales'] = $sales;
        if ($syncSpend) {
            $byChannel[$channel][$date]['spend'] = $spend;
            $byChannel[$channel][$date]['clicks'] = $clicks;
        }
        $refreshedSold[$channel.'|'.$date] = true;

        return true;
    }

    /**
     * Last completed California day, same rule as the Google Shopping chart.
     * Today in America/Los_Angeles is left off because that day is not finished,
     * and the end is never later than the newest google_ads_campaigns date.
     */
    private function completedCaliforniaChartEnd(): Carbon
    {
        $tz = self::SNAPSHOT_TIMEZONE;
        $usYesterday = Carbon::now($tz)->subDay()->startOfDay();

        $maxDateStr = null;
        try {
            if (Schema::hasTable('google_ads_campaigns')) {
                $maxDateStr = DB::table('google_ads_campaigns')->whereNotNull('date')->max('date');
            }
        } catch (\Throwable) {
            $maxDateStr = null;
        }

        if ($maxDateStr !== null && $maxDateStr !== '') {
            $maxData = Carbon::parse(substr((string) $maxDateStr, 0, 10), $tz)->startOfDay();
            if ($maxData->lt($usYesterday)) {
                return $maxData;
            }
        }

        return $usYesterday;
    }

    /**
     * @param  array<int, string>  $labels
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     * @param  array<string, bool>  $refreshedSold
     */
    private function fillGoogleHistory(array $labels, array &$byChannel, array &$refreshedSold): void
    {
        if (! Schema::hasTable('google_ads_campaigns')) {
            return;
        }

        $end = $labels[array_key_last($labels)];
        $start = Carbon::parse($labels[0], self::SNAPSHOT_TIMEZONE)->subDays(29)->toDateString();

        foreach ([
            'Google Shopping' => 'shopping',
            'Google SERP'     => 'serp',
        ] as $channel => $scope) {
            $daily = $this->googleScopeDaily($scope, $start, $end);
            $active = $this->googleActiveByDate($scope, $start, $end);
            $pageSold = $this->googlePageSoldByDate($scope, $labels[0], $end);
            $tableSold = $channel === 'Google Shopping'
                ? $this->shopifyB2cAdSoldByDate($labels[0], $end)
                : [];
            $this->writeRollingGoogleChannel($byChannel, $labels, $channel, $daily, $active, $pageSold, $tableSold, $refreshedSold);
        }

        $this->writeRollingYoutubeChannel(
            $byChannel,
            $labels,
            $this->googleYoutubeCampaignDays($start, $end),
            $this->googleActiveByDate('youtube', $start, $end),
            $this->googlePageSoldByDate('youtube', $labels[0], $end),
            $refreshedSold
        );
    }

    /**
     * Sold and ads sales the Google grid chart saved for each day
     * (google_ads_sbgt_snapshots). That is the number on /google/shopping.
     *
     * @return array<string, array{spend: float, clicks: float, sold: float, sales: float}>
     */
    private function googlePageSoldByDate(string $channelKey, string $start, string $end): array
    {
        if (! Schema::hasTable('google_ads_sbgt_snapshots')
            || ! Schema::hasColumn('google_ads_sbgt_snapshots', 'sold_l30')) {
            return [];
        }

        $rows = DB::table('google_ads_sbgt_snapshots')
            ->where('channel', $channelKey)
            ->whereBetween('snapshot_date', [$start, $end])
            ->whereNotNull('sold_l30')
            ->groupBy('snapshot_date')
            ->selectRaw('snapshot_date')
            ->selectRaw('SUM(COALESCE(sold_l30, 0)) as sold')
            ->selectRaw('SUM(COALESCE(sales_l30, 0)) as sales')
            ->selectRaw('SUM(COALESCE(spend_l30, 0)) as spend')
            ->selectRaw('SUM(COALESCE(clicks_l30, 0)) as clicks')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[substr((string) $r->snapshot_date, 0, 10)] = [
                'spend'  => (float) $r->spend,
                'clicks' => (float) $r->clicks,
                'sold'   => (float) $r->sold,
                'sales'  => (float) $r->sales,
            ];
        }

        return $out;
    }

    /**
     * Active Channel Shopify B2C ad sold / ad sales, already calculated into
     * channel_master_daily_data. Used when Google has no campaign row for that day.
     *
     * @return array<string, array{sold: float, sales: float}>
     */
    private function shopifyB2cAdSoldByDate(string $start, string $end): array
    {
        if (! Schema::hasTable('channel_master_daily_data')) {
            return [];
        }

        $rows = DB::table('channel_master_daily_data')
            ->where('channel', 'shopifyb2c')
            ->whereBetween('snapshot_date', [$start, $end])
            ->get(['snapshot_date', 'summary_data']);

        $out = [];
        foreach ($rows as $r) {
            $summary = json_decode((string) $r->summary_data, true);
            if (! is_array($summary) || ! array_key_exists('ad_sold', $summary)) {
                continue;
            }
            $out[substr((string) $r->snapshot_date, 0, 10)] = [
                'sold'  => (float) $summary['ad_sold'],
                'sales' => (float) ($summary['ad_sales'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{spend: float, clicks: float, sold: float, sales: float}>
     */
    private function googleScopeDaily(string $scope, string $start, string $end): array
    {
        $query = DB::table('google_ads_campaigns')
            ->whereNotNull('campaign_id')
            ->whereNotNull('date')
            ->whereBetween('date', [$start, $end]);
        $this->applyGoogleScope($query, $scope);

        $rows = $query
            ->select('date')
            ->selectRaw('SUM(metrics_cost_micros) / 1000000 as spend')
            ->selectRaw('SUM(metrics_clicks) as clicks')
            ->selectRaw('SUM(ga4_actual_sold_units) as sold')
            ->selectRaw('COALESCE(SUM(ga4_actual_revenue), 0) as sales')
            ->groupBy('date')
            ->get();

        $daily = [];
        foreach ($rows as $r) {
            $daily[substr((string) $r->date, 0, 10)] = [
                'spend'  => (float) $r->spend,
                'clicks' => (float) $r->clicks,
                'sold'   => (float) $r->sold,
                'sales'  => (float) $r->sales,
            ];
        }

        return $daily;
    }

    /**
     * Per campaign, per day. YouTube sales are lifted on the L30 campaign
     * total, which a pre-summed daily total cannot do.
     *
     * @return array<string, array{name: string, days: array<string, array{spend: float, clicks: float, sold_actual: float, sold_fallback: float, sales_actual: float, sales_fallback: float}>}>
     */
    private function googleYoutubeCampaignDays(string $start, string $end): array
    {
        $query = DB::table('google_ads_campaigns')
            ->whereNotNull('campaign_id')
            ->whereNotNull('date')
            ->whereBetween('date', [$start, $end]);
        $this->applyGoogleScope($query, 'youtube');

        $rows = $query
            ->select('date', 'campaign_id')
            ->selectRaw('MAX(campaign_name) as campaign_name')
            ->selectRaw('SUM(metrics_cost_micros) / 1000000 as spend')
            ->selectRaw('SUM(metrics_clicks) as clicks')
            ->selectRaw('SUM(ga4_actual_sold_units) as sold_actual')
            ->selectRaw('SUM(ga4_sold_units) as sold_fallback')
            ->selectRaw('SUM(ga4_actual_revenue) as sales_actual')
            ->selectRaw('SUM(ga4_ad_sales) as sales_fallback')
            ->groupBy('date', 'campaign_id')
            ->get();

        $byCampaign = [];
        foreach ($rows as $r) {
            $cid = (string) $r->campaign_id;
            $byCampaign[$cid] ??= ['name' => (string) ($r->campaign_name ?? ''), 'days' => []];
            if ($byCampaign[$cid]['name'] === '' && $r->campaign_name) {
                $byCampaign[$cid]['name'] = (string) $r->campaign_name;
            }
            $byCampaign[$cid]['days'][substr((string) $r->date, 0, 10)] = [
                'spend'          => (float) $r->spend,
                'clicks'         => (float) $r->clicks,
                'sold_actual'    => (float) $r->sold_actual,
                'sold_fallback'  => (float) $r->sold_fallback,
                'sales_actual'   => (float) $r->sales_actual,
                'sales_fallback' => (float) $r->sales_fallback,
            ];
        }

        return $byCampaign;
    }

    /**
     * @return array<string, float> date => enabled campaign count
     */
    private function googleActiveByDate(string $scope, string $start, string $end): array
    {
        $query = DB::table('google_ads_campaigns')
            ->whereNotNull('campaign_id')
            ->whereNotNull('date')
            ->whereBetween('date', [$start, $end])
            ->whereRaw('UPPER(TRIM(COALESCE(campaign_status, ""))) = ?', ['ENABLED']);
        $this->applyGoogleScope($query, $scope);

        $rows = $query
            ->select('date')
            ->selectRaw('COUNT(DISTINCT campaign_id) as active')
            ->groupBy('date')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[substr((string) $r->date, 0, 10)] = (float) $r->active;
        }

        return $out;
    }

    private function applyGoogleScope($query, string $scope): void
    {
        if ($scope === 'shopping') {
            $query->whereRaw('UPPER(campaign_name) NOT LIKE ?', ['% SEARCH%'])
                ->whereRaw('UPPER(campaign_name) NOT LIKE ?', ['% YT']);
        } elseif ($scope === 'serp') {
            $query->whereRaw('UPPER(campaign_name) LIKE ?', ['% SEARCH%']);
        } elseif ($scope === 'youtube') {
            $query->whereRaw('UPPER(campaign_name) LIKE ?', ['% YT']);
        }
    }

    /**
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     * @param  array<int, string>  $labels
     * @param  array<string, array{spend: float, clicks: float, sold: float, sales: float}>  $daily
     * @param  array<string, float>  $activeByDate
     * @param  array<string, array{spend: float, clicks: float, sold: float, sales: float}>  $pageSold
     * @param  array<string, array{sold: float, sales: float}>  $tableSold
     * @param  array<string, bool>  $refreshedSold
     */
    private function writeRollingGoogleChannel(array &$byChannel, array $labels, string $channel, array $daily, array $activeByDate, array $pageSold, array $tableSold, array &$refreshedSold): void
    {
        foreach ($labels as $day) {
            $sum = $this->sumDailyWindow($daily, $day, 30);
            if (isset($pageSold[$day])) {
                // Same totals the Google Shopping / SERP / YouTube chart saved
                // for this California day (google_ads_sbgt_snapshots).
                $measures = [
                    'spend'  => (float) $pageSold[$day]['spend'],
                    'clicks' => (float) $pageSold[$day]['clicks'],
                    'sold'   => (float) $pageSold[$day]['sold'],
                    'sales'  => (float) $pageSold[$day]['sales'],
                    'active' => $this->activeOnOrBefore($activeByDate, $day),
                ];
            } elseif ($sum !== null) {
                $sum['active'] = $this->activeOnOrBefore($activeByDate, $day);
                $measures = $sum;
            } elseif (isset($tableSold[$day])) {
                $measures = [
                    'spend'  => 0.0,
                    'clicks' => 0.0,
                    'sold'   => (float) $tableSold[$day]['sold'],
                    'sales'  => (float) $tableSold[$day]['sales'],
                    'active' => 0.0,
                ];
            } else {
                continue;
            }
            // Google has no campaign row for this day. Use the sold Active
            // Channel already calculated into channel_master_daily_data.
            if (! isset($daily[$day]) && isset($tableSold[$day])) {
                $measures['sold'] = (float) $tableSold[$day]['sold'];
                $measures['sales'] = (float) $tableSold[$day]['sales'];
            }
            if ($this->refreshSoldSales($byChannel, $channel, $day, $measures, $refreshedSold, true)) {
                continue;
            }
            $this->putHistoryDay($byChannel, $channel, $day, $measures);
        }
    }

    /**
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     * @param  array<int, string>  $labels
     * @param  array<string, array{name: string, days: array<string, array<string, float>>}>  $byCampaign
     * @param  array<string, float>  $activeByDate
     * @param  array<string, array{spend: float, clicks: float, sold: float, sales: float}>  $pageSold
     * @param  array<string, bool>  $refreshedSold
     */
    private function writeRollingYoutubeChannel(array &$byChannel, array $labels, array $byCampaign, array $activeByDate, array $pageSold, array &$refreshedSold): void
    {
        $channel = 'Youtube ads';
        foreach ($labels as $day) {
            if (isset($pageSold[$day])) {
                $measures = [
                    'spend'  => (float) $pageSold[$day]['spend'],
                    'clicks' => (float) $pageSold[$day]['clicks'],
                    'sold'   => (float) $pageSold[$day]['sold'],
                    'sales'  => (float) $pageSold[$day]['sales'],
                    'active' => $this->activeOnOrBefore($activeByDate, $day),
                ];
                if ($this->refreshSoldSales($byChannel, $channel, $day, $measures, $refreshedSold)) {
                    continue;
                }
                $this->putHistoryDay($byChannel, $channel, $day, $measures);
                continue;
            }
            $end = $day;
            $start = Carbon::parse($day, self::SNAPSHOT_TIMEZONE)->subDays(29)->toDateString();
            $spend = 0.0;
            $clicks = 0.0;
            $sold = 0.0;
            $sales = 0.0;
            $saw = false;

            foreach ($byCampaign as $campaign) {
                $sp = $cl = $soldActual = $soldFallback = $salesActual = $salesFallback = 0.0;
                foreach ($campaign['days'] as $date => $m) {
                    if ($date < $start || $date > $end) {
                        continue;
                    }
                    $saw = true;
                    $sp += (float) $m['spend'];
                    $cl += (float) $m['clicks'];
                    $soldActual += (float) $m['sold_actual'];
                    $soldFallback += (float) $m['sold_fallback'];
                    $salesActual += (float) $m['sales_actual'];
                    $salesFallback += (float) $m['sales_fallback'];
                }
                if ($sp == 0.0 && $cl == 0.0 && $soldActual == 0.0 && $soldFallback == 0.0 && $salesActual == 0.0 && $salesFallback == 0.0) {
                    continue;
                }
                $cSold = $soldActual > 0 ? $soldActual : $soldFallback;
                $cSales = $salesActual > 0 ? $salesActual : $salesFallback;
                $spend += $sp;
                $clicks += $cl;
                $sold += $cSold;
                $sales += GoogleYoutubeCampaignSales::lift($cSales, $cSold, $campaign['name']);
            }

            if (! $saw) {
                continue;
            }

            $measures = [
                'spend'  => $spend,
                'clicks' => $clicks,
                'sold'   => $sold,
                'sales'  => $sales,
                'active' => $this->activeOnOrBefore($activeByDate, $day),
            ];
            if ($this->refreshSoldSales($byChannel, $channel, $day, $measures, $refreshedSold)) {
                continue;
            }
            $this->putHistoryDay($byChannel, $channel, $day, $measures);
        }
    }

    /**
     * @param  array<string, array{spend: float, clicks: float, sold: float, sales: float}>  $daily
     * @return array{spend: float, clicks: float, sold: float, sales: float}|null
     */
    private function sumDailyWindow(array $daily, string $day, int $window): ?array
    {
        $start = Carbon::parse($day, self::SNAPSHOT_TIMEZONE)->subDays($window - 1)->toDateString();
        $sum = ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0];
        $saw = false;
        $cursor = Carbon::parse($start, self::SNAPSHOT_TIMEZONE);
        $end = Carbon::parse($day, self::SNAPSHOT_TIMEZONE);
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            if (isset($daily[$key])) {
                $saw = true;
                $sum['spend'] += (float) $daily[$key]['spend'];
                $sum['clicks'] += (float) $daily[$key]['clicks'];
                $sum['sold'] += (float) $daily[$key]['sold'];
                $sum['sales'] += (float) $daily[$key]['sales'];
            }
            $cursor->addDay();
        }

        return $saw ? $sum : null;
    }

    /**
     * @param  array<string, float>  $activeByDate
     */
    private function activeOnOrBefore(array $activeByDate, string $day): float
    {
        if (isset($activeByDate[$day])) {
            return (float) $activeByDate[$day];
        }
        $cursor = Carbon::parse($day, self::SNAPSHOT_TIMEZONE)->subDay();
        $floor = Carbon::parse($day, self::SNAPSHOT_TIMEZONE)->subDays(29);
        while ($cursor->gte($floor)) {
            $key = $cursor->toDateString();
            if (isset($activeByDate[$key])) {
                return (float) $activeByDate[$key];
            }
            $cursor->subDay();
        }

        return 0.0;
    }

    /**
     * @param  array<int, string>  $labels
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     * @param  array<string, bool>  $refreshedSold
     */
    private function fillMetaHistory(array $labels, array &$byChannel, array &$refreshedSold): void
    {
        if (Schema::hasTable('facebook_campaign_metric_snapshots')) {
            $this->fillMetaFromCampaignSnapshots($labels, $byChannel, $refreshedSold);
        }

        // Days the Facebook sheet was not opened still have a real daily
        // spend/click/purchase row in meta_insights_daily. Roll those into
        // the same L30 total the badges use so the line keeps moving.
        $this->fillMetaFromInsights($labels, $byChannel);
    }

    /**
     * @param  array<int, string>  $labels
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     * @param  array<string, bool>  $refreshedSold
     */
    private function fillMetaFromCampaignSnapshots(array $labels, array &$byChannel, array &$refreshedSold): void
    {
        $from = $labels[0];
        $end = $labels[array_key_last($labels)];
        $rows = DB::table('facebook_campaign_metric_snapshots')
            ->whereBetween('snapshot_date', [$from, $end])
            ->get(['campaign_id', 'snapshot_date', 'spend', 'clk', 'sold', 'sales']);

        if ($rows->isEmpty()) {
            return;
        }

        $chMap = $this->facebookChMap();
        $adTypeMap = $this->facebookAdTypeMap();
        $subByType = $this->metaSubChannelsByAdType();

        /** @var array<string, array<string, array{spend: float, clicks: float, sold: float, sales: float, active: float}>> $bucket */
        $bucket = [];
        foreach ($rows as $r) {
            $cid = (string) $r->campaign_id;
            $code = $chMap[$cid] ?? null;
            if ($code !== 'FB' && $code !== 'Insta') {
                continue;
            }
            $parent = $code === 'FB' ? 'Facebook' : 'Instagram';
            $date = substr((string) $r->snapshot_date, 0, 10);
            $this->addMetaBucket($bucket, $parent, $date, $r);

            $adType = mb_strtoupper(trim((string) ($adTypeMap[$cid] ?? '')));
            foreach ($subByType[$adType] ?? [] as $subChannel) {
                if (! str_starts_with($subChannel, $parent . self::SUBROW_SEPARATOR)) {
                    continue;
                }
                $this->addMetaBucket($bucket, $subChannel, $date, $r);
            }
        }

        foreach ($bucket as $channel => $perDay) {
            foreach ($labels as $day) {
                if (! isset($perDay[$day])) {
                    continue;
                }
                if ($this->refreshSoldSales($byChannel, $channel, $day, $perDay[$day], $refreshedSold)) {
                    continue;
                }
                $this->putHistoryDay($byChannel, $channel, $day, $perDay[$day]);
            }
        }
    }

    /**
     * @param  array<int, string>  $labels
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     */
    private function fillMetaFromInsights(array $labels, array &$byChannel): void
    {
        if (! Schema::hasTable('meta_insights_daily') || ! Schema::hasTable('meta_campaigns')) {
            return;
        }

        $end = $labels[array_key_last($labels)];
        $start = Carbon::parse($labels[0], self::SNAPSHOT_TIMEZONE)->subDays(29)->toDateString();

        $rows = DB::table('meta_insights_daily as mid')
            ->join('meta_campaigns as mc', function ($join) {
                $join->on('mc.id', '=', 'mid.entity_id')
                    ->where('mid.entity_type', 'campaign');
            })
            ->whereBetween('mid.date_start', [$start, $end])
            ->where('mid.breakdown_hash', md5(json_encode([])))
            ->groupBy('mid.date_start', 'mc.meta_id')
            ->selectRaw('mid.date_start as d, mc.meta_id as meta_id')
            ->selectRaw('SUM(mid.spend) as spend')
            ->selectRaw('SUM(mid.clicks) as clicks')
            ->selectRaw('SUM(mid.purchases) as sold')
            ->selectRaw('SUM(mid.action_values) as sales')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $chMap = $this->facebookChMap();
        $adTypeMap = $this->facebookAdTypeMap();
        $subByType = $this->metaSubChannelsByAdType();

        /** @var array<string, array<string, array{spend: float, clicks: float, sold: float, sales: float}>> $daily */
        $daily = [];
        foreach ($rows as $r) {
            $cid = (string) $r->meta_id;
            $code = $chMap[$cid] ?? null;
            if ($code !== 'FB' && $code !== 'Insta') {
                continue;
            }
            $date = substr((string) $r->d, 0, 10);
            $parent = $code === 'FB' ? 'Facebook' : 'Instagram';
            $this->addInsightDay($daily, $parent, $date, $r);

            $adType = mb_strtoupper(trim((string) ($adTypeMap[$cid] ?? '')));
            foreach ($subByType[$adType] ?? [] as $subChannel) {
                if (! str_starts_with($subChannel, $parent . self::SUBROW_SEPARATOR)) {
                    continue;
                }
                $this->addInsightDay($daily, $subChannel, $date, $r);
            }
        }

        foreach ($daily as $channel => $perDay) {
            foreach ($labels as $day) {
                if (isset($byChannel[$channel][$day])) {
                    continue;
                }
                $sum = $this->sumDailyWindow($perDay, $day, 30);
                $this->putHistoryDay($byChannel, $channel, $day, $sum ?? [
                    'spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0, 'active' => 0.0,
                ]);
            }
        }
    }

    /**
     * @param  array<string, array<string, array{spend: float, clicks: float, sold: float, sales: float}>>  $daily
     */
    private function addInsightDay(array &$daily, string $channel, string $date, object $row): void
    {
        $daily[$channel][$date] ??= ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0];
        $daily[$channel][$date]['spend'] += (float) $row->spend;
        $daily[$channel][$date]['clicks'] += (float) $row->clicks;
        $daily[$channel][$date]['sold'] += (float) $row->sold;
        $daily[$channel][$date]['sales'] += (float) $row->sales;
    }

    /**
     * @param  array<string, array<string, array{spend: float, clicks: float, sold: float, sales: float, active: float}>>  $bucket
     */
    private function addMetaBucket(array &$bucket, string $channel, string $date, object $row): void
    {
        $bucket[$channel][$date] ??= ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0, 'active' => 0.0];
        $bucket[$channel][$date]['spend'] += (float) $row->spend;
        $bucket[$channel][$date]['clicks'] += (float) $row->clk;
        $bucket[$channel][$date]['sold'] += (float) $row->sold;
        $bucket[$channel][$date]['sales'] += (float) $row->sales;
        $bucket[$channel][$date]['active'] += 1;
    }

    /**
     * Uppercase ad type => channel names such as "Facebook · G Video".
     *
     * @return array<string, list<string>>
     */
    private function metaSubChannelsByAdType(): array
    {
        $out = [];
        foreach (['Facebook' => true, 'Instagram' => false] as $parent => $includeSheetTypes) {
            foreach ($this->metaAdTypeLenses(strtolower($parent), $includeSheetTypes) as [$label, $source, $types]) {
                unset($source);
                foreach ($types as $type) {
                    $key = mb_strtoupper(trim((string) $type));
                    if ($key === '') {
                        continue;
                    }
                    $out[$key][] = $parent . self::SUBROW_SEPARATOR . $label;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $labels
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     * @param  array<string, bool>  $refreshedSold
     */
    private function fillTiktokHistory(array $labels, array &$byChannel, array &$refreshedSold): void
    {
        if (! Schema::hasTable('tiktok_campaign_metric_snapshots')) {
            return;
        }

        $channel = 'TikTok Video Ads';
        $rows = DB::table('tiktok_campaign_metric_snapshots')
            ->whereBetween('snapshot_date', [$labels[0], $labels[array_key_last($labels)]])
            ->select('snapshot_date')
            ->selectRaw('SUM(spend) as spend')
            ->selectRaw('SUM(clk) as clicks')
            ->selectRaw('SUM(sold) as sold')
            ->selectRaw('SUM(sales) as sales')
            ->selectRaw('COUNT(DISTINCT campaign_id) as active')
            ->groupBy('snapshot_date')
            ->get();

        foreach ($rows as $r) {
            $day = substr((string) $r->snapshot_date, 0, 10);
            if (! in_array($day, $labels, true)) {
                continue;
            }
            $measures = [
                'spend'  => (float) $r->spend,
                'clicks' => (float) $r->clicks,
                'sold'   => (float) $r->sold,
                'sales'  => (float) $r->sales,
                'active' => (float) $r->active,
            ];
            if ($this->refreshSoldSales($byChannel, $channel, $day, $measures, $refreshedSold)) {
                continue;
            }
            $this->putHistoryDay($byChannel, $channel, $day, $measures);
        }
    }

    /**
     * Store net sales, same exclusions as the Shopify Sales badge, rolled
     * into an L30 total for each chart day.
     *
     * @param  array<int, string>  $labels
     * @param  array<string, float>  $ssalesByDate
     */
    private function fillShopifySalesHistory(array $labels, array &$ssalesByDate): void
    {
        if (! Schema::hasTable('shopify_raw_orders')) {
            return;
        }

        $end = $labels[array_key_last($labels)];
        $start = Carbon::parse($labels[0], self::SNAPSHOT_TIMEZONE)->subDays(29)->toDateString();

        $query = DB::table('shopify_raw_orders')
            ->whereRaw('DATE(order_date) >= ?', [$start])
            ->whereRaw('DATE(order_date) <= ?', [$end]);
        \App\Http\Controllers\ShopifyRawDataController::applyDirectExclusions($query);

        $rows = $query
            ->selectRaw('DATE(order_date) as d')
            ->selectRaw('SUM(net_sales) as sales')
            ->groupBy(DB::raw('DATE(order_date)'))
            ->get();

        $daily = [];
        foreach ($rows as $r) {
            $daily[substr((string) $r->d, 0, 10)] = [
                'spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => (float) $r->sales,
            ];
        }

        if ($daily === []) {
            return;
        }

        foreach ($labels as $day) {
            if (array_key_exists($day, $ssalesByDate)) {
                continue;
            }
            $sum = $this->sumDailyWindow($daily, $day, 30);
            $ssalesByDate[$day] = round((float) ($sum['sales'] ?? 0), 2);
        }
    }

    /**
     * Turn the per-day raw measures into the badge series (with CVR /
     * ACOS derived exactly like the badges / table do). Days with no
     * stored or calculated row stay null.
     *
     * @param  array<string, array<string, float>>  $byDate
     * @param  array<int, string>  $labels
     * @param  array<string, float>  $ssalesByDate  date => store net sales (for TCOS)
     * @return array<string, array<int, float|null>>
     */
    private function buildMetricSeries(array $byDate, array $labels, array $ssalesByDate = []): array
    {
        $series = ['spend' => [], 'clicks' => [], 'sold' => [], 'sales' => [], 'active' => [], 'cvr' => [], 'acos' => [], 'tcos' => []];
        foreach ($labels as $d) {
            if (! isset($byDate[$d])) {
                $series['spend'][]  = null;
                $series['clicks'][] = null;
                $series['sold'][]   = null;
                $series['sales'][]  = null;
                $series['active'][] = null;
                $series['cvr'][]    = null;
                $series['acos'][]   = null;
                $series['tcos'][]   = null;
                continue;
            }

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
     * Per-channel badge series, aligned to the same date labels. Missing
     * days stay null; the chart repeats the previous value for those days.
     *
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     * @param  array<int, string>  $labels
     * @param  array<string, float>  $ssalesByDate
     * @return array<string, array<string, array<int, float|null>>>
     */
    private function buildChannelSeries(array $byChannel, array $labels, array $ssalesByDate = []): array
    {
        $out = [];
        foreach ($byChannel as $channel => $perDay) {
            $byDate = [];
            foreach ($labels as $d) {
                if (isset($perDay[$d])) {
                    $byDate[$d] = $perDay[$d];
                }
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
     * TikTok Video Ads totals from /tiktok-video-ads.
     * Uses the latest Pacific-day snapshot of merged campaign metrics
     * (same numbers the TikTok page badges reflect after sheet upload).
     */
    private function tiktokVideoAdsMetrics(): array
    {
        try {
            if (! Schema::hasTable('tiktok_campaign_metric_snapshots')) {
                return $this->metricRow('TikTok Video Ads');
            }

            $latestDate = DB::table('tiktok_campaign_metric_snapshots')->max('snapshot_date');
            if ($latestDate === null || $latestDate === '') {
                return $this->metricRow('TikTok Video Ads');
            }

            $row = DB::table('tiktok_campaign_metric_snapshots')
                ->where('snapshot_date', $latestDate)
                ->selectRaw('COALESCE(SUM(spend), 0) as spend')
                ->selectRaw('COALESCE(SUM(clk), 0) as clicks')
                ->selectRaw('COALESCE(SUM(sold), 0) as sold')
                ->selectRaw('COALESCE(SUM(sales), 0) as sales')
                ->selectRaw('COUNT(DISTINCT campaign_id) as active')
                ->first();

            return $this->metricRow('TikTok Video Ads', $row);
        } catch (\Throwable) {
            return $this->metricRow('TikTok Video Ads');
        }
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
                ->selectRaw(
                    $scope === 'youtube'
                        ? 'CASE WHEN COALESCE(SUM(ga4_actual_sold_units), 0) > 0 THEN COALESCE(SUM(ga4_actual_sold_units), 0) ELSE COALESCE(SUM(ga4_sold_units), 0) END as sold'
                        : 'SUM(ga4_actual_sold_units) as sold'
                )
                ->selectRaw(
                    $scope === 'youtube'
                        ? 'CASE WHEN COALESCE(SUM(ga4_actual_revenue), 0) > 0 THEN COALESCE(SUM(ga4_actual_revenue), 0) ELSE COALESCE(SUM(ga4_ad_sales), 0) END as sales'
                        : 'COALESCE(SUM(ga4_actual_revenue), 0) as sales'
                )
                ->selectRaw('MAX(campaign_name) as campaign_name')
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

            if ($scope === 'youtube') {
                $spend = 0.0;
                $clicks = 0.0;
                $sold = 0.0;
                $sales = 0.0;
                foreach ($campaigns->get() as $c) {
                    $spend += (float) ($c->spend ?? 0);
                    $clicks += (float) ($c->clicks ?? 0);
                    $cSold = (float) ($c->sold ?? 0);
                    $sold += $cSold;
                    $sales += GoogleYoutubeCampaignSales::lift((float) ($c->sales ?? 0), $cSold, (string) ($c->campaign_name ?? ''));
                }
                $row = (object) [
                    'spend' => $spend,
                    'clicks' => $clicks,
                    'sold' => $sold,
                    'sales' => $sales,
                ];
            } else {
                $row = DB::query()
                    ->fromSub($campaigns, 'campaigns')
                    ->selectRaw('COALESCE(SUM(spend), 0) as spend')
                    ->selectRaw('COALESCE(SUM(clicks), 0) as clicks')
                    ->selectRaw('COALESCE(SUM(sold), 0) as sold')
                    ->selectRaw('COALESCE(SUM(sales), 0) as sales')
                    ->first();
            }

            if ($row !== null) {
                $row->active = $this->googleAdsActiveCount($scope, $bounds);
                if ($scope === 'shopping') {
                    $this->applyChannelTableSold($row);
                }
            }

            return $this->metricRow($label, $row);
        } catch (\Throwable) {
            return $this->metricRow($label);
        }
    }

    /**
     * Today's Google Shopping sold comes from channel_master_daily_data when
     * google_ads_campaigns has not recorded today yet.
     */
    private function applyChannelTableSold(object $row): void
    {
        $today = Carbon::now(self::SNAPSHOT_TIMEZONE)->toDateString();
        $maxDate = DB::table('google_ads_campaigns')->whereNotNull('date')->max('date');
        $maxDay = $maxDate ? substr((string) $maxDate, 0, 10) : null;
        if ($maxDay !== null && $maxDay >= $today) {
            return;
        }

        $saved = $this->shopifyB2cAdSoldByDate($today, $today);
        if (! isset($saved[$today])) {
            return;
        }

        $row->sold = $saved[$today]['sold'];
        $row->sales = $saved[$today]['sales'];
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
     * Typed Meta sub-rows for Facebook and Instagram.
     *
     * The original four lenses keep their short labels and source keys so
     * existing snapshots stay attached. When `$includeSheetTypes` is true,
     * every other ad type from the Facebook sheet — Music Store, Music School,
     * Wholesale, Dropship, and any type later saved in facebook_ad_types — is
     * appended. Instagram stays on the original four only.
     *
     * @return list<array{0: string, 1: string, 2: list<string>}>
     */
    private function metaAdTypeLenses(string $sourcePrefix, bool $includeSheetTypes = true): array
    {
        $known = [
            'GROUP VIDEO' => ['G Video', $sourcePrefix.'_g_video'],
            'GROUP CAROUSAL' => ['G Carousal', $sourcePrefix.'_g_carousal'],
            'PARENT VIDEO' => ['P Video', $sourcePrefix.'_p_video'],
            'PARENT CAROUSAL' => ['P Carousal', $sourcePrefix.'_p_carousal'],
        ];

        $out = [];
        foreach ($known as $adType => [$label, $source]) {
            $out[] = [$label, $source, [$adType]];
        }

        if (! $includeSheetTypes) {
            return $out;
        }

        $covered = array_fill_keys(array_keys($known), true);
        try {
            $types = FacebookAllAdsSheet::allAdTypes();
        } catch (\Throwable $e) {
            $types = [];
        }

        foreach ($types as $adType) {
            $key = mb_strtoupper(trim((string) $adType));
            if ($key === '' || isset($covered[$key])) {
                continue;
            }
            $covered[$key] = true;
            $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $key));
            $slug = trim($slug, '_');
            if ($slug === '') {
                continue;
            }
            $out[] = [mb_convert_case(mb_strtolower($key), MB_CASE_TITLE, 'UTF-8'), $sourcePrefix.'_'.$slug, [$key]];
        }

        return $out;
    }

    /** Page for a Meta ad type that has its own sheet. Null keeps the existing channel link. */
    private function metaAdTypePageUrl(string $adType): ?string
    {
        return match (mb_strtoupper(trim($adType))) {
            'MUSIC STORE' => route('music.store.ads.sheet'),
            'MUSIC SCHOOL' => route('music.school.ads.sheet'),
            'GROUP VIDEO', 'GROUP CAROUSAL', 'PARENT VIDEO', 'PARENT CAROUSAL' => null,
            default => route('facebook.all.ads.sheet'),
        };
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
    /**
     * @param  bool  $activeOnly  When true, only Active campaigns contribute to
     *                            spend / clicks / sold / sales (matches /facebook-ads
     *                            default Status filter).
     */
    private function metaChannelMetrics(string $label, string $chCode, ?array $adTypeList = null, bool $isSubRow = false, bool $activeOnly = false, ?string $b2b = null): array
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
                    $at = mb_strtoupper(trim((string) ($ctx['adTypeMap'][$cid] ?? '')));
                    $wanted = array_map(
                        fn ($type) => mb_strtoupper(trim((string) $type)),
                        $adTypeList
                    );
                    if ($at === '' || ! in_array($at, $wanted, true)) {
                        continue;
                    }
                }
                if ($b2b !== null) {
                    $tag = mb_strtoupper(trim((string) ($ctx['b2bMap'][$cid] ?? '')));
                    if ($tag === '' || $tag !== mb_strtoupper(trim($b2b))) {
                        continue;
                    }
                }
                $isActive = ! empty($ctx['activeCids'][$cid]);
                if ($activeOnly && ! $isActive) {
                    continue;
                }
                if (isset($ctx['spendByCid'][$cid])) {
                    $spend  += $ctx['spendByCid'][$cid]['spend'];
                    $clicks += $ctx['spendByCid'][$cid]['clicks'];
                }
                if (isset($ctx['salesByCid'][$cid])) {
                    $sold  += $ctx['salesByCid'][$cid]['sold'];
                    $sales += $ctx['salesByCid'][$cid]['sales'];
                }
                if ($isActive) {
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
            'b2bMap'     => [],
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
            'b2bMap'     => $this->facebookB2bMap(),
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
     * Campaign ID → B2B / B2C tag from the Facebook sheet. Latest row wins.
     * Uses the same resolution as the sheet dropdown (saved value, sheet
     * column, or a single B2B / B2C token in the campaign name).
     *
     * @return array<string, string>
     */
    private function facebookB2bMap(): array
    {
        if (! Schema::hasColumn('facebook_all_ads_sheet', 'b2b_b2c')) {
            return [];
        }

        $rows = FacebookAllAdsSheet::query()
            ->whereNotNull('b2b_b2c')
            ->where('b2b_b2c', '!=', '')
            ->orderByDesc('id')
            ->get(['b2b_b2c', 'row_data']);

        $map = [];
        foreach ($rows as $r) {
            $rd = array_filter(
                (array) ($r->row_data ?? []),
                fn ($_, $k) => ! str_starts_with((string) $k, '__'),
                ARRAY_FILTER_USE_BOTH
            );
            $cid = $this->facebookFindCampaignId($rd);
            if ($cid === null || $cid === '' || isset($map[$cid])) {
                continue;
            }
            $tag = FacebookAllAdsSheet::resolveB2bB2c($r->b2b_b2c, $rd);
            if ($tag) {
                $map[$cid] = $tag;
            }
        }

        return $map;
    }

    /**
     * Every saved B2B / B2C option, including B2B and B2C when none are tagged yet.
     *
     * @return list<string>
     */
    private function facebookB2bOptionNames(): array
    {
        try {
            return FacebookB2bB2cOption::options();
        } catch (\Throwable) {
            return FacebookB2bB2cOption::BUILTIN;
        }
    }

    /**
     * Advertisement-dashboard children: "Shopify · Facebook · B2B".
     * Always present, same as Music School and the other Facebook type rows.
     *
     * @return list<array<string, mixed>>
     */
    private function facebookB2bAdvertisementChildren(string $parentLabel, string $chCode): array
    {
        $sep = self::SUBROW_SEPARATOR;
        $rows = [];
        foreach ($this->facebookB2bOptionNames() as $tag) {
            $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $tag));
            $slug = trim($slug, '_');
            if ($slug === '') {
                continue;
            }
            $metrics = $this->metaChannelMetrics($parentLabel.$sep.$tag, $chCode, null, true, false, $tag);
            $rows[] = self::advertisementMasterMetricRow(
                'Shopify'.$sep.$parentLabel.$sep.$tag,
                'shopify_'.strtolower($parentLabel).'_biz_'.$slug,
                (object) $metrics,
                true
            );
        }

        return $rows;
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
