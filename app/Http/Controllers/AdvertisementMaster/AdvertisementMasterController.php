<?php

namespace App\Http\Controllers\AdvertisementMaster;

use App\Http\Controllers\AmazonAdsController;
use App\Http\Controllers\Campaigns\Ebay2CampaignAdsController;
use App\Http\Controllers\Campaigns\Ebay3CampaignAdsController;
use App\Http\Controllers\Campaigns\EbayCampaignAdsController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MarketPlace\ShopifyAdsMasterController;
use App\Http\Controllers\Sales\AmazonSalesController;
use App\Models\AmazonOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdvertisementMasterController extends Controller
{
    /**
     * Timezone the history snapshots are stamped in. Pacific
     * (America/Los_Angeles) auto-switches between PST and PDT, so "today"
     * always means the current Pacific business day.
     */
    public const SNAPSHOT_TIMEZONE = 'America/Los_Angeles';

    /**
     * Channel-name delimiter marking a nested "sub-row" (e.g. "Amazon · KW",
     * "Shopify · Facebook"). Top-level parents have no separator, so the
     * history total sums only those and never double-counts children.
     */
    public const SUBROW_SEPARATOR = ' · ';

    /** Pseudo-channel key used to store the combined S Sales snapshot. */
    private const SSALES_CHANNEL = '__ssales__';

    public function index(Request $request)
    {
        return view('advertisement-master.advertisement_master', [
            'mode' => $request->query('mode'),
            'demo' => $request->query('demo'),
        ]);
    }

    /**
     * Home Dashboard badges — same rollup as /advertisement-master header badges
     * (parent channels only; CVR / ACOS / TCOS / TOTAL SALES derived).
     * Reads the latest Pacific-day snapshot so the dashboard stays fast.
     *
     * @return array{
     *     active: int,
     *     spend: float,
     *     clicks: float,
     *     sold: float,
     *     sales: float,
     *     cvr: float,
     *     acos: int,
     *     tcos: int,
     *     ssales: float,
     *     snapshot_date: string|null,
     *     updated_at: \Carbon\Carbon|null
     * }
     */
    public static function dashboardBadgeTotals(): array
    {
        $empty = [
            'active' => 0,
            'spend' => 0.0,
            'clicks' => 0.0,
            'sold' => 0.0,
            'sales' => 0.0,
            'cvr' => 0.0,
            'acos' => 0,
            'tcos' => 0,
            'ssales' => 0.0,
            'snapshot_date' => null,
            'updated_at' => null,
        ];

        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('advertisement_master_metric_snapshots')) {
                return $empty;
            }

            $latestDate = DB::table('advertisement_master_metric_snapshots')->max('snapshot_date');
            if (! $latestDate) {
                return $empty;
            }

            $rows = DB::table('advertisement_master_metric_snapshots')
                ->where('snapshot_date', $latestDate)
                ->get(['channel', 'spend', 'clicks', 'sold', 'sales', 'active', 'updated_at']);

            $spend = 0.0;
            $clicks = 0.0;
            $sold = 0.0;
            $sales = 0.0;
            $active = 0.0;
            $ssales = 0.0;
            $updatedAt = null;

            foreach ($rows as $r) {
                $ch = (string) ($r->channel ?? '');
                if ($r->updated_at) {
                    $ts = Carbon::parse($r->updated_at);
                    if ($updatedAt === null || $ts->gt($updatedAt)) {
                        $updatedAt = $ts;
                    }
                }

                if ($ch === self::SSALES_CHANNEL) {
                    $ssales = (float) ($r->sales ?? 0);
                    continue;
                }

                // Sub-rows are slices of a parent — skip to avoid double-count.
                if ($ch === '' || str_contains($ch, self::SUBROW_SEPARATOR)) {
                    continue;
                }

                $spend += (float) ($r->spend ?? 0);
                $clicks += (float) ($r->clicks ?? 0);
                $sold += (float) ($r->sold ?? 0);
                $sales += (float) ($r->sales ?? 0);
                $active += (float) ($r->active ?? 0);
            }

            $cvr = $clicks > 0 ? ($sold / $clicks) * 100 : 0.0;
            $acos = $sales > 0
                ? (int) round(($spend / $sales) * 100)
                : ($spend > 0 ? 100 : 0);
            $tcos = $ssales > 0
                ? (int) round(($spend / $ssales) * 100)
                : ($spend > 0 ? 100 : 0);

            return [
                'active' => (int) round($active),
                'spend' => round($spend, 2),
                'clicks' => round($clicks, 0),
                'sold' => round($sold, 0),
                'sales' => round($sales, 2),
                'cvr' => round($cvr, 1),
                'acos' => $acos,
                'tcos' => $tcos,
                'ssales' => round($ssales, 2),
                'snapshot_date' => (string) $latestDate,
                'updated_at' => $updatedAt,
            ];
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master dashboard badges failed: '.$e->getMessage());

            return $empty;
        }
    }

    public function data(
        AmazonAdsController $amazonAds,
        EbayCampaignAdsController $ebayCampaignAds,
        Ebay2CampaignAdsController $ebay2CampaignAds,
        Ebay3CampaignAdsController $ebay3CampaignAds,
        ShopifyAdsMasterController $shopifyAdsMaster
    ) {
        try {
            $amazonNetSales = $this->amazonNetSales();
            $ebayNetSales = EbayCampaignAdsController::advertisementMasterNetSales();
            $ebay2NetSales = Ebay2CampaignAdsController::advertisementMasterNetSales();
            $ebay3NetSales = Ebay3CampaignAdsController::advertisementMasterNetSales();
            $shopifyNetSales = ShopifyAdsMasterController::advertisementMasterNetSales();

            $rows = array_merge(
                $amazonAds->getAdvertisementMasterChannelRows(),
                $ebayCampaignAds->getAdvertisementMasterChannelRows(),
                $ebay2CampaignAds->getAdvertisementMasterChannelRows(),
                $ebay3CampaignAds->getAdvertisementMasterChannelRows(),
                $shopifyAdsMaster->getAdvertisementMasterChannelRows()
            );

            $this->applyTcosToRows($rows, [
                'amazon' => $amazonNetSales,
                'ebay'   => $ebayNetSales,
                'ebay2'  => $ebay2NetSales,
                'ebay3'  => $ebay3NetSales,
                'shopify' => $shopifyNetSales,
            ]);

            $totalNetSales = round(
                $amazonNetSales + $ebayNetSales + $ebay2NetSales + $ebay3NetSales + $shopifyNetSales,
                2
            );

            // Trend dots: compare each metric against the previous Pacific-day
            // snapshot (per channel). Read *before* today's snapshot write so
            // "previous" never means today. Spend + ACOS are inverted on the
            // frontend (a higher value is worse → red).
            $pacificToday  = Carbon::now(self::SNAPSHOT_TIMEZONE)->toDateString();
            $prevByChannel = $this->previousSnapshotByChannel($pacificToday);
            $this->attachTrends($rows, $prevByChannel);

            // Persist today's snapshot so the trend dots + badge charts have
            // history. The snapshot table is flat (one row per channel), so
            // flatten the tree first, and store combined S Sales as its own
            // pseudo-channel so the TCOS / S SALES badges get a trend too. Never
            // let a snapshot write break the data feed.
            try {
                $this->snapshotChannels($this->flattenRows($rows), $totalNetSales);
            } catch (\Throwable $e) {
                \Log::warning('Advertisement Master snapshot failed: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 200,
                'message' => 'Advertisement Master data fetched successfully',
                'data' => $rows,
                'amazon_net_sales' => $amazonNetSales,
                'ebay_net_sales' => $ebayNetSales,
                'ebay2_net_sales' => $ebay2NetSales,
                'ebay3_net_sales' => $ebay3NetSales,
                'shopify_net_sales' => $shopifyNetSales,
                'total_net_sales' => $totalNetSales,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Advertisement Master data failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Failed to load Advertisement Master data',
                'data' => [],
                'amazon_net_sales' => 0,
                'ebay_net_sales' => 0,
                'ebay2_net_sales' => 0,
                'ebay3_net_sales' => 0,
                'shopify_net_sales' => 0,
                'total_net_sales' => 0,
            ], 500);
        }
    }

    /**
     * Amazon L30 store sales (Pacific rolling window) — same source as Channel Master
     * and the Amazon daily sales page. Used for the S Sales badge and TCOS.
     */
    private function amazonNetSales(): float
    {
        try {
            $yesterdayPacific = Carbon::yesterday('America/Los_Angeles');
            $endToday = $yesterdayPacific->copy()->endOfDay();
            $startAmazonWindow = $yesterdayPacific
                ->copy()
                ->subDays(AmazonSalesController::DAILY_SALES_WINDOW_DAYS - 1)
                ->startOfDay();

            return AmazonOrder::badgeTotalSalesByOrderDate($startAmazonWindow, $endToday);
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master Amazon net sales lookup failed: ' . $e->getMessage());

            return 0.0;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, float>  $netSalesByMarketplace
     */
    private function applyTcosToRows(array &$rows, array $netSalesByMarketplace): void
    {
        foreach ($rows as &$row) {
            $marketplace = (string) ($row['marketplace'] ?? 'amazon');
            $netSales = (float) ($netSalesByMarketplace[$marketplace] ?? 0);
            $spend = (float) ($row['spend'] ?? 0);
            $row['tcos'] = $netSales > 0
                ? round(($spend / $netSales) * 100, 0)
                : ($spend > 0 ? 100 : 0);

            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->applyTcosToRows($row['_children'], $netSalesByMarketplace);
            }
        }
        unset($row);
    }

    /**
     * Flatten a nested `_children` tree into a single list (parents first,
     * then their children) so snapshotting can persist one row per channel.
     * The `_children` and `trend` keys are stripped from each emitted row.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function flattenRows(array $rows): array
    {
        $flat = [];
        foreach ($rows as $row) {
            $children = $row['_children'] ?? [];
            unset($row['_children'], $row['trend']);
            $flat[] = $row;
            if (! empty($children) && is_array($children)) {
                $flat = array_merge($flat, $this->flattenRows($children));
            }
        }

        return $flat;
    }

    /**
     * Save (upsert) every channel row into the history table for the current
     * Pacific (PDT/PST) business day. One row per (snapshot_date, channel),
     * so repeated page loads within the same Pacific day refresh the value
     * rather than piling up duplicates.
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

        // Combined S Sales kept as its own pseudo-channel row (the net-sales
        // figure lives in the `sales` column). Powers the TCOS / S SALES badge
        // trends; excluded from the channel totals in history().
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
        $query = DB::table('advertisement_master_metric_snapshots')
            ->where('snapshot_date', $date)
            ->where('channel', $channel);

        if ($query->exists()) {
            (clone $query)->update(array_merge($measures, ['updated_at' => $now]));

            return;
        }

        $payload = array_merge($measures, [
            'snapshot_date' => $date,
            'channel'       => $channel,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        // Local/prod tables were created with a PK `id` and no AUTO_INCREMENT.
        // Assign the next id so the insert cannot fail with
        // "Field 'id' doesn't have a default value".
        $maxId = DB::table('advertisement_master_metric_snapshots')->max('id');
        $payload['id'] = ((int) $maxId) + 1;

        DB::table('advertisement_master_metric_snapshots')->insert($payload);
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
        $rows = DB::table('advertisement_master_metric_snapshots')
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

    /**
     * Badge/cell trend history. Returns a per-day time series for each metric
     * (spend / clicks / sold / sales / cvr / acos) rolled up across the
     * top-level channels, plus the same broken out per channel so the chart
     * can lens to the total or a single channel. Mirrors the
     * /shopify-ads-master history endpoint.
     *
     *   GET /advertisement-master/history?days=32
     */
    public function history(Request $request)
    {
        $days = max(1, min(365, (int) $request->query('days', 32)));
        // Anchor to the Pacific business day so it lines up with the snapshots.
        $from = Carbon::now(self::SNAPSHOT_TIMEZONE)->subDays($days - 1)->toDateString();

        $rows = DB::table('advertisement_master_metric_snapshots')
            ->where('snapshot_date', '>=', $from)
            ->orderBy('snapshot_date')
            ->get(['snapshot_date', 'channel', 'spend', 'clicks', 'sold', 'sales', 'active']);

        $byDate       = [];   // date => rolled-up totals (top-level parents only)
        $byChannel    = [];   // channel => date => measures
        $ssalesByDate = [];   // date => combined S Sales (net sales)
        $allDates     = [];
        foreach ($rows as $r) {
            $d  = (string) $r->snapshot_date;
            $ch = (string) $r->channel;
            $allDates[$d] = true;

            // Combined S Sales pseudo-channel: kept aside for the TCOS /
            // S SALES badges, never rolled into the channel totals.
            if ($ch === self::SSALES_CHANNEL) {
                $ssalesByDate[$d] = (float) $r->sales;
                continue;
            }

            $byChannel[$ch][$d] = [
                'spend'  => (float) $r->spend,
                'clicks' => (float) $r->clicks,
                'sold'   => (float) $r->sold,
                'sales'  => (float) $r->sales,
                'active' => (float) ($r->active ?? 0),
            ];

            // Sub-rows (contain the separator) are slices of a parent — skip
            // them from the grand total so parents aren't double-counted.
            if (str_contains($ch, self::SUBROW_SEPARATOR)) {
                continue;
            }

            $byDate[$d] ??= ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0, 'active' => 0.0];
            $byDate[$d]['spend']  += (float) $r->spend;
            $byDate[$d]['clicks'] += (float) $r->clicks;
            $byDate[$d]['sold']   += (float) $r->sold;
            $byDate[$d]['sales']  += (float) $r->sales;
            $byDate[$d]['active'] += (float) ($r->active ?? 0);
        }

        $labels = array_keys($allDates);
        sort($labels);
        foreach ($labels as $d) {
            $byDate[$d] ??= ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0, 'active' => 0.0];
        }

        // Rolled-up "All channels" series carries tcos + ssales (both need the
        // store-level net sales). Per-channel series get tcos too, lensed to
        // that channel's spend against the same store S Sales.
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
     * @param  array<string, array<string, float>>  $byDate
     * @param  array<int, string>  $labels
     * @param  array<string, float>  $ssalesByDate  date => combined S Sales (for TCOS)
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
            // TCOS = Spend / S Sales (combined store net sales).
            $ss = $ssalesByDate[$d] ?? 0;
            $series['tcos'][]   = $ss > 0
                ? round(($m['spend'] / $ss) * 100, 0)
                : ($m['spend'] > 0 ? 100 : 0);
        }

        return $series;
    }

    /**
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     * @param  array<int, string>  $labels
     * @param  array<string, float>  $ssalesByDate
     * @return array<string, array<string, array<int, float>>>
     */
    private function buildChannelSeries(array $byChannel, array $labels, array $ssalesByDate = []): array
    {
        $out = [];
        foreach ($byChannel as $channel => $perDay) {
            $bd = [];
            foreach ($labels as $d) {
                $bd[$d] = $perDay[$d] ?? ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0, 'active' => 0.0];
            }
            $out[$channel] = $this->buildMetricSeries($bd, $labels, $ssalesByDate);
        }

        return $out;
    }
}
