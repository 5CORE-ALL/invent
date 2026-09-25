<?php

namespace App\Http\Controllers\AdvertisementMaster;

use App\Http\Controllers\AmazonAdsController;
use App\Http\Controllers\AmazonAdsMissingController;
use App\Http\Controllers\Campaigns\Ebay2CampaignAdsController;
use App\Http\Controllers\Campaigns\Ebay3CampaignAdsController;
use App\Http\Controllers\Campaigns\EbayCampaignAdsController;
use App\Http\Controllers\Campaigns\GoogleSerpAdsMissingController;
use App\Http\Controllers\Campaigns\GoogleShoppingAdsMissingController;
use App\Http\Controllers\Campaigns\GoogleYoutubeAdsMissingController;
use App\Http\Controllers\Campaigns\Temu1MissingAdsController;
use App\Http\Controllers\Campaigns\Temu2AdsController;
use App\Http\Controllers\Campaigns\Temu2MissingAdsController;
use App\Http\Controllers\Campaigns\TemuAdsController;
use App\Http\Controllers\Campaigns\Tiktok1AdsRawDataController;
use App\Http\Controllers\Campaigns\TiktokAdsMissingController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Channels\ChannelMasterController;
use App\Http\Controllers\MarketPlace\ShopifyAdsMasterController;
use App\Http\Controllers\Sales\AmazonSalesController;
use App\Support\Badges\AllMarketplaceMasterBadgeAggregator;
use App\Models\AdvertisementMasterChannelLabel;
use App\Models\BadgeData;
use App\Models\BadgeDataHistory;
use App\Models\AdvertisementMasterCustomRow;
use App\Models\AdvertisementMasterHiddenRow;
use App\Models\AdvertisementMasterNrReq;
use App\Models\AmazonOrder;
use App\Models\ChannelMaster;
use App\Models\ChannelMasterSummary;
use App\Models\MarketplaceDailyMetric;
use App\Models\ChannelMasterCalculatedData;
use App\Support\AmazonAdsAdvertisementMasterHistory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

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

    /** Daily Active Channel spend, one row per Pacific day. */
    private const ACTIVE_SPEND_CHANNEL = '__aspend__';

    /** Daily Active Channel listing clicks (total views), one row per Pacific day. */
    private const ACTIVE_CLICKS_CHANNEL = '__aclicks__';

    /** @var list<array<string, mixed>>|null */
    private ?array $activeChannelGridRows = null;

    public function index(Request $request)
    {
        return view('advertisement-master.advertisement_master', [
            'mode' => $request->query('mode'),
            'demo' => $request->query('demo'),
            'activeChannels' => $this->activeChannelNames(),
        ]);
    }

    /**
     * Distinct channel names from Channel Master rows whose status is active.
     *
     * @return list<string>
     */
    private function activeChannelNames(): array
    {
        if (! Schema::hasTable('channel_master')) {
            return [];
        }

        return ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(COALESCE(status, ""))) = ?', ['active'])
            ->whereNotNull('channel')
            ->orderBy('channel')
            ->pluck('channel')
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '')
            ->unique(fn ($name) => mb_strtolower($name))
            ->values()
            ->all();
    }

    /**
     * Combined store sales for the TOTAL SALES badge — same L30 Sales total as
     * Active Channel / All Marketplace Master (active channels only).
     */
    private function activeChannelL30SalesTotal(): float
    {
        try {
            if (Schema::hasTable('badges_data')) {
                $cached = BadgeData::dataForPage('all-marketplace-master');
                $sales = (float) ($cached['l30_sales'] ?? 0);
                if ($sales > 0) {
                    return round($sales, 2);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master active-channel sales badge lookup failed: '.$e->getMessage());
        }

        if (! Schema::hasTable('channel_master') || ! Schema::hasTable('channel_master_calculated_data')) {
            return 0.0;
        }

        $active = [];
        foreach (ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(COALESCE(status, ""))) = ?', ['active'])
            ->pluck('channel') as $name) {
            $key = $this->normalizeChannelMatchKey((string) $name);
            if ($key !== '') {
                $active[$key] = true;
            }
        }

        $sum = 0.0;
        $seen = [];
        try {
            foreach (ChannelMasterCalculatedData::query()->get(['channel', 'l30_sales']) as $row) {
                $key = $this->normalizeChannelMatchKey((string) ($row->channel ?? ''));
                if ($key === '' || ! isset($active[$key]) || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $sales = (float) ($row->l30_sales ?? 0);
                if ($key === 'amazon') {
                    $live = $this->amazonNetSales();
                    if ($live > 0) {
                        $sales = $live;
                    }
                }
                $sum += $sales;
            }
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master active-channel sales sum failed: '.$e->getMessage());

            return 0.0;
        }

        return round($sum, 2);
    }

    /**
     * Combined ad spend for the SPEND badge — same Total Ad Spend as
     * Active Channel / All Marketplace Master (active channels only).
     */
    private function activeChannelAdSpendTotal(): float
    {
        try {
            if (Schema::hasTable('badges_data')) {
                $cached = BadgeData::dataForPage('all-marketplace-master');
                if (array_key_exists('ad_spend', $cached)) {
                    return round((float) $cached['ad_spend'], 2);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master active-channel spend badge lookup failed: '.$e->getMessage());
        }

        if (! Schema::hasTable('channel_master') || ! Schema::hasTable('channel_master_calculated_data')) {
            return 0.0;
        }

        $active = [];
        foreach (ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(COALESCE(status, ""))) = ?', ['active'])
            ->pluck('channel') as $name) {
            $key = $this->normalizeChannelMatchKey((string) $name);
            if ($key !== '') {
                $active[$key] = true;
            }
        }

        $sum = 0.0;
        $seen = [];
        try {
            foreach (ChannelMasterCalculatedData::query()->get(['channel', 'total_ad_spend', 'l30_sales', 'ads_percentage', 'tacos_percentage']) as $row) {
                $key = $this->normalizeChannelMatchKey((string) ($row->channel ?? ''));
                if ($key === '' || ! isset($active[$key]) || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $spend = (float) ($row->total_ad_spend ?? 0);
                if ($spend <= 0 && str_contains($key, 'reverb')) {
                    $pct = (float) ($row->ads_percentage ?? 0);
                    if ($pct <= 0) {
                        $pct = (float) ($row->tacos_percentage ?? 0);
                    }
                    $l30 = (float) ($row->l30_sales ?? 0);
                    if ($pct > 0 && $l30 > 0) {
                        $spend = ($pct / 100) * $l30;
                    }
                }
                $sum += $spend;
            }
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master active-channel spend sum failed: '.$e->getMessage());

            return 0.0;
        }

        return round($sum, 2);
    }

    /**
     * Ad badges on Active Channel, taken from the same live grid that page sums.
     *
     * @return array{values: array<string, float|null>, trends: array<string, string>}
     */
    private function activeChannelAdBadgePack(): array
    {
        $empty = [
            'values' => ['spend' => null, 'tcos' => null, 'acos' => null, 'clicks' => null, 'cvr' => null],
            'trends' => ['spend' => 'flat', 'tcos' => 'flat', 'acos' => 'flat', 'clicks' => 'flat', 'cvr' => 'flat'],
        ];
        $rows = $this->activeChannelGridRows();
        if ($rows === []) {
            return $empty;
        }

        $agg = AllMarketplaceMasterBadgeAggregator::aggregate($rows);
        $spend = round((float) ($agg['ad_spend'] ?? 0), 2);
        $l30 = (float) ($agg['l30_sales'] ?? 0);
        $tcos = $l30 > 0 ? round(($spend / $l30) * 100, 1) : 0.0;
        $adSales = 0.0;
        foreach ($rows as $row) {
            $adSales += $this->gridNumber($row, 'Ad Sales');
        }
        $acos = $adSales > 0 ? round(($spend / $adSales) * 100, 1) : ($spend > 0 ? 100.0 : 0.0);
        $clicks = (float) round((float) ($agg['total_views'] ?? 0));
        $cvr = $this->activeChannelListingCvr($rows);

        $prior = $this->activeChannelPriorDayTotals();
        $priorSpend = $prior['ad_spend'] ?? null;
        $priorSales = $prior['l30_sales'] ?? null;
        $priorAdSales = $prior['ad_sales'] ?? null;
        $priorTcos = ($priorSpend !== null && $priorSales !== null && $priorSales > 0)
            ? ($priorSpend / $priorSales) * 100
            : null;
        $priorAcos = ($priorSpend !== null && $priorAdSales !== null && $priorAdSales > 0)
            ? ($priorSpend / $priorAdSales) * 100
            : null;

        return [
            'values' => [
                'spend' => $spend,
                'tcos' => $tcos,
                'acos' => $acos,
                'clicks' => $clicks,
                'cvr' => $cvr,
            ],
            'trends' => [
                'spend' => $this->liveVsPriorDirection($spend, $priorSpend),
                'tcos' => $this->liveVsPriorDirection($tcos, $priorTcos),
                'acos' => $this->liveVsPriorDirection($acos, $priorAcos),
                'clicks' => $this->liveVsPriorDirection($clicks, $prior['clicks'] ?? null),
                'cvr' => $this->liveVsPriorDirection($cvr, $this->priorListingCvr($prior), 0.005),
            ],
        ];
    }

    /**
     * Same rows Active Channel draws in its header badges.
     *
     * @return list<array<string, mixed>>
     */
    private function activeChannelGridRows(): array
    {
        if ($this->activeChannelGridRows !== null) {
            return $this->activeChannelGridRows;
        }

        try {
            $payload = app(ChannelMasterController::class)->getAllMarketplaceMasterChannelPayload();
            $rows = $payload['data'] ?? [];
            $this->activeChannelGridRows = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master active-channel grid failed: '.$e->getMessage());
            $this->activeChannelGridRows = [];
        }

        return $this->activeChannelGridRows;
    }

    /**
     * Listing CVR badge: Amazon and Reverb use Qty (else orders) ÷ views.
     * Every other channel uses its saved listing CVR × views.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function activeChannelListingCvr(array $rows): ?float
    {
        $units = 0.0;
        $viewsSum = 0.0;
        foreach ($rows as $row) {
            $views = $this->gridNumber($row, 'Total Views');
            if ($views <= 0) {
                continue;
            }
            $key = strtolower((string) preg_replace('/[^a-z0-9]/', '', (string) ($row['Channel '] ?? $row['Channel'] ?? '')));
            if ($key === 'amazon' || $key === 'reverb') {
                $qty = $this->gridNumber($row, 'Qty');
                if ($qty <= 0) {
                    $qty = $this->gridNumber($row, 'L30 Orders');
                }
                $units += $qty;
                $viewsSum += $views;
                continue;
            }
            if (array_key_exists('CVR', $row) && $row['CVR'] !== null && $row['CVR'] !== '') {
                $units += ($this->gridNumber($row, 'CVR') / 100) * $views;
                $viewsSum += $views;
            } else {
                $units += $this->gridNumber($row, 'Qty');
                $viewsSum += $views;
            }
        }

        return $viewsSum > 0 ? round(($units / $viewsSum) * 100, 2) : null;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array{units: float, views: float}|null
     */
    private function summaryListingCvrPart(string $channelKey, array $summary): ?array
    {
        $views = (float) ($summary['total_views'] ?? 0);
        if ($views <= 0) {
            return null;
        }
        if ($channelKey === 'amazon' || $channelKey === 'reverb') {
            $qty = (float) ($summary['total_quantity'] ?? 0);
            if ($qty <= 0) {
                $qty = (float) ($summary['l30_orders'] ?? 0);
            }

            return ['units' => $qty, 'views' => $views];
        }
        $cvr = $summary['listing_cvr'] ?? null;
        if ($cvr === null || $cvr === '') {
            $cvr = $summary['cvr_percent'] ?? null;
        }
        if ($cvr !== null && $cvr !== '' && is_numeric($cvr)) {
            return ['units' => ((float) $cvr / 100) * $views, 'views' => $views];
        }
        $qty = (float) ($summary['total_quantity'] ?? 0);
        if ($qty <= 0) {
            $qty = (float) ($summary['l30_orders'] ?? 0);
        }

        return ['units' => $qty, 'views' => $views];
    }

    /**
     * @param  array<string, float>|null  $prior
     */
    private function priorListingCvr(?array $prior): ?float
    {
        if ($prior === null) {
            return null;
        }
        $views = (float) ($prior['cvr_views'] ?? 0);
        if ($views <= 0) {
            return null;
        }

        return round(((float) ($prior['cvr_units'] ?? 0) / $views) * 100, 2);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function gridNumber(array $row, string $key): float
    {
        if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
            return 0.0;
        }
        $cleaned = preg_replace('/[^0-9.-]/', '', (string) $row[$key]);
        if ($cleaned === '' || $cleaned === '-' || ! is_numeric($cleaned)) {
            return 0.0;
        }

        return (float) $cleaned;
    }

    /**
     * Latest saved Active Channel day before today, used only for the badge dot.
     *
     * @return array{l30_sales: float, ad_spend: float, clicks: float, ad_sales: float}|null
     */
    private function activeChannelPriorDayTotals(): ?array
    {
        $today = Carbon::now(self::SNAPSHOT_TIMEZONE)->startOfDay();
        $totals = $this->activeChannelDailyTotals(
            $today->copy()->subDays(3)->toDateString(),
            $today->toDateString()
        );
        unset($totals[$today->toDateString()]);
        if ($totals === []) {
            return null;
        }

        $last = end($totals);

        return is_array($last) ? $last : null;
    }

    private function liveVsPriorDirection(?float $live, ?float $prior, float $epsilon = 0.01): string
    {
        if ($live === null || $prior === null || abs($live - $prior) < $epsilon) {
            return 'flat';
        }

        return $live > $prior ? 'up' : 'down';
    }

    private function badgeHistoryDirection(string $field): string
    {
        if (! Schema::hasTable('badges_data_histories')) {
            return 'flat';
        }

        try {
            $values = BadgeDataHistory::query()
                ->where('page_name', 'all-marketplace-master')
                ->where('field', $field)
                ->orderByDesc('snapshot_date')
                ->limit(2)
                ->pluck('value');
        } catch (\Throwable $e) {
            return 'flat';
        }

        if ($values->count() < 2) {
            return 'flat';
        }
        $last = (float) $values[0];
        $prev = (float) $values[1];
        if (abs($last - $prev) < 0.01) {
            return 'flat';
        }

        return $last > $prev ? 'up' : 'down';
    }

    /**
     * ACOS column total on Active Channel: Total Ad Spend ÷ Ad Sales.
     */
    private function activeChannelAcos(?float $spend): ?float
    {
        $adSales = $this->activeChannelAdSalesTotal();
        if ($spend === null && $adSales <= 0) {
            return null;
        }
        $spend = $spend ?? 0.0;
        if ($adSales <= 0) {
            return $spend > 0 ? 100.0 : 0.0;
        }

        return round(($spend / $adSales) * 100, 1);
    }

    private function activeChannelAdSalesTotal(): float
    {
        if (! Schema::hasTable('channel_master') || ! Schema::hasTable('channel_master_calculated_data')) {
            return 0.0;
        }

        $active = [];
        foreach (ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(COALESCE(status, ""))) = ?', ['active'])
            ->pluck('channel') as $name) {
            $key = $this->normalizeChannelMatchKey((string) $name);
            if ($key !== '') {
                $active[$key] = true;
            }
        }

        $sum = 0.0;
        $seen = [];
        try {
            foreach (ChannelMasterCalculatedData::query()->get(['channel', 'ad_sales']) as $row) {
                $key = $this->normalizeChannelMatchKey((string) ($row->channel ?? ''));
                if ($key === '' || ! isset($active[$key]) || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $sum += (float) ($row->ad_sales ?? 0);
            }
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master active-channel ad sales sum failed: '.$e->getMessage());

            return 0.0;
        }

        return round($sum, 2);
    }

    private function activeChannelAcosDirection(): string
    {
        $today = Carbon::now(self::SNAPSHOT_TIMEZONE)->startOfDay();
        $totals = $this->activeChannelDailyTotals(
            $today->copy()->subDay()->toDateString(),
            $today->toDateString()
        );
        $pct = [];
        foreach ($totals as $metric) {
            $sales = (float) ($metric['ad_sales'] ?? 0);
            $spend = (float) ($metric['ad_spend'] ?? 0);
            if ($sales <= 0 && $spend <= 0) {
                continue;
            }
            $pct[] = $sales > 0 ? ($spend / $sales) * 100 : ($spend > 0 ? 100.0 : 0.0);
        }
        if (count($pct) < 2 || abs($pct[1] - $pct[0]) < 0.01) {
            return 'flat';
        }

        return $pct[1] > $pct[0] ? 'up' : 'down';
    }

    /**
     * @return array{0: list<string>, 1: list<float|null>}
     */
    private function activeChannelAcosHistory(int $days): array
    {
        $today = Carbon::now(self::SNAPSHOT_TIMEZONE)->startOfDay();
        $from = $today->copy()->subDays($days - 1);
        $totals = $this->activeChannelDailyTotals($from->toDateString(), $today->toDateString());
        $dates = [];
        $cursor = $from->copy();
        while ($cursor->lte($today)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        $series = [];
        foreach ($dates as $date) {
            if (! isset($totals[$date])) {
                $series[] = null;
                continue;
            }
            $sales = (float) ($totals[$date]['ad_sales'] ?? 0);
            $spend = (float) ($totals[$date]['ad_spend'] ?? 0);
            if ($sales <= 0 && $spend <= 0) {
                $series[] = null;
                continue;
            }
            $series[] = $sales > 0 ? round(($spend / $sales) * 100, 1) : ($spend > 0 ? 100.0 : 0.0);
        }

        $todayIdx = array_search($today->toDateString(), $dates, true);
        if ($todayIdx !== false) {
            $liveSpend = null;
            try {
                $cached = BadgeData::dataForPage('all-marketplace-master');
                if (array_key_exists('ad_spend', $cached) && is_numeric($cached['ad_spend'])) {
                    $liveSpend = (float) $cached['ad_spend'];
                }
            } catch (\Throwable $e) {
                $liveSpend = null;
            }
            $live = $this->activeChannelAcos($liveSpend);
            if ($live !== null) {
                $series[$todayIdx] = $live;
            }
        }

        return [
            array_map(fn ($d) => date('M d', strtotime($d)), $dates),
            $series,
        ];
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
                if ($ch === self::ACTIVE_SPEND_CHANNEL || $ch === self::ACTIVE_CLICKS_CHANNEL) {
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
            $rowSpend = $spend;
            $acos = $sales > 0
                ? (int) round(($rowSpend / $sales) * 100)
                : ($rowSpend > 0 ? 100 : 0);
            $page = new static;
            $today = Carbon::now(self::SNAPSHOT_TIMEZONE)->toDateString();
            $page->persistActiveChannelDaily(
                Carbon::now(self::SNAPSHOT_TIMEZONE)->subDays(40)->toDateString(),
                $today
            );
            $savedSales = $page->savedMetricEnd(self::SSALES_CHANNEL, 'sales');
            $savedSpend = $page->savedMetricEnd(self::ACTIVE_SPEND_CHANNEL, 'spend');
            $activeChannelSales = ($savedSales['last'] !== null && $savedSales['last'] > 0)
                ? $savedSales['last']
                : $page->activeChannelL30SalesTotal();
            if ($activeChannelSales > 0) {
                $ssales = $activeChannelSales;
            }
            $activeChannelSpend = ($savedSpend['last'] !== null && $savedSpend['last'] > 0)
                ? $savedSpend['last']
                : $page->activeChannelAdSpendTotal();
            if ($activeChannelSpend > 0) {
                $spend = $activeChannelSpend;
            }
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
        ShopifyAdsMasterController $shopifyAdsMaster,
        Tiktok1AdsRawDataController $tiktok1Ads,
        TemuAdsController $temuAds,
        Temu2AdsController $temu2Ads
    ) {
        try {
            $amazonNetSales = $this->amazonNetSales();
            $ebayNetSales = EbayCampaignAdsController::advertisementMasterNetSales();
            $ebay2NetSales = Ebay2CampaignAdsController::advertisementMasterNetSales();
            $ebay3NetSales = Ebay3CampaignAdsController::advertisementMasterNetSales();
            $shopifyNetSales = ShopifyAdsMasterController::advertisementMasterNetSales();
            $tiktokNetSales = Tiktok1AdsRawDataController::advertisementMasterNetSales();
            $temuNetSales = TemuAdsController::advertisementMasterNetSales();
            $temu2NetSales = Temu2AdsController::advertisementMasterNetSales();

            $rows = array_merge(
                $amazonAds->getAdvertisementMasterChannelRows(),
                $ebayCampaignAds->getAdvertisementMasterChannelRows(),
                $ebay2CampaignAds->getAdvertisementMasterChannelRows(),
                $ebay3CampaignAds->getAdvertisementMasterChannelRows(),
                $shopifyAdsMaster->getAdvertisementMasterChannelRows(),
                $tiktok1Ads->getAdvertisementMasterChannelRows(),
                $temuAds->getAdvertisementMasterChannelRows(),
                $temu2Ads->getAdvertisementMasterChannelRows()
            );

            $this->applyTcosToRows($rows, [
                'amazon' => $amazonNetSales,
                'ebay'   => $ebayNetSales,
                'ebay2'  => $ebay2NetSales,
                'ebay3'  => $ebay3NetSales,
                'shopify' => $shopifyNetSales,
                'tiktok' => $tiktokNetSales,
                'temu' => $temuNetSales,
                'temu2' => $temu2NetSales,
            ]);

            $pacificToday = Carbon::now(self::SNAPSHOT_TIMEZONE)->toDateString();
            $this->persistActiveChannelDaily(
                Carbon::parse($pacificToday, self::SNAPSHOT_TIMEZONE)->subDays(40)->toDateString(),
                $pacificToday
            );
            $savedSales = $this->savedMetricEnd(self::SSALES_CHANNEL, 'sales');
            $savedSpend = $this->savedMetricEnd(self::ACTIVE_SPEND_CHANNEL, 'spend');
            $savedClicks = $this->savedMetricEnd(self::ACTIVE_CLICKS_CHANNEL, 'clicks');
            $chartSales = $savedSales['last'];
            $chartSpend = $savedSpend['last'];
            $chartClicks = $savedClicks['last'];
            $adBadges = $this->activeChannelAdBadgePack();
            $badgeTrends = [
                'ssales' => $savedSales['dir'],
                'spend' => $adBadges['trends']['spend'],
                'clicks' => $adBadges['trends']['clicks'],
                'tcos' => $adBadges['trends']['tcos'],
                'cvr' => $adBadges['trends']['cvr'],
                'acos' => $adBadges['trends']['acos'],
            ];
            $liveSpend = $adBadges['values']['spend'];
            $activeChannelSpend = $liveSpend !== null
                ? (float) $liveSpend
                : $this->activeChannelAdSpendTotal();
            $totalNetSales = ($chartSales !== null && $chartSales > 0)
                ? $chartSales
                : $this->activeChannelL30SalesTotal();
            if ($totalNetSales <= 0) {
                $totalNetSales = round(
                    $amazonNetSales + $ebayNetSales + $ebay2NetSales + $ebay3NetSales + $shopifyNetSales + $tiktokNetSales + $temuNetSales + $temu2NetSales,
                    2
                );
            }

            // Trend dots: compare each metric against the previous Pacific-day
            // snapshot (per channel). Read *before* today's snapshot write so
            // "previous" never means today. Spend + ACOS are inverted on the
            // frontend (a higher value is worse → red). New channels (e.g.
            // TikTok 1) get a seeded prior day so dots + history show on day one.
            $pacificToday  = Carbon::now(self::SNAPSHOT_TIMEZONE)->toDateString();
            $this->attachMissingAds($rows);
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

            // Display-name overrides (Group / Channel) applied after snapshot so
            // history + links keep the original channel_key.
            $this->revertStandaloneTypeTotalLabels();
            $this->applyChannelLabels($rows);
            $this->wrapStandaloneChannelTotals($rows);
            $this->ensureSumRowTotalSuffix($rows);
            $this->attachMissingChannelMasterTotals($rows);
            $this->attachCustomRows($rows);
            $this->ensureDefaultTypeRows($rows);
            $this->persistMissingTypeRowLabels($rows);
            $this->removeHiddenRows($rows);
            $this->attachChannelHrefs($rows);
            $this->attachNrReqs($rows);
            $this->attachViews($rows);
            $this->attachTSales($rows);
            $this->clearTypeRowChannelMetrics($rows);
            $this->attachTotalRowAcos($rows, $prevByChannel);
            $this->attachMissingAds($rows);
            $this->attachMissingAdsTrends($rows, $prevByChannel);
            $this->applyActiveChannelAds($rows);
            try {
                $this->snapshotChannels($this->flattenRows($rows), $totalNetSales);
                $this->snapshotMissingAds($this->flattenRows($rows));
            } catch (\Throwable $e) {
                \Log::warning('Advertisement Master missing-ads snapshot failed: '.$e->getMessage());
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
                'tiktok_net_sales' => $tiktokNetSales,
                'temu_net_sales' => $temuNetSales,
                'temu2_net_sales' => $temu2NetSales,
                'total_net_sales' => $totalNetSales,
                'active_channel_spend' => $activeChannelSpend,
                'active_channel_clicks' => $chartClicks,
                'active_channel_badges' => $adBadges['values'],
                'badge_trends' => $badgeTrends,
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
                'tiktok_net_sales' => 0,
                'temu_net_sales' => 0,
                'temu2_net_sales' => 0,
                'total_net_sales' => 0,
                'active_channel_spend' => 0,
            ], 500);
        }
    }

    /**
     * Persist Channel + Type. Existing source rows store a display-name override
     * (channel_key stays the original name). "+" rows are stored as custom rows.
     */
    public function saveLabel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_key' => 'nullable|string|max:191',
            'group_name' => 'required|string|max:80',
            'channel_name' => 'required|string|max:191',
        ]);

        $key = trim((string) ($data['channel_key'] ?? ''));
        $group = trim($data['group_name']);
        $channel = trim($data['channel_name']);
        if ($group === '' || $channel === '') {
            return response()->json([
                'status' => 422,
                'message' => 'Channel and Type are required.',
            ], 422);
        }

        if ($key === '' || str_starts_with($key, 'custom:')) {
            return $this->saveCustomRow($key, $group, $channel);
        }

        if (! Schema::hasTable('advertisement_master_channel_labels')) {
            return response()->json([
                'status' => 503,
                'message' => 'Channel labels table is missing. Run migrations and try again.',
            ], 503);
        }

        AdvertisementMasterChannelLabel::query()->updateOrCreate(
            ['channel_key' => $key],
            [
                'group_name' => $group,
                'channel_name' => $channel,
            ]
        );

        return response()->json([
            'status' => 200,
            'message' => 'Saved.',
            'channel_key' => $key,
            'group_name' => $group,
            'channel_name' => $channel,
            'is_custom' => false,
        ]);
    }

    private function saveCustomRow(string $key, string $channelName, string $typeName): JsonResponse
    {
        if (! Schema::hasTable('advertisement_master_custom_rows')) {
            return response()->json([
                'status' => 503,
                'message' => 'Custom rows table is missing. Run migrations and try again.',
            ], 503);
        }

        $id = 0;
        if (str_starts_with($key, 'custom:')) {
            $id = (int) substr($key, 7);
        }

        $row = $id > 0
            ? AdvertisementMasterCustomRow::query()->find($id)
            : null;

        if ($row) {
            $row->fill([
                'channel_name' => $channelName,
                'type_name' => $typeName,
            ])->save();
        } else {
            $row = AdvertisementMasterCustomRow::query()->create([
                'channel_name' => $channelName,
                'type_name' => $typeName,
            ]);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Saved.',
            'channel_key' => 'custom:'.$row->id,
            'group_name' => $row->channel_name,
            'channel_name' => $row->type_name,
            'is_custom' => true,
        ]);
    }

    /**
     * Hide a source row (or delete a "+" custom row). Children are hoisted
     * so nested types stay visible.
     */
    public function deleteRow(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_key' => 'required|string|max:191',
        ]);
        $key = trim($data['channel_key']);
        if ($key === '') {
            return response()->json([
                'status' => 422,
                'message' => 'Missing row.',
            ], 422);
        }

        if (str_starts_with($key, 'custom:')) {
            if (Schema::hasTable('advertisement_master_custom_rows')) {
                $id = (int) substr($key, 7);
                if ($id > 0) {
                    AdvertisementMasterCustomRow::query()->where('id', $id)->delete();
                }
            }

            return response()->json([
                'status' => 200,
                'message' => 'Deleted.',
                'channel_key' => $key,
            ]);
        }

        if (! Schema::hasTable('advertisement_master_hidden_rows')) {
            return response()->json([
                'status' => 503,
                'message' => 'Hidden rows table is missing. Run migrations and try again.',
            ], 503);
        }

        AdvertisementMasterHiddenRow::query()->updateOrCreate(
            ['channel_key' => $key],
            ['channel_key' => $key]
        );

        return response()->json([
            'status' => 200,
            'message' => 'Deleted.',
            'channel_key' => $key,
        ]);
    }

    /**
     * Persist R/N (REQ = green, NR = red) for a Type row.
     */
    public function saveNrReq(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_key' => 'required|string|max:191',
            'nr_req' => 'required|string|in:REQ,NR',
        ]);
        $key = trim((string) $data['channel_key']);
        $nr = strtoupper(trim((string) $data['nr_req'])) === 'NR' ? 'NR' : 'REQ';
        if ($key === '') {
            return response()->json([
                'status' => 422,
                'message' => 'Missing row.',
            ], 422);
        }

        if (! Schema::hasTable('advertisement_master_nr_reqs')) {
            return response()->json([
                'status' => 503,
                'message' => 'R/N table is missing. Run migrations and try again.',
            ], 503);
        }

        AdvertisementMasterNrReq::query()->updateOrCreate(
            ['channel_key' => $key],
            ['nr_req' => $nr]
        );

        return response()->json([
            'status' => 200,
            'message' => 'Saved.',
            'channel_key' => $key,
            'nr_req' => $nr,
        ]);
    }

    /**
     * Overlay saved Group / Channel display names onto the live tree.
     * Leaves channel_key as the original name.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function applyChannelLabels(array &$rows): void
    {
        $labels = $this->channelLabelMap();
        $this->applyChannelLabelsWalk($rows, $labels);
    }

    /** @var array<string, array{group_name: string, channel_name: string}>|null */
    private ?array $channelLabelMapCache = null;

    /**
     * @return array<string, array{group_name: string, channel_name: string}>
     */
    private function channelLabelMap(): array
    {
        if ($this->channelLabelMapCache !== null) {
            return $this->channelLabelMapCache;
        }

        if (! Schema::hasTable('advertisement_master_channel_labels')) {
            return $this->channelLabelMapCache = [];
        }

        $map = [];
        foreach (AdvertisementMasterChannelLabel::query()->get(['channel_key', 'group_name', 'channel_name']) as $row) {
            $key = trim((string) $row->channel_key);
            if ($key === '') {
                continue;
            }
            $map[$key] = [
                'group_name' => trim((string) $row->group_name),
                'channel_name' => trim((string) $row->channel_name),
            ];
        }

        return $this->channelLabelMapCache = $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array{group_name: string, channel_name: string}>  $labels
     */
    private function applyChannelLabelsWalk(array &$rows, array $labels): void
    {
        foreach ($rows as &$row) {
            $key = (string) ($row['channel_key'] ?? $row['channel'] ?? '');
            $row['channel_key'] = $key;
            if ($key !== '' && isset($labels[$key])) {
                if ($labels[$key]['channel_name'] !== '') {
                    $row['channel'] = $labels[$key]['channel_name'];
                }
                if ($labels[$key]['group_name'] !== '') {
                    $row['channel_group'] = $labels[$key]['group_name'];
                }
            }
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->applyChannelLabelsWalk($row['_children'], $labels);
            }
        }
        unset($row);
    }

    /**
     * eBay / eBay 2 / eBay 3 / TikTok 1 were briefly saved as "* Total".
     * Restore their Type names so they stay regular rows under the new group totals.
     */
    private function revertStandaloneTypeTotalLabels(): void
    {
        if (! Schema::hasTable('advertisement_master_channel_labels')) {
            return;
        }

        foreach (['eBay', 'eBay 2', 'eBay 3', 'TikTok 1', 'Temu', 'Temu 1', 'Temu 2'] as $key) {
            $label = AdvertisementMasterChannelLabel::query()->where('channel_key', $key)->first();
            if (! $label) {
                continue;
            }
            $name = trim((string) $label->channel_name);
            if ($name !== '' && preg_match('/\s+Total$/i', $name)) {
                $label->channel_name = $key === 'Temu' ? 'Temu 1' : $key;
                $label->save();
            }
        }

        $this->ensureTemu1TypeLabel();
    }

    /**
     * The Temu ads source row is Temu 1. Keep the saved Type name in sync
     * unless someone has already customized it.
     */
    private function ensureTemu1TypeLabel(): void
    {
        $label = AdvertisementMasterChannelLabel::query()->where('channel_key', 'Temu')->first();
        if (! $label) {
            return;
        }

        $name = trim((string) $label->channel_name);
        if ($name !== '' && strcasecmp($name, 'Temu') !== 0) {
            return;
        }

        $label->channel_name = 'Temu 1';
        if (trim((string) $label->group_name) === '') {
            $label->group_name = 'Temu';
        }
        $label->save();
        $this->channelLabelMapCache = null;
    }

    /**
     * Fold standalone eBay, TikTok, and Temu type rows under a yellow group-total parent.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function wrapStandaloneChannelTotals(array &$rows): void
    {
        $this->wrapRowsAsGroupTotal($rows, ['eBay', 'eBay 2', 'eBay 3'], [
            'channel' => 'eBay Total',
            'channel_key' => 'eBay Total',
            'channel_group' => 'eBay',
            'marketplace' => 'ebay',
            'source' => 'ebay_group_total',
        ]);
        $this->wrapRowsAsGroupTotal($rows, ['TikTok 1'], [
            'channel' => 'TikTok Total',
            'channel_key' => 'TikTok Total',
            'channel_group' => 'TikTok',
            'marketplace' => 'tiktok',
            'source' => 'tiktok_group_total',
        ]);
        $this->wrapRowsAsGroupTotal($rows, ['Temu', 'Temu 1', 'Temu 2'], [
            'channel' => 'Temu Total',
            'channel_key' => 'Temu Total',
            'channel_group' => 'Temu',
            'marketplace' => 'temu',
            'source' => 'temu_group_total',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $memberKeys
     * @param  array{channel: string, channel_key: string, channel_group: string, marketplace: string, source: string}  $parentMeta
     */
    private function wrapRowsAsGroupTotal(array &$rows, array $memberKeys, array $parentMeta): void
    {
        $members = [];
        $firstIndex = null;
        $kept = [];
        foreach ($rows as $row) {
            if ($this->rowMatchesChannelKeys($row, $memberKeys)) {
                if ($firstIndex === null) {
                    $firstIndex = count($kept);
                }
                $row['is_sub_row'] = true;
                $row['channel_group'] = $parentMeta['channel_group'];
                $key = trim((string) ($row['channel_key'] ?? ''));
                $name = trim((string) ($row['channel'] ?? ''));
                if (preg_match('/\s+Total$/i', $name)) {
                    $row['channel'] = $this->stripTotalSuffix($key !== '' ? $key : $name);
                }
                $members[] = $row;
            } else {
                $kept[] = $row;
            }
        }
        if ($members === []) {
            return;
        }

        $parent = $this->buildGroupTotalRow($members, $parentMeta);
        $parent['_children'] = $members;
        array_splice($kept, $firstIndex ?? count($kept), 0, [$parent]);
        $rows = $kept;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function rowMatchesChannelKeys(array $row, array $keys): bool
    {
        foreach ([(string) ($row['channel_key'] ?? ''), (string) ($row['channel'] ?? '')] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            if (in_array($candidate, $keys, true) || in_array($this->stripTotalSuffix($candidate), $keys, true)) {
                return true;
            }
        }

        return false;
    }

    private function stripTotalSuffix(string $name): string
    {
        return trim((string) preg_replace('/\s+Total$/i', '', $name));
    }

    /**
     * @param  array<int, array<string, mixed>>  $members
     * @param  array{channel: string, channel_key: string, channel_group: string, marketplace: string, source: string}  $meta
     * @return array<string, mixed>
     */
    private function buildGroupTotalRow(array $members, array $meta): array
    {
        $spend = 0.0;
        $clicks = 0.0;
        $sold = 0.0;
        $sales = 0.0;
        $active = 0.0;
        foreach ($members as $member) {
            $spend += (float) ($member['spend'] ?? 0);
            $clicks += (float) ($member['clicks'] ?? 0);
            $sold += (float) ($member['sold'] ?? 0);
            $sales += (float) ($member['sales'] ?? 0);
            $active += (float) ($member['active'] ?? 0);
        }

        return [
            'channel' => $meta['channel'],
            'channel_key' => $meta['channel_key'],
            'channel_group' => $meta['channel_group'],
            'source' => $meta['source'],
            'marketplace' => $meta['marketplace'],
            'spend' => round($spend, 2),
            'clicks' => (int) round($clicks),
            'sold' => (int) round($sold),
            'sales' => round($sales, 2),
            'cvr' => $clicks > 0 ? round(($sold / $clicks) * 100, 1) : 0.0,
            'acos' => $sales > 0 ? (int) round(($spend / $sales) * 100) : ($spend > 0 ? 100 : 0),
            'tcos' => 0,
            'active' => (int) round($active),
            'views' => 0,
            'is_sub_row' => false,
            'is_group_total' => true,
        ];
    }

    /**
     * Yellow "{Channel} Total" rows for every Active Channel Master channel
     * that is not already represented on Advertisement Master.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function attachMissingChannelMasterTotals(array &$rows): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }

        $occupied = $this->collectOccupiedChannelKeys($rows);
        $seen = [];

        try {
            $channels = ChannelMaster::query()
                ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
                ->orderBy('channel')
                ->get(['id', 'channel']);
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master Channel Master lookup failed: '.$e->getMessage());

            return;
        }

        foreach ($channels as $channel) {
            $name = trim((string) ($channel->channel ?? ''));
            if ($name === '') {
                continue;
            }
            $norm = $this->normalizeChannelMatchKey($name);
            if ($norm === '' || isset($occupied[$norm]) || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $rows[] = $this->emptyChannelTotalRow($name);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, true>
     */
    private function collectOccupiedChannelKeys(array $rows): array
    {
        $set = [];
        $walk = function (array $list) use (&$walk, &$set): void {
            foreach ($list as $row) {
                foreach (['channel_key', 'channel', 'channel_group', 'marketplace'] as $field) {
                    $norm = $this->normalizeChannelMatchKey((string) ($row[$field] ?? ''));
                    if ($norm !== '') {
                        $set[$norm] = true;
                    }
                }
                if (! empty($row['_children']) && is_array($row['_children'])) {
                    $walk($row['_children']);
                }
            }
        };
        $walk($rows);

        return $set;
    }

    private function normalizeChannelMatchKey(string $name): string
    {
        $n = strtolower($this->stripTotalSuffix($name));
        $n = preg_replace('/[^a-z0-9]+/', '', $n) ?? '';
        if ($n === '') {
            return '';
        }

        $aliases = [
            'amz' => 'amazon',
            'ebay1' => 'ebay',
            'ebayone' => 'ebay',
            'ebaytwo' => 'ebay2',
            'ebaythree' => 'ebay3',
            'tiktokshop' => 'tiktok1',
            'tiktoks' => 'tiktok1',
            'tiktok' => 'tiktok1',
            'temuone' => 'temu1',
        ];

        return $aliases[$n] ?? $n;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyChannelTotalRow(string $channelName): array
    {
        $type = preg_match('/\bTotal$/i', $channelName)
            ? $channelName
            : $channelName.' Total';

        return [
            'channel' => $type,
            'channel_key' => $type,
            'channel_group' => $channelName,
            'source' => 'channel_master',
            'marketplace' => $this->normalizeChannelMatchKey($channelName),
            'spend' => 0.0,
            'clicks' => 0,
            'sold' => 0,
            'sales' => 0.0,
            'cvr' => 0.0,
            'acos' => 0,
            'tcos' => 0,
            'active' => 0,
            'views' => 0,
            'is_sub_row' => false,
            'is_group_total' => true,
            'is_sum_row' => true,
        ];
    }

    /**
     * Every yellow Total needs at least one Type row so ads data is visible
     * when the Types filter is on, and so Channel filter/sort can match it.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function ensureDefaultTypeRows(array &$rows): void
    {
        foreach ($rows as &$row) {
            $children = is_array($row['_children'] ?? null) ? $row['_children'] : [];
            if ($children !== []) {
                $this->ensureDefaultTypeRows($children);
                $row['_children'] = $children;

                continue;
            }

            if (empty($row['is_sum_row']) && empty($row['is_group_total'])) {
                continue;
            }

            $child = $this->makeDefaultTypeRow($row);
            $this->applySavedLabelToRow($child);
            $row['_children'] = [$child];
        }
        unset($row);
    }

    /**
     * @param  array<string, mixed>  $parent
     * @return array<string, mixed>
     */
    private function makeDefaultTypeRow(array $parent): array
    {
        $group = trim((string) ($parent['channel_group'] ?? ''));
        if ($group === '') {
            $group = $this->stripTotalSuffix((string) ($parent['channel'] ?? $parent['channel_key'] ?? ''));
        }
        if ($group === '') {
            $group = 'Other';
        }
        $typeName = $this->stripTotalSuffix((string) ($parent['channel'] ?? $group));
        if ($typeName === '') {
            $typeName = $group;
        }

        return [
            'channel' => $typeName,
            'channel_key' => 'default-type:'.$this->normalizeChannelMatchKey($group),
            'channel_group' => $group,
            'source' => 'default_type',
            'marketplace' => (string) ($parent['marketplace'] ?? ''),
            'href' => $parent['href'] ?? null,
            'spend' => (float) ($parent['spend'] ?? 0),
            'clicks' => (int) ($parent['clicks'] ?? 0),
            'sold' => (int) ($parent['sold'] ?? 0),
            'sales' => (float) ($parent['sales'] ?? 0),
            'cvr' => (float) ($parent['cvr'] ?? 0),
            'acos' => (float) ($parent['acos'] ?? 0),
            'tcos' => 0,
            'has_tcos' => false,
            't_sales' => 0.0,
            'has_t_sales' => false,
            'active' => (int) ($parent['active'] ?? 0),
            'views' => 0,
            'is_sub_row' => true,
            'is_default_type' => true,
            'is_custom' => false,
            'is_sum_row' => false,
            'is_group_total' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function applySavedLabelToRow(array &$row): void
    {
        $key = trim((string) ($row['channel_key'] ?? ''));
        if ($key === '') {
            return;
        }
        $labels = $this->channelLabelMap();
        if (! isset($labels[$key])) {
            return;
        }
        if ($labels[$key]['channel_name'] !== '') {
            $row['channel'] = $labels[$key]['channel_name'];
        }
        if ($labels[$key]['group_name'] !== '') {
            $row['channel_group'] = $labels[$key]['group_name'];
        }
    }

    /**
     * Persist Channel names on Type rows so All-view Channel filter/sort
     * keeps working after reload.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function persistMissingTypeRowLabels(array $rows): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        if (! Schema::hasTable('advertisement_master_channel_labels')) {
            return;
        }

        try {
            $have = [];
            foreach (AdvertisementMasterChannelLabel::query()->pluck('channel_key') as $key) {
                $key = trim((string) $key);
                if ($key !== '') {
                    $have[$key] = true;
                }
            }

            $walk = function (array $list) use (&$walk, &$have): void {
                foreach ($list as $row) {
                    if (! empty($row['_children']) && is_array($row['_children'])) {
                        $walk($row['_children']);
                    }
                    if (empty($row['is_sub_row']) || ! empty($row['is_sum_row']) || ! empty($row['is_group_total'])) {
                        continue;
                    }
                    $key = trim((string) ($row['channel_key'] ?? ''));
                    $group = trim((string) ($row['channel_group'] ?? ''));
                    $type = trim((string) ($row['channel'] ?? ''));
                    if ($key === '' || $group === '' || isset($have[$key]) || str_starts_with($key, 'custom:')) {
                        continue;
                    }

                    AdvertisementMasterChannelLabel::query()->updateOrCreate(
                        ['channel_key' => $key],
                        [
                            'group_name' => $group,
                            'channel_name' => $type !== '' ? $type : $group,
                        ]
                    );
                    $have[$key] = true;
                }
            };
            $walk($rows);
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master type-row label save failed: '.$e->getMessage());
        }
    }

    /**
     * Yellow total rows get a " Total" Type suffix and that name is saved.
     * Totals = top-level channel rows (eBay / eBay 2 / eBay 3 / TikTok 1)
     * plus any parent that has children (Amazon, Shopify, Facebook, …).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function ensureSumRowTotalSuffix(array &$rows): void
    {
        foreach ($rows as &$row) {
            $children = $row['_children'] ?? [];
            $hasChildren = is_array($children) && $children !== [];
            if ($hasChildren) {
                $key = trim((string) ($row['channel_key'] ?? $row['channel'] ?? ''));
                $name = trim((string) ($row['channel'] ?? ''));
                if ($key !== '' && $name !== '' && ! preg_match('/\bTotal$/i', $name)) {
                    $name .= ' Total';
                    $row['channel'] = $name;
                    $row['channel_key'] = $key;
                    $this->persistSumRowLabel($key, $name, $row);
                }
            }
            if ($hasChildren) {
                $this->ensureSumRowTotalSuffix($children);
                $row['_children'] = $children;
            }
        }
        unset($row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function persistSumRowLabel(string $channelKey, string $typeName, array $row): void
    {
        if (! Schema::hasTable('advertisement_master_channel_labels')) {
            return;
        }

        try {
            $existing = AdvertisementMasterChannelLabel::query()
                ->where('channel_key', $channelKey)
                ->first();
            $group = trim((string) ($row['channel_group'] ?? ''));
            if ($group === '') {
                $group = trim((string) ($existing->group_name ?? ''));
            }
            if ($group === '') {
                $group = $this->inferChannelGroup($row, $channelKey);
            }

            AdvertisementMasterChannelLabel::query()->updateOrCreate(
                ['channel_key' => $channelKey],
                [
                    'group_name' => $group,
                    'channel_name' => $typeName,
                ]
            );
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master sum-row label save failed: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function inferChannelGroup(array $row, string $channelKey): string
    {
        $mp = strtolower((string) ($row['marketplace'] ?? ''));
        if ($mp === 'amazon') {
            return 'Amazon';
        }
        if (str_starts_with($mp, 'ebay')) {
            return 'eBay';
        }
        if ($mp === 'shopify') {
            return 'Shopify';
        }
        if ($mp === 'tiktok') {
            return 'TikTok';
        }
        if (str_starts_with($mp, 'temu')) {
            return 'Temu';
        }

        $base = $channelKey;
        if (str_contains($base, self::SUBROW_SEPARATOR)) {
            $base = explode(self::SUBROW_SEPARATOR, $base, 2)[0];
        }

        return $base !== '' ? $base : 'Other';
    }

    /**
     * Append user-created Channel / Type rows. Nested under a matching parent
     * when one exists; otherwise they become their own top-level row.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function attachCustomRows(array &$rows): void
    {
        if (! Schema::hasTable('advertisement_master_custom_rows')) {
            return;
        }

        foreach (AdvertisementMasterCustomRow::query()->orderBy('id')->get() as $custom) {
            $channelName = trim((string) $custom->channel_name);
            $typeName = trim((string) $custom->type_name);
            if ($channelName === '' || $typeName === '') {
                continue;
            }

            $row = [
                'channel' => $typeName,
                'channel_key' => 'custom:'.$custom->id,
                'channel_group' => $channelName,
                'source' => 'custom',
                'is_custom' => true,
                'spend' => 0.0,
                'clicks' => 0,
                'sold' => 0,
                'sales' => 0.0,
                'cvr' => 0.0,
                'acos' => 0,
                'tcos' => 0,
                'active' => 0,
                'views' => 0,
                'is_sub_row' => true,
                'marketplace' => '',
            ];

            $attached = false;
            foreach ($rows as &$parent) {
                $parentGroup = trim((string) ($parent['channel_group'] ?? $parent['channel'] ?? ''));
                if (strcasecmp($parentGroup, $channelName) === 0) {
                    if (! isset($parent['_children']) || ! is_array($parent['_children'])) {
                        $parent['_children'] = [];
                    }
                    $parent['_children'][] = $row;
                    $attached = true;
                    break;
                }
            }
            unset($parent);

            if (! $attached) {
                $row['is_sub_row'] = false;
                $rows[] = $row;
            }
        }
    }

    /**
     * Drop hidden rows from the tree and hoist their children one level up.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function removeHiddenRows(array &$rows): void
    {
        $hidden = $this->hiddenChannelKeySet();
        if ($hidden === []) {
            return;
        }

        $rows = $this->filterHiddenRows($rows, $hidden);
    }

    /**
     * @return array<string, true>
     */
    private function hiddenChannelKeySet(): array
    {
        $hidden = [
            'Shopify · Facebook' => true,
            'Shopify · Instagram' => true,
        ];

        if (! Schema::hasTable('advertisement_master_hidden_rows')) {
            return $hidden;
        }

        foreach (AdvertisementMasterHiddenRow::query()->pluck('channel_key') as $key) {
            $key = trim((string) $key);
            if ($key !== '') {
                $hidden[$key] = true;
            }
        }

        return $hidden;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, true>  $hidden
     * @return array<int, array<string, mixed>>
     */
    private function filterHiddenRows(array $rows, array $hidden): array
    {
        $out = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['channel_key'] ?? $row['channel'] ?? ''));
            $children = [];
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $children = $this->filterHiddenRows($row['_children'], $hidden);
            }

            if ($key !== '' && isset($hidden[$key])) {
                foreach ($children as $child) {
                    $out[] = $child;
                }

                continue;
            }

            if ($children !== []) {
                $row['_children'] = $children;
            } else {
                unset($row['_children']);
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Amazon L30 store sales — same figure as Active Channel / All Marketplace
     * Master "L30 Sales" (amazon_orders Pacific window, MDM fallback).
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

            $fromOrders = (float) AmazonOrder::badgeTotalSalesByOrderDate($startAmazonWindow, $endToday);
            $orderCount = (int) AmazonOrder::constrainOrderDate(
                DB::table('amazon_orders as o')->where(function ($q) {
                    $q->whereNull('o.status')
                        ->orWhereNotIn('o.status', ['Canceled', 'Cancelled']);
                }),
                $startAmazonWindow,
                $endToday
            )->count(DB::raw('DISTINCT o.amazon_order_id'));

            if ($orderCount > 0 && $fromOrders > 0) {
                return round($fromOrders, 2);
            }

            if (Schema::hasTable('marketplace_daily_metrics')) {
                $row = MarketplaceDailyMetric::query()
                    ->where('channel', 'Amazon')
                    ->latest('date')
                    ->first();

                return round((float) ($row->total_sales ?? 0), 2);
            }

            return round($fromOrders, 2);
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
            if ($this->isAdTypeRow($row)) {
                $this->clearTcos($row);
            } else {
                $marketplace = (string) ($row['marketplace'] ?? 'amazon');
                $this->applyTcos($row, (float) ($netSalesByMarketplace[$marketplace] ?? 0));
            }

            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->applyTcosToRows($row['_children'], $netSalesByMarketplace);
            }
        }
        unset($row);
    }

    /**
     * Replace campaign-report ads figures with the Active Channel row
     * (channel_master_calculated_data): spend, ad clicks, ad sold, ad sales, ACOS, Ads CVR, Ads%.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function applyActiveChannelAds(array &$rows): void
    {
        $this->applyActiveChannelAdsWalk($rows, $this->activeChannelAdsByKey());
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    private function activeChannelAdsByKey(): array
    {
        if (! Schema::hasTable('channel_master') || ! Schema::hasTable('channel_master_calculated_data')) {
            return [];
        }

        $active = [];
        foreach (ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(COALESCE(status, ""))) = ?', ['active'])
            ->pluck('channel') as $name) {
            foreach ($this->activeChannelAdsKeys((string) $name) as $key) {
                $active[$key] = true;
            }
        }

        $map = [];
        try {
            $rows = ChannelMasterCalculatedData::query()->get([
                'channel', 'total_ad_spend', 'clicks', 'ad_sold', 'ad_sales', 'acos', 'cvr',
                'ads_percentage', 'l30_sales',
                'kw_clicks', 'pt_clicks', 'hl_clicks', 'pmt_clicks', 'shopping_clicks', 'serp_clicks',
                'kw_sales', 'pt_sales', 'hl_sales', 'pmt_sales', 'shopping_sales', 'serp_sales',
                'kw_sold', 'pt_sold', 'hl_sold', 'pmt_sold', 'shopping_sold', 'serp_sold',
                'kw_acos', 'pt_acos', 'hl_acos', 'pmt_acos', 'shopping_acos', 'serp_acos',
                'kw_cvr', 'pt_cvr', 'hl_cvr', 'pmt_cvr', 'shopping_cvr', 'serp_cvr',
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master active-channel ads read failed: '.$e->getMessage());

            return [];
        }

        foreach ($rows as $row) {
            $metrics = [
                'spend' => (float) ($row->total_ad_spend ?? 0),
                'clicks' => (int) ($row->clicks ?? 0),
                'sold' => (int) ($row->ad_sold ?? 0),
                'sales' => (float) ($row->ad_sales ?? 0),
                'acos' => (float) ($row->acos ?? 0),
                'cvr' => (float) ($row->cvr ?? 0),
                'tcos' => (float) ($row->ads_percentage ?? 0),
                'l30_sales' => (float) ($row->l30_sales ?? 0),
                'kw_clicks' => (int) ($row->kw_clicks ?? 0),
                'pt_clicks' => (int) ($row->pt_clicks ?? 0),
                'hl_clicks' => (int) ($row->hl_clicks ?? 0),
                'pmt_clicks' => (int) ($row->pmt_clicks ?? 0),
                'shopping_clicks' => (int) ($row->shopping_clicks ?? 0),
                'serp_clicks' => (int) ($row->serp_clicks ?? 0),
                'kw_sales' => (float) ($row->kw_sales ?? 0),
                'pt_sales' => (float) ($row->pt_sales ?? 0),
                'hl_sales' => (float) ($row->hl_sales ?? 0),
                'pmt_sales' => (float) ($row->pmt_sales ?? 0),
                'shopping_sales' => (float) ($row->shopping_sales ?? 0),
                'serp_sales' => (float) ($row->serp_sales ?? 0),
                'kw_sold' => (int) ($row->kw_sold ?? 0),
                'pt_sold' => (int) ($row->pt_sold ?? 0),
                'hl_sold' => (int) ($row->hl_sold ?? 0),
                'pmt_sold' => (int) ($row->pmt_sold ?? 0),
                'shopping_sold' => (int) ($row->shopping_sold ?? 0),
                'serp_sold' => (int) ($row->serp_sold ?? 0),
                'kw_acos' => (float) ($row->kw_acos ?? 0),
                'pt_acos' => (float) ($row->pt_acos ?? 0),
                'hl_acos' => (float) ($row->hl_acos ?? 0),
                'pmt_acos' => (float) ($row->pmt_acos ?? 0),
                'shopping_acos' => (float) ($row->shopping_acos ?? 0),
                'serp_acos' => (float) ($row->serp_acos ?? 0),
                'kw_cvr' => (float) ($row->kw_cvr ?? 0),
                'pt_cvr' => (float) ($row->pt_cvr ?? 0),
                'hl_cvr' => (float) ($row->hl_cvr ?? 0),
                'pmt_cvr' => (float) ($row->pmt_cvr ?? 0),
                'shopping_cvr' => (float) ($row->shopping_cvr ?? 0),
                'serp_cvr' => (float) ($row->serp_cvr ?? 0),
            ];
            foreach ($this->activeChannelAdsKeys((string) ($row->channel ?? '')) as $key) {
                if ($key === '' || ! isset($active[$key])) {
                    continue;
                }
                $map[$key] = $metrics;
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function activeChannelAdsKeys(string $name): array
    {
        $key = $this->normalizeChannelMatchKey($name);
        if ($key === '') {
            return [];
        }
        $keys = [$key];
        if ($key === 'shopifyb2c') {
            $keys[] = 'shopify';
        }
        if ($key === 'tiktokshop2') {
            $keys[] = 'tiktok2';
        }

        return $keys;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, float|int>>  $byChannel
     */
    private function applyActiveChannelAdsWalk(array &$rows, array $byChannel): void
    {
        foreach ($rows as &$row) {
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->applyActiveChannelAdsWalk($row['_children'], $byChannel);
            }
            if (! empty($row['is_group_total'])) {
                $this->sumGroupAdsFromChildren($row);
                continue;
            }

            $name = (string) ($row['channel_key'] ?? $row['channel'] ?? '');
            $isType = str_contains($name, self::SUBROW_SEPARATOR);
            $type = $isType ? $this->activeChannelBreakdown($name) : null;
            $key = $this->adsParentKey($row);
            $metrics = $byChannel[$key] ?? null;

            if ($isType && $type !== null && $metrics !== null) {
                $this->applyBreakdownAds($row, $metrics, $type);
            } elseif (! $isType && $metrics !== null) {
                $this->applyChannelAds($row, $metrics);
            } elseif (! $isType) {
                $this->zeroAdsMetrics($row);
            }
        }
        unset($row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function adsParentKey(array $row): string
    {
        $name = (string) ($row['channel_key'] ?? $row['channel'] ?? '');
        if (str_contains($name, self::SUBROW_SEPARATOR)) {
            $name = trim(explode(self::SUBROW_SEPARATOR, $name)[0]);
        }
        $keys = $this->activeChannelAdsKeys($name);

        return $keys[0] ?? '';
    }

    private function activeChannelBreakdown(string $name): ?string
    {
        $part = $name;
        if (str_contains($name, self::SUBROW_SEPARATOR)) {
            $bits = explode(self::SUBROW_SEPARATOR, $name);
            $part = trim((string) end($bits));
        }
        $key = strtolower((string) preg_replace('/[^a-z0-9]+/', '', $part));

        return match ($key) {
            'kw' => 'kw',
            'pt' => 'pt',
            'hl' => 'hl',
            'pmt', 'promoted' => 'pmt',
            'shopping', 'googleshopping' => 'shopping',
            'serp', 'googleserp' => 'serp',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, float|int>  $metrics
     */
    private function applyChannelAds(array &$row, array $metrics): void
    {
        $row['spend'] = round((float) $metrics['spend'], 2);
        $row['clicks'] = (int) $metrics['clicks'];
        $row['sold'] = (int) $metrics['sold'];
        $row['sales'] = round((float) $metrics['sales'], 2);
        $row['acos'] = round((float) $metrics['acos'], 1);
        $row['cvr'] = round((float) $metrics['cvr'], 1);
        $row['tcos'] = round((float) $metrics['tcos'], 1);
        $row['has_tcos'] = ((float) $metrics['l30_sales']) > 0 || ((float) $metrics['spend']) > 0;
        $row['t_sales'] = round((float) $metrics['l30_sales'], 2);
        $row['has_t_sales'] = ((float) $metrics['l30_sales']) > 0;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, float|int>  $metrics
     */
    private function applyBreakdownAds(array &$row, array $metrics, string $type): void
    {
        $row['spend'] = 0;
        $row['clicks'] = (int) ($metrics[$type.'_clicks'] ?? 0);
        $row['sold'] = (int) ($metrics[$type.'_sold'] ?? 0);
        $row['sales'] = round((float) ($metrics[$type.'_sales'] ?? 0), 2);
        $row['acos'] = round((float) ($metrics[$type.'_acos'] ?? 0), 1);
        $row['cvr'] = round((float) ($metrics[$type.'_cvr'] ?? 0), 1);
        $this->clearTcos($row);
        $row['t_sales'] = 0;
        $row['has_t_sales'] = false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function zeroAdsMetrics(array &$row): void
    {
        $row['spend'] = 0;
        $row['clicks'] = 0;
        $row['sold'] = 0;
        $row['sales'] = 0;
        $row['acos'] = 0;
        $row['cvr'] = 0;
        $this->clearTcos($row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function sumGroupAdsFromChildren(array &$row): void
    {
        $spend = 0.0;
        $clicks = 0.0;
        $sold = 0.0;
        $sales = 0.0;
        $l30 = 0.0;
        foreach ($row['_children'] ?? [] as $child) {
            if (! is_array($child)) {
                continue;
            }
            $spend += (float) ($child['spend'] ?? 0);
            $clicks += (float) ($child['clicks'] ?? 0);
            $sold += (float) ($child['sold'] ?? 0);
            $sales += (float) ($child['sales'] ?? 0);
            $l30 += (float) ($child['t_sales'] ?? 0);
        }
        $row['spend'] = round($spend, 2);
        $row['clicks'] = (int) round($clicks);
        $row['sold'] = (int) round($sold);
        $row['sales'] = round($sales, 2);
        $row['cvr'] = $clicks > 0 ? round(($sold / $clicks) * 100, 1) : 0;
        $row['acos'] = $sales > 0 ? round(($spend / $sales) * 100, 1) : ($spend > 0 ? 100 : 0);
        $row['t_sales'] = round($l30, 2);
        $row['has_t_sales'] = $l30 > 0;
        $row['tcos'] = $l30 > 0 ? round(($spend / $l30) * 100, 1) : 0;
        $row['has_tcos'] = $l30 > 0 || $spend > 0;
    }

    /**
     * KW / PT / HL (and Shopify · …) are ad types, not Active Channel rows.
     * Active Channel TCOS is one number per channel: total spend / L30 sales.
     *
     * @param  array<string, mixed>  $row
     */
    private function isAdTypeRow(array $row): bool
    {
        foreach (['channel_key', 'channel'] as $field) {
            $name = (string) ($row[$field] ?? '');
            if ($name !== '' && str_contains($name, self::SUBROW_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function clearTcos(array &$row): void
    {
        $row['tcos'] = 0;
        $row['has_tcos'] = false;
    }

    /**
     * TCOS = Spend / channel L30 sales — same Ads% rule as Active Channel Master.
     *
     * @param  array<string, mixed>  $row
     */
    private function applyTcos(array &$row, float $netSales): void
    {
        $spend = (float) ($row['spend'] ?? 0);
        $adsSales = (float) ($row['sales'] ?? 0);
        $row['tcos'] = Ebay2CampaignAdsController::tcosPercent($spend, $netSales, $adsSales);
        $row['has_tcos'] = $netSales > 0 || $spend > 0;
    }

    /**
     * KW / PT / HL and other type rows do not get T Sales or TCOS.
     * Those figures belong on All / Total channel rows only.
     *
     * @param  array<string, mixed>  $row
     */
    private function shouldHideTypeChannelMetrics(array $row): bool
    {
        return $this->isAdTypeRow($row)
            || ! empty($row['is_default_type'])
            || (! empty($row['is_custom']) && empty($row['is_sum_row']) && empty($row['is_group_total']));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function clearTypeRowChannelMetrics(array &$rows): void
    {
        foreach ($rows as &$row) {
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->clearTypeRowChannelMetrics($row['_children']);
            }
            if ($this->shouldHideTypeChannelMetrics($row)) {
                $row['t_sales'] = 0.0;
                $row['has_t_sales'] = false;
                $this->clearTcos($row);
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

    private function isActiveChannelSnapshot(string $channel): bool
    {
        return in_array($channel, [
            self::SSALES_CHANNEL,
            self::ACTIVE_SPEND_CHANNEL,
            self::ACTIVE_CLICKS_CHANNEL,
        ], true);
    }

    /**
     * Sum of each day's saved Active Channel snapshot (channel_master_daily_data).
     * One full snapshot per channel per day. Listing-only rows without l30_sales
     * are skipped so they cannot zero the day.
     *
     * @return array<string, array{l30_sales: float, ad_spend: float, clicks: float}>
     */
    private function activeChannelDailyTotals(string $from, string $to): array
    {
        if (! Schema::hasTable('channel_master_daily_data')) {
            return [];
        }

        $active = [];
        foreach ($this->activeChannelNames() as $name) {
            $key = $this->normalizeChannelMatchKey($name);
            if ($key !== '') {
                $active[$key] = true;
            }
        }

        $rows = DB::table('channel_master_daily_data')
            ->whereDate('snapshot_date', '>=', $from)
            ->whereDate('snapshot_date', '<=', $to)
            ->orderBy('id')
            ->get(['channel', 'snapshot_date', 'summary_data']);

        $picked = [];
        foreach ($rows as $row) {
            $key = $this->normalizeChannelMatchKey((string) $row->channel);
            if ($key === '' || ($active !== [] && ! isset($active[$key]))) {
                continue;
            }
            $summary = ChannelMasterSummary::decodeSummaryData($row->summary_data);
            if (! array_key_exists('l30_sales', $summary)) {
                continue;
            }
            $date = $row->snapshot_date instanceof \DateTimeInterface
                ? $row->snapshot_date->format('Y-m-d')
                : substr((string) $row->snapshot_date, 0, 10);
            $picked[$date.'|'.$key] = $summary;
        }

        $out = [];
        foreach ($picked as $combo => $summary) {
            $date = strstr($combo, '|', true);
            if (! is_string($date) || $date === '') {
                continue;
            }
            $out[$date]['l30_sales'] = ($out[$date]['l30_sales'] ?? 0) + (float) ($summary['l30_sales'] ?? 0);
            $out[$date]['ad_spend'] = ($out[$date]['ad_spend'] ?? 0) + (float) ($summary['total_ad_spend'] ?? 0);
            $out[$date]['ad_sales'] = ($out[$date]['ad_sales'] ?? 0) + (float) ($summary['ad_sales'] ?? 0);
            $out[$date]['clicks'] = ($out[$date]['clicks'] ?? 0) + (float) ($summary['total_views'] ?? 0);
            $channelKey = substr($combo, strpos($combo, '|') + 1);
            $cvrPart = $this->summaryListingCvrPart(is_string($channelKey) ? $channelKey : '', $summary);
            if ($cvrPart !== null) {
                $out[$date]['cvr_units'] = ($out[$date]['cvr_units'] ?? 0) + $cvrPart['units'];
                $out[$date]['cvr_views'] = ($out[$date]['cvr_views'] ?? 0) + $cvrPart['views'];
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * Write Active Channel daily totals into advertisement history.
     * A past day is inserted once and then left alone. Today is refreshed.
     */
    private function persistActiveChannelDaily(string $from, string $to): void
    {
        if (! Schema::hasTable('advertisement_master_metric_snapshots')) {
            return;
        }

        try {
            $totals = $this->activeChannelDailyTotals($from, $to);
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master active-channel daily read failed: '.$e->getMessage());

            return;
        }

        $today = Carbon::now(self::SNAPSHOT_TIMEZONE)->toDateString();
        $now = Carbon::now(self::SNAPSHOT_TIMEZONE);
        foreach ($totals as $date => $metric) {
            $refresh = $date === $today;
            $this->saveDailyTotal($date, self::SSALES_CHANNEL, [
                'spend' => 0,
                'clicks' => 0,
                'sold' => 0,
                'sales' => round($metric['l30_sales'], 2),
                'active' => 0,
            ], $now, $refresh);
            $this->saveDailyTotal($date, self::ACTIVE_SPEND_CHANNEL, [
                'spend' => round($metric['ad_spend'], 2),
                'clicks' => 0,
                'sold' => 0,
                'sales' => 0,
                'active' => 0,
            ], $now, $refresh);
            $this->saveDailyTotal($date, self::ACTIVE_CLICKS_CHANNEL, [
                'spend' => 0,
                'clicks' => (int) round($metric['clicks']),
                'sold' => 0,
                'sales' => 0,
                'active' => 0,
            ], $now, $refresh);
        }
    }

    /**
     * @param  array<string, float|int>  $measures
     */
    private function saveDailyTotal(string $date, string $channel, array $measures, Carbon $now, bool $refresh): void
    {
        $exists = DB::table('advertisement_master_metric_snapshots')
            ->where('snapshot_date', $date)
            ->where('channel', $channel)
            ->exists();
        if ($exists && ! $refresh) {
            return;
        }

        try {
            $this->saveSnapshotRow($date, $channel, $measures, $now);
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master daily total save failed for '.$channel.' '.$date.': '.$e->getMessage());
        }
    }

    /**
     * Last two saved days for one pseudo-channel. Direction matches the graph.
     *
     * @return array{last: ?float, dir: string}
     */
    private function savedMetricEnd(string $channel, string $column): array
    {
        $empty = ['last' => null, 'dir' => 'flat'];
        if (! Schema::hasTable('advertisement_master_metric_snapshots')) {
            return $empty;
        }
        if (! in_array($column, ['spend', 'clicks', 'sold', 'sales', 'active'], true)) {
            return $empty;
        }

        $rows = DB::table('advertisement_master_metric_snapshots')
            ->where('channel', $channel)
            ->orderByDesc('snapshot_date')
            ->limit(2)
            ->get(['snapshot_date', $column]);
        if ($rows->isEmpty()) {
            return $empty;
        }

        $last = (float) $rows[0]->{$column};
        $dir = 'flat';
        if (isset($rows[1])) {
            $prev = (float) $rows[1]->{$column};
            if (abs($last - $prev) >= 0.01) {
                $dir = $last > $prev ? 'up' : 'down';
            }
        }

        return ['last' => round($last, 2), 'dir' => $dir];
    }

    private function savedTcosDirection(): string
    {
        if (! Schema::hasTable('advertisement_master_metric_snapshots')) {
            return 'flat';
        }

        $sales = DB::table('advertisement_master_metric_snapshots')
            ->where('channel', self::SSALES_CHANNEL)
            ->orderByDesc('snapshot_date')
            ->limit(2)
            ->get(['snapshot_date', 'sales']);
        $spend = DB::table('advertisement_master_metric_snapshots')
            ->where('channel', self::ACTIVE_SPEND_CHANNEL)
            ->orderByDesc('snapshot_date')
            ->get(['snapshot_date', 'spend'])
            ->keyBy(function ($row) {
                return $row->snapshot_date instanceof \DateTimeInterface
                    ? $row->snapshot_date->format('Y-m-d')
                    : substr((string) $row->snapshot_date, 0, 10);
            });

        $pct = [];
        foreach ($sales as $row) {
            $date = $row->snapshot_date instanceof \DateTimeInterface
                ? $row->snapshot_date->format('Y-m-d')
                : substr((string) $row->snapshot_date, 0, 10);
            if (! isset($spend[$date])) {
                continue;
            }
            $sale = (float) $row->sales;
            $cost = (float) $spend[$date]->spend;
            $pct[] = $sale > 0 ? ($cost / $sale) * 100 : ($cost > 0 ? 100.0 : 0.0);
        }
        if (count($pct) < 2) {
            return 'flat';
        }
        $newer = $pct[0];
        $older = $pct[1];
        if (abs($newer - $older) < 0.01) {
            return 'flat';
        }

        return $newer > $older ? 'up' : 'down';
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
            if ($channel === '' || $this->isActiveChannelSnapshot($channel)) {
                continue;
            }
            $this->saveSnapshotRow($today, $channel, $this->snapshotMeasures($row), $now);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, float|int>
     */
    private function snapshotMeasures(array $row): array
    {
        $measures = [
            'spend' => (float) ($row['spend'] ?? 0),
            'clicks' => (float) ($row['clicks'] ?? 0),
            'sold' => (float) ($row['sold'] ?? 0),
            'sales' => (float) ($row['sales'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
        ];
        if ($this->snapshotsHaveMissingAdsColumn()) {
            $measures['missing_ads'] = (int) ($row['missing_ads'] ?? 0);
        }

        return $measures;
    }

    private function snapshotsHaveMissingAdsColumn(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        try {
            $has = Schema::hasColumn('advertisement_master_metric_snapshots', 'missing_ads');
        } catch (\Throwable $e) {
            $has = false;
        }

        return $has;
    }

    /**
     * Write missing-ads onto today's snapshots after Channel Master rows
     * (Temu, etc.) are appended.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function snapshotMissingAds(array $rows): void
    {
        if (! $this->snapshotsHaveMissingAdsColumn()) {
            return;
        }

        $now = Carbon::now(self::SNAPSHOT_TIMEZONE);
        $today = $now->toDateString();
        foreach ($rows as $row) {
            if (empty($row['has_missing_ads'])) {
                continue;
            }
            $channel = trim((string) ($row['channel_key'] ?? ''));
            if ($channel === '') {
                $channel = $this->stripTotalSuffix((string) ($row['channel'] ?? ''));
            }
            if ($channel === '') {
                continue;
            }
            $this->saveSnapshotRow($today, $channel, [
                'missing_ads' => (int) ($row['missing_ads'] ?? 0),
            ], $now);
        }
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
     * First-day channels have no snapshot before today, so trend dots stay
     * hidden and the history chart is empty. Write yesterday from the current
     * measures (flat vs today) and add them to $prevByChannel so the UI
     * matches Amazon / eBay / Shopify immediately.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, float>>  $prevByChannel
     */
    private function seedMissingPriorSnapshots(array $rows, string $today, array &$prevByChannel): void
    {
        $yesterday = Carbon::parse($today, self::SNAPSHOT_TIMEZONE)->subDay()->toDateString();
        $now = Carbon::now(self::SNAPSHOT_TIMEZONE);

        foreach ($this->flattenRows($rows) as $row) {
            $channel = (string) ($row['channel'] ?? '');
            if ($channel === '' || isset($prevByChannel[$channel])) {
                continue;
            }

            $measures = $this->snapshotMeasures($row);

            try {
                $this->saveSnapshotRow($yesterday, $channel, $measures, $now);
                $prevByChannel[$channel] = $measures;
            } catch (\Throwable $e) {
                \Log::warning('Advertisement Master prior-day seed failed for '.$channel.': '.$e->getMessage());
            }
        }
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
        $cols = ['channel', 'spend', 'clicks', 'sold', 'sales', 'active'];
        if ($this->snapshotsHaveMissingAdsColumn()) {
            $cols[] = 'missing_ads';
        }
        $rows = DB::table('advertisement_master_metric_snapshots')
            ->where('snapshot_date', '<', $today)
            ->orderBy('snapshot_date')
            ->get($cols);

        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r->channel] = [
                'spend'  => (float) $r->spend,
                'clicks' => (float) $r->clicks,
                'sold'   => (float) $r->sold,
                'sales'  => (float) $r->sales,
                'active' => (float) ($r->active ?? 0),
                'missing_ads' => (float) ($r->missing_ads ?? 0),
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
            'missing_ads' => $dir((float) ($row['missing_ads'] ?? 0), (float) ($prev['missing_ads'] ?? 0)),
        ];
    }

    /**
     * Re-apply missing-ads trend after late Channel Master rows are attached.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, float>>  $prevByChannel
     */
    private function attachMissingAdsTrends(array &$rows, array $prevByChannel): void
    {
        foreach ($rows as &$row) {
            $channel = (string) ($row['channel_key'] ?? $row['channel'] ?? '');
            $prev = $prevByChannel[$channel] ?? $prevByChannel[(string) ($row['channel'] ?? '')] ?? null;
            if ($prev !== null) {
                $dir = static fn (float $cur, float $was): string => $cur > $was
                    ? 'up'
                    : ($cur < $was ? 'down' : 'flat');
                $row['trend'] = array_merge($row['trend'] ?? [], [
                    'missing_ads' => $dir((float) ($row['missing_ads'] ?? 0), (float) ($prev['missing_ads'] ?? 0)),
                ]);
            }
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->attachMissingAdsTrends($row['_children'], $prevByChannel);
            }
        }
        unset($row);
    }

    /**
     * All-channel badge charts use the same series Active Channel draws.
     * A single channel still reads this page's saved daily rows.
     *
     *   GET /advertisement-master/history?days=30&channel=eBay&metric=spend
     */
    public function history(Request $request)
    {
        $days = max(1, min(365, (int) $request->query('days', 30)));
        $metric = (string) $request->query('metric', 'spend');
        $channel = (string) $request->query('channel', '__total__');
        $labels = [];
        $series = [];

        try {
            if ($metric === 'missing_ads' && $this->historyIsAllChannels($channel)) {
                [$labels, $series] = $this->missingBadgeHistory($days);
            } elseif ($this->historyIsAllChannels($channel) && $this->activeChannelChartMetric($metric) !== null) {
                [$labels, $series] = $this->activeChannelChartHistory($metric, $days);
            } else {
                [$labels, $series] = $this->snapshotHistory($channel, $metric, $days);
            }
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master history failed: '.$e->getMessage());
        }

        return response()->json([
            'status' => 200,
            'days' => $days,
            'labels' => $labels,
            'metrics' => [$metric => $series],
            'channels' => [],
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function historyIsAllChannels(string $channel): bool
    {
        $channel = trim($channel);

        return $channel === '' || $channel === '__total__';
    }

    private function badgeHistoryField(string $metric): ?string
    {
        return match ($metric) {
            'spend' => 'ad_spend',
            'ssales' => 'l30_sales',
            'clicks' => 'total_views',
            'tcos' => 'ads_pct',
            'cvr' => 'cvr_pct',
            default => null,
        };
    }

    private function activeChannelChartMetric(string $metric): ?string
    {
        return match ($metric) {
            'spend' => 'ad_spend',
            'ssales' => 'l30_sales',
            'clicks' => 'total_views',
            'tcos' => 'ads_pct',
            'cvr' => 'cvr',
            'acos' => 'acos',
            default => null,
        };
    }

    /**
     * Same chart Active Channel opens for an all-channels badge.
     * The last point is pinned to this page's live badge.
     *
     * @return array{0: list<string>, 1: list<float|null>}
     */
    private function activeChannelChartHistory(string $metric, int $days): array
    {
        $chartMetric = $this->activeChannelChartMetric($metric);
        if ($chartMetric === null) {
            return [[], []];
        }

        $badge = $this->activeChannelAdBadgePack()['values'][$metric] ?? null;
        $badgeValue = $badge === null ? null : match ($metric) {
            'spend', 'clicks', 'ssales' => (float) round((float) $badge),
            'tcos', 'acos' => round((float) $badge, 1),
            'cvr' => round((float) $badge, 2),
            default => (float) $badge,
        };

        $request = Request::create('/channel-metric-chart-data', 'GET', array_filter([
            'channel' => 'all',
            'metric' => $chartMetric,
            'days' => $days,
            'badge_value' => $badgeValue,
        ], static fn ($value) => $value !== null));

        $response = app(ChannelMasterController::class)->getChannelMetricChartData($request);
        $payload = $response->getData(true);
        $labels = [];
        $series = [];
        foreach (is_array($payload['data'] ?? null) ? $payload['data'] : [] as $point) {
            if (! is_array($point)) {
                continue;
            }
            $labels[] = (string) ($point['date'] ?? '');
            $series[] = isset($point['value']) && is_numeric($point['value'])
                ? round((float) $point['value'], 2)
                : null;
        }

        return [$labels, $series];
    }

    /**
     * @return array{0: list<string>, 1: list<float>}
     */
    private function badgePageHistory(string $metric, int $days): array
    {
        $field = $this->badgeHistoryField($metric);
        if ($field === null) {
            return [[], []];
        }

        $cached = BadgeData::dataForPage('all-marketplace-master');
        $live = array_key_exists($field, $cached) && is_numeric($cached[$field])
            ? (float) $cached[$field]
            : null;
        $points = BadgeDataHistory::series('all-marketplace-master', $field, $days, $live);
        $labels = [];
        $series = [];
        foreach ($points as $point) {
            $labels[] = (string) ($point['date'] ?? '');
            $series[] = round((float) ($point['value'] ?? 0), 2);
        }

        return [$labels, $series];
    }

    /**
     * Missing badge = leaf rows that have their own missing-ads link.
     * Parents and "* Total" rows are left out so the chart is that same sum.
     *
     * @return array{0: list<string>, 1: list<int|null>}
     */
    private function missingBadgeHistory(int $days): array
    {
        $today = Carbon::now(self::SNAPSHOT_TIMEZONE)->startOfDay();
        $from = $today->copy()->subDays($days - 1);
        $dates = [];
        $cursor = $from->copy();
        while ($cursor->lte($today)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        $byDate = [];
        if (Schema::hasTable('advertisement_master_metric_snapshots') && $this->snapshotsHaveMissingAdsColumn()) {
            $rows = DB::table('advertisement_master_metric_snapshots')
                ->whereDate('snapshot_date', '>=', $from->toDateString())
                ->whereDate('snapshot_date', '<=', $today->toDateString())
                ->get(['snapshot_date', 'channel', 'missing_ads']);
            foreach ($rows as $row) {
                $canonical = $this->missingBadgeCanonical((string) ($row->channel ?? ''));
                if ($canonical === null) {
                    continue;
                }
                $date = $row->snapshot_date instanceof \DateTimeInterface
                    ? $row->snapshot_date->format('Y-m-d')
                    : substr((string) $row->snapshot_date, 0, 10);
                $byDate[$date][$canonical] = max(
                    $byDate[$date][$canonical] ?? 0,
                    (int) ($row->missing_ads ?? 0)
                );
            }
        }

        $series = [];
        foreach ($dates as $date) {
            if (! isset($byDate[$date])) {
                $series[] = null;
                continue;
            }
            $series[] = array_sum($byDate[$date]);
        }

        $todayKey = $today->toDateString();
        $todayIdx = array_search($todayKey, $dates, true);
        if ($todayIdx !== false) {
            $series[$todayIdx] = $this->liveMissingBadgeTotal();
        }

        return [
            array_map(fn ($d) => date('M d', strtotime($d)), $dates),
            $series,
        ];
    }

    private function liveMissingBadgeTotal(): int
    {
        $seen = [];
        $sum = 0;
        foreach ($this->missingAdsSourceMap() as $key => $source) {
            $canonical = $this->missingBadgeCanonical($key);
            if ($canonical === null || isset($seen[$canonical]) || empty($source['href'])) {
                continue;
            }
            $seen[$canonical] = true;
            $sum += (int) ($source['count'] ?? 0);
        }

        return $sum;
    }

    private function missingBadgeCanonical(string $channel): ?string
    {
        if (preg_match('/\s+Total$/i', trim($channel))) {
            return null;
        }
        $norm = $this->normalizeChannelMatchKey($channel);

        return match ($norm) {
            'amazonkw', 'amzkw' => 'amazonkw',
            'amazonpt', 'amzpt' => 'amazonpt',
            'shopifygoogleshopping' => 'shopifygoogleshopping',
            'shopifygoogleserp' => 'shopifygoogleserp',
            'shopifyyoutubeads' => 'shopifyyoutubeads',
            'shopifytiktokvideoads' => 'shopifytiktokvideoads',
            'temu', 'temu1' => 'temu',
            'temu2' => 'temu2',
            'ebay' => 'ebay',
            'ebay2' => 'ebay2',
            default => null,
        };
    }

    /**
     * Daily values already saved for this page's table. A value stays on the day it was saved.
     *
     * @return array{0: list<string>, 1: list<float|null>}
     */
    private function snapshotHistory(string $channel, string $metric, int $days): array
    {
        $today = Carbon::now(self::SNAPSHOT_TIMEZONE)->startOfDay();
        $from = $today->copy()->subDays($days - 1);
        $labels = [];
        $cursor = $from->copy();
        while ($cursor->lte($today)) {
            $labels[] = $cursor->toDateString();
            $cursor->addDay();
        }

        $byDate = [];
        $ssalesByDate = [];
        if (Schema::hasTable('advertisement_master_metric_snapshots')) {
            $names = $this->historyIsAllChannels($channel) ? null : $this->snapshotChannelNames($channel);
            $cols = ['snapshot_date', 'channel', 'spend', 'clicks', 'sold', 'sales', 'active'];
            if ($this->snapshotsHaveMissingAdsColumn()) {
                $cols[] = 'missing_ads';
            }
            $query = DB::table('advertisement_master_metric_snapshots')
                ->whereDate('snapshot_date', '>=', $from->toDateString())
                ->whereDate('snapshot_date', '<=', $today->toDateString());
            if ($names !== null) {
                $query->whereIn('channel', $names);
            }
            foreach ($query->get($cols) as $row) {
                $name = (string) ($row->channel ?? '');
                $date = $row->snapshot_date instanceof \DateTimeInterface
                    ? $row->snapshot_date->format('Y-m-d')
                    : substr((string) $row->snapshot_date, 0, 10);
                if ($name === self::SSALES_CHANNEL) {
                    $ssalesByDate[$date] = (float) ($row->sales ?? 0);
                    continue;
                }
                if ($this->isActiveChannelSnapshot($name)) {
                    continue;
                }
                if ($names === null && ($name === '' || str_contains($name, self::SUBROW_SEPARATOR))) {
                    continue;
                }
                if (! isset($byDate[$date])) {
                    $byDate[$date] = [
                        'spend' => 0.0,
                        'clicks' => 0.0,
                        'sold' => 0.0,
                        'sales' => 0.0,
                        'active' => 0.0,
                        'missing_ads' => 0.0,
                    ];
                }
                $byDate[$date]['spend'] += (float) ($row->spend ?? 0);
                $byDate[$date]['clicks'] += (float) ($row->clicks ?? 0);
                $byDate[$date]['sold'] += (float) ($row->sold ?? 0);
                $byDate[$date]['sales'] += (float) ($row->sales ?? 0);
                $byDate[$date]['active'] += (float) ($row->active ?? 0);
                $byDate[$date]['missing_ads'] += (float) ($row->missing_ads ?? 0);
            }
        }

        $built = $this->buildMetricSeries($byDate, $labels, $ssalesByDate);
        if ($metric === 'ssales') {
            $series = array_map(
                fn ($d) => array_key_exists($d, $ssalesByDate) ? round($ssalesByDate[$d], 2) : null,
                $labels
            );
        } else {
            $series = $built[$metric] ?? array_fill(0, count($labels), null);
        }

        return [
            array_map(fn ($d) => date('M d', strtotime($d)), $labels),
            $series,
        ];
    }

    /**
     * @return list<string>
     */
    private function snapshotChannelNames(string $channel): array
    {
        $channel = trim($channel);
        $key = strtolower((string) preg_replace('/[^a-z0-9]+/', '', $channel));

        return match ($key) {
            'ebaytotal' => ['eBay', 'eBay 2', 'eBay 3'],
            'tiktoktotal' => ['TikTok 1', 'TikTok'],
            'temutotal' => ['Temu', 'Temu 1', 'Temu 2'],
            default => [$this->stripTotalSuffix($channel)],
        };
    }

    /**
     * @param  array<string, array<string, float>>  $byDate
     * @param  array<int, string>  $labels
     * @param  array<string, float>  $ssalesByDate  date => combined S Sales (for TCOS)
     * @return array<string, array<int, float|null>>
     */
    private function buildMetricSeries(array $byDate, array $labels, array $ssalesByDate = []): array
    {
        $series = ['spend' => [], 'clicks' => [], 'sold' => [], 'sales' => [], 'active' => [], 'cvr' => [], 'acos' => [], 'tcos' => [], 'missing_ads' => []];
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
                $series['missing_ads'][] = null;
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
            // TCOS = Spend / S Sales (combined store net sales).
            $ss = $ssalesByDate[$d] ?? 0;
            $series['tcos'][]   = $ss > 0
                ? round(($m['spend'] / $ss) * 100, 0)
                : ($m['spend'] > 0 ? 100 : 0);
            $series['missing_ads'][] = (int) round($m['missing_ads'] ?? 0);
        }

        return $series;
    }

    /**
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     * @param  array<int, string>  $labels
     * @param  array<string, float>  $ssalesByDate
     * @return array<string, array<string, array<int, float|null>>>
     */
    private function buildChannelSeries(array $byChannel, array $labels, array $ssalesByDate = []): array
    {
        $out = [];
        foreach ($byChannel as $channel => $perDay) {
            $bd = [];
            foreach ($labels as $d) {
                if (isset($perDay[$d])) {
                    $bd[$d] = $perDay[$d];
                }
            }
            $out[$channel] = $this->buildMetricSeries($bd, $labels, $ssalesByDate);
        }

        return $out;
    }

    /**
     * Type-column link: channel ads page when one exists, otherwise analytics.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function attachChannelHrefs(array &$rows): void
    {
        foreach ($rows as &$row) {
            $href = $this->resolveChannelHref($row);
            if ($href) {
                $row['href'] = $href;
            }
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->attachChannelHrefs($row['_children']);
            }
        }
        unset($row);
    }

    /**
     * Attach saved R/N flags. Missing keys default to REQ (green).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function attachNrReqs(array &$rows): void
    {
        $map = $this->nrReqMap();
        $this->attachNrReqsWalk($rows, $map);
    }

    /**
     * @return array<string, string>
     */
    private function nrReqMap(): array
    {
        if (! Schema::hasTable('advertisement_master_nr_reqs')) {
            return [];
        }

        $map = [];
        foreach (AdvertisementMasterNrReq::query()->get(['channel_key', 'nr_req']) as $row) {
            $key = trim((string) $row->channel_key);
            if ($key === '') {
                continue;
            }
            $map[$key] = strtoupper((string) $row->nr_req) === 'NR' ? 'NR' : 'REQ';
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $map
     */
    private function attachNrReqsWalk(array &$rows, array $map): void
    {
        foreach ($rows as &$row) {
            $key = trim((string) ($row['channel_key'] ?? $row['channel'] ?? ''));
            $row['nr_req'] = ($key !== '' && ($map[$key] ?? '') === 'NR') ? 'NR' : 'REQ';
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->attachNrReqsWalk($row['_children'], $map);
            }
        }
        unset($row);
    }

    /**
     * Listing views from Channel Master. Parent totals sum children when
     * those children have views; otherwise the parent uses its own channel views.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function attachViews(array &$rows): void
    {
        $this->attachViewsWalk($rows, $this->channelViewsMap());
    }

    /**
     * @return array<string, int>
     */
    private function channelViewsMap(): array
    {
        if (! Schema::hasTable('channel_master_calculated_data')) {
            return [];
        }

        $map = [];
        try {
            foreach (ChannelMasterCalculatedData::query()->get(['channel', 'total_views']) as $row) {
                $norm = $this->normalizeChannelMatchKey((string) ($row->channel ?? ''));
                if ($norm === '') {
                    continue;
                }
                $views = (float) ($row->total_views ?? 0);
                // Same as Channel Master / All Marketplace Master: Reverb views ÷ 100.
                if ($norm === 'reverb') {
                    $views = $views / 100;
                }
                $map[$norm] = $views > 0 ? (int) round($views) : 0;
            }
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master views lookup failed: '.$e->getMessage());
        }

        return $map;
    }

    /**
     * Channel store sales — same L30 Sales figure as All Marketplace Master
     * "Sales" (channel_master_calculated_data.l30_sales).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function attachTSales(array &$rows): void
    {
        $this->attachTSalesWalk($rows, $this->channelL30SalesMap());
    }

    /**
     * Yellow total rows: ACOS = Spend / Total Sales (T Sales / AMM Sales).
     * Type rows keep Spend / Ads Sales. TCOS on totals uses the same
     * store-sales denominator (Spend / T Sales).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, float>>  $prevByChannel
     */
    private function attachTotalRowAcos(array &$rows, array $prevByChannel = []): void
    {
        foreach ($rows as &$row) {
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->attachTotalRowAcos($row['_children'], $prevByChannel);
            }

            $isTotal = ! empty($row['is_sum_row'])
                || ! empty($row['is_group_total'])
                || (! empty($row['_children']) && is_array($row['_children']));
            if (! $isTotal) {
                continue;
            }
            if ($this->shouldHideTypeChannelMetrics($row)) {
                $this->clearTcos($row);
                continue;
            }

            $spend = (float) ($row['spend'] ?? 0);
            $totalSales = (float) ($row['t_sales'] ?? 0);
            $row['acos'] = $totalSales > 0
                ? round(($spend / $totalSales) * 100, 1)
                : ($spend > 0 ? 100.0 : 0.0);
            $isAmazonChannel = ! $this->isAdTypeRow($row)
                && strtolower((string) ($row['marketplace'] ?? '')) === 'amazon';
            // Amazon TCOS uses Active Channel L30 (T Sales). Other wrapped
            // totals (eBay / Temu) get TCOS from T Sales when not already set.
            if (empty($row['has_tcos']) || $isAmazonChannel) {
                $this->applyTcos($row, $totalSales);
            }

            $channel = (string) ($row['channel_key'] ?? $row['channel'] ?? '');
            $prev = $prevByChannel[$channel] ?? $prevByChannel[(string) ($row['channel'] ?? '')] ?? null;
            if ($prev !== null) {
                $prevAcos = (float) ($prev['acos'] ?? 0);
                $dir = static fn (float $cur, float $was): string => $cur > $was
                    ? 'up'
                    : ($cur < $was ? 'down' : 'flat');
                $row['trend'] = array_merge($row['trend'] ?? [], [
                    'acos' => $dir((float) $row['acos'], $prevAcos),
                ]);
            }
        }
        unset($row);
    }

    /**
     * @return array<string, float>
     */
    private function channelL30SalesMap(): array
    {
        if (! Schema::hasTable('channel_master_calculated_data')) {
            return [];
        }

        $map = [];
        try {
            foreach (ChannelMasterCalculatedData::query()->get(['channel', 'l30_sales']) as $row) {
                $norm = $this->normalizeChannelMatchKey((string) ($row->channel ?? ''));
                if ($norm === '') {
                    continue;
                }
                $map[$norm] = (float) ($row->l30_sales ?? 0);
            }
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master T Sales lookup failed: '.$e->getMessage());
        }

        $amazonSales = $this->amazonNetSales();
        if ($amazonSales > 0) {
            $map['amazon'] = $amazonSales;
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, float>  $map
     */
    private function attachTSalesWalk(array &$rows, array $map): float
    {
        $sum = 0.0;
        foreach ($rows as &$row) {
            $childChannelSum = 0.0;
            $childHasChannelSales = false;
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->attachTSalesWalk($row['_children'], $map);
                foreach ($row['_children'] as $child) {
                    if (empty($child['has_t_sales']) || $this->isAdTypeRow($child)) {
                        continue;
                    }
                    $childHasChannelSales = true;
                    $childChannelSum += (float) ($child['t_sales'] ?? 0);
                }
            }

            if ($this->shouldHideTypeChannelMetrics($row)) {
                $row['t_sales'] = 0.0;
                $row['has_t_sales'] = false;
            } else {
                $own = $this->lookupRowTSales($row, $map);
                if ($childHasChannelSales) {
                    $row['t_sales'] = $childChannelSum;
                    $row['has_t_sales'] = true;
                } elseif ($own !== null) {
                    $row['t_sales'] = $own;
                    $row['has_t_sales'] = true;
                } else {
                    $row['t_sales'] = 0.0;
                    $row['has_t_sales'] = false;
                }
            }
            $sum += (float) $row['t_sales'];
        }
        unset($row);

        return $sum;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, float>  $map
     */
    private function lookupRowTSales(array $row, array $map): ?float
    {
        $candidates = [
            (string) ($row['channel_key'] ?? ''),
            (string) ($row['channel'] ?? ''),
            (string) ($row['channel_group'] ?? ''),
            (string) ($row['marketplace'] ?? ''),
        ];

        foreach ($candidates as $name) {
            $norm = $this->normalizeChannelMatchKey($name);
            if ($norm !== '' && array_key_exists($norm, $map)) {
                return (float) $map[$norm];
            }
        }

        return null;
    }

    /**
     * Missing-ad counts from the same pages as the sidebar:
     * Ads Missing Amz, Temu 1 Missing Ads, Temu 2 Missing Ads, Missing Google Shopping / SERP,
     * YouTube Missing Ads, TikTok Missing Ads.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function attachMissingAds(array &$rows): void
    {
        $this->attachMissingAdsWalk($rows, $this->missingAdsSourceMap());
    }

    /**
     * @return array<string, array{count: int, href: ?string}>
     */
    private function missingAdsSourceMap(): array
    {
        $map = [];
        $put = function (array $keys, int $count, ?string $href) use (&$map): void {
            foreach ($keys as $key) {
                if ($key === '') {
                    continue;
                }
                $map[$key] = ['count' => $count, 'href' => $href];
            }
        };

        $safeCount = function (callable $fn): int {
            try {
                return (int) $fn();
            } catch (\Throwable $e) {
                \Log::warning('Advertisement Master missing-ads count failed: '.$e->getMessage());

                return 0;
            }
        };

        $amazonMissingHref = $this->namedHref('amazon.ads.missing');
        $amazonMissing = [];
        try {
            $amazonMissing = AmazonAdsMissingController::missingCountsByType();
        } catch (\Throwable $e) {
            $amazonMissing = ['PT' => 0, 'KW' => 0];
        }
        $amazonMissingPt = (int) ($amazonMissing['PT'] ?? 0);
        $amazonMissingKw = (int) ($amazonMissing['KW'] ?? 0);
        $put(
            ['amazon'],
            $amazonMissingPt + $amazonMissingKw,
            $amazonMissingHref
        );
        $put(
            ['amazonkw', 'amzkw'],
            $amazonMissingKw,
            $amazonMissingHref
        );
        $put(
            ['amazonpt', 'amzpt'],
            $amazonMissingPt,
            $amazonMissingHref
        );
        $put(
            ['shopifygoogleshopping'],
            $safeCount(static fn () => GoogleShoppingAdsMissingController::missingTotalCount(true)),
            $this->namedHref('google.shopping.ads.missing')
        );
        $put(
            ['shopifygoogleserp'],
            $safeCount(static fn () => GoogleSerpAdsMissingController::missingTotalCount(true)),
            $this->namedHref('google.serp.ads.missing')
        );
        $put(
            ['shopifyyoutubeads'],
            $safeCount(static fn () => GoogleYoutubeAdsMissingController::missingTotalCount()),
            $this->namedHref('google.youtube.ads.missing')
        );
        $put(
            ['shopifytiktokvideoads'],
            $safeCount(static fn () => TiktokAdsMissingController::missingTotalCount()),
            $this->namedHref('tiktok.ads.missing')
        );
        $put(
            ['temu', 'temu1'],
            $safeCount(static fn () => Temu1MissingAdsController::missingTotalCount()),
            $this->namedHref('temu.ads.missing')
        );
        $put(
            ['temu2'],
            $safeCount(static fn () => Temu2MissingAdsController::missingTotalCount()),
            $this->namedHref('temu2.ads.missing')
        );
        $put(
            ['ebay', 'ebay1'],
            $safeCount(static fn () => EbayCampaignAdsController::missingAdsTotalCount()),
            $this->namedHref('ebay.campaign.ads')
        );
        $put(
            ['ebay2'],
            $safeCount(static fn () => Ebay2CampaignAdsController::missingAdsTotalCount()),
            $this->namedHref('ebay2.campaign.ads')
        );

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array{count: int, href: ?string}>  $sources
     */
    private function attachMissingAdsWalk(array &$rows, array $sources): int
    {
        $sum = 0;
        foreach ($rows as &$row) {
            $childSum = 0;
            $childHas = false;
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $childSum = $this->attachMissingAdsWalk($row['_children'], $sources);
                foreach ($row['_children'] as $child) {
                    if (! empty($child['has_missing_ads'])) {
                        $childHas = true;
                        break;
                    }
                }
            }

            $source = $this->lookupMissingAdsSource($row, $sources);
            if ($source !== null) {
                $row['missing_ads'] = (int) $source['count'];
                $row['missing_ads_href'] = $source['href'];
                $row['has_missing_ads'] = true;
            } elseif ($childHas) {
                $row['missing_ads'] = $childSum;
                $row['missing_ads_href'] = null;
                $row['has_missing_ads'] = true;
            } else {
                $row['missing_ads'] = 0;
                $row['missing_ads_href'] = null;
                $row['has_missing_ads'] = false;
            }
            $sum += (int) $row['missing_ads'];
        }
        unset($row);

        return $sum;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array{count: int, href: ?string}>  $sources
     * @return array{count: int, href: ?string}|null
     */
    private function lookupMissingAdsSource(array $row, array $sources): ?array
    {
        // Group totals (eBay Total) must not inherit the eBay 1 source from
        // stripping "Total". They roll up child counts instead.
        if (! empty($row['is_group_total']) || ! empty($row['is_sum_row'])) {
            return null;
        }

        foreach (['channel_key', 'channel', 'source'] as $field) {
            $norm = $this->normalizeChannelMatchKey((string) ($row[$field] ?? ''));
            if ($norm !== '' && isset($sources[$norm])) {
                return $sources[$norm];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, int>  $map
     */
    private function attachViewsWalk(array &$rows, array $map): int
    {
        $sum = 0;
        foreach ($rows as &$row) {
            $childSum = 0;
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $childSum = $this->attachViewsWalk($row['_children'], $map);
            }

            $key = (string) ($row['channel_key'] ?? $row['channel'] ?? '');
            $isAdType = str_contains($key, self::SUBROW_SEPARATOR);
            if ($childSum > 0) {
                $row['views'] = $childSum;
            } elseif ($isAdType && empty($row['is_group_total'])) {
                $row['views'] = 0;
            } else {
                $row['views'] = $this->lookupRowViews($row, $map);
            }
            $sum += (int) $row['views'];
        }
        unset($row);

        return $sum;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $map
     */
    private function lookupRowViews(array $row, array $map): int
    {
        foreach (['channel_key', 'channel', 'channel_group'] as $field) {
            $norm = $this->normalizeChannelMatchKey((string) ($row[$field] ?? ''));
            if ($norm !== '' && isset($map[$norm])) {
                return (int) $map[$norm];
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveChannelHref(array $row): ?string
    {
        foreach (['channel_key', 'channel', 'channel_group'] as $field) {
            $name = trim((string) ($row[$field] ?? ''));
            if ($name === '' || str_starts_with($name, 'custom:')) {
                continue;
            }
            $href = $this->channelHrefForName($name);
            if ($href) {
                return $href;
            }
        }

        return null;
    }

    private function adsTitleFromName(string $name): string
    {
        $label = trim((string) preg_replace('/\s+Total$/i', '', trim($name)));
        if (str_contains($label, self::SUBROW_SEPARATOR)) {
            $bits = array_map('trim', explode(self::SUBROW_SEPARATOR, $label));
            $label = (string) end($bits);
        }

        return $label !== '' ? $label : 'Channel';
    }

    private function channelHrefForName(string $name): ?string
    {
        $norm = $this->normalizeChannelMatchKey($name);
        if ($norm === '') {
            return null;
        }

        $ads = $this->channelAdsHrefMap()[$norm] ?? null;
        if (is_string($ads) && $ads !== '') {
            return $ads;
        }

        if (Route::has('channel.title.ads')) {
            return route('channel.title.ads', ['channel' => $norm]).'?title='.rawurlencode($this->adsTitleFromName($name));
        }

        $analytics = $this->channelAnalyticsHrefMap()[$norm] ?? null;

        return (is_string($analytics) && $analytics !== '') ? $analytics : null;
    }

    /**
     * @return array<string, string>
     */
    private function channelAdsHrefMap(): array
    {
        static $map = null;
        if (is_array($map)) {
            return $map;
        }

        $map = array_filter([
            'amazon' => $this->namedHref('amazon.ads.all'),
            'amazonkw' => $this->namedHref('amazon.ads.all', ['search' => 'KW']),
            'amazonpt' => $this->namedHref('amazon.ads.all', ['search' => 'PT']),
            'amazonhl' => $this->namedHref('amazon.ads.all', ['source' => 'sb_reports']),
            'ebay' => $this->namedHref('ebay.campaign.ads'),
            'ebay2' => $this->namedHref('ebay2.campaign.ads'),
            'ebay3' => $this->namedHref('ebay3.campaign.ads'),
            'shopify' => $this->namedHref('shopify.ads.master'),
            'shopifyb2c' => $this->namedHref('shopify.ads.master'),
            'shopifygoogleshopping' => $this->namedHref('google.shopping.campaigns'),
            'shopifygoogleserp' => $this->namedHref('google.serp.campaigns'),
            'shopifyyoutubeads' => $this->namedHref('google.youtube.ads.campaigns'),
            'shopifytiktokvideoads' => $this->namedHref('tiktok.ads.master') ?: $this->namedHref('tiktok.video.ads'),
            'shopifyfacebook' => $this->namedHref('facebook.ads.channel'),
            'shopifyfacebookgvideo' => $this->namedHref('facebook.ads.channel.group.video'),
            'shopifyfacebookgcarousal' => $this->namedHref('facebook.ads.channel.group.carousal'),
            'shopifyfacebookpvideo' => $this->namedHref('facebook.ads.channel.parent.video'),
            'shopifyfacebookpcarousal' => $this->namedHref('facebook.ads.channel.parent.carousal'),
            'shopifyfacebookmusicstore' => $this->namedHref('music.store.ads.sheet'),
            'shopifyfacebookmusicschool' => $this->namedHref('music.school.ads.sheet'),
            'shopifyinstagram' => $this->namedHref('instagram.ads.channel'),
            'shopifyinstagramgvideo' => $this->namedHref('instagram.ads.channel.group.video'),
            'shopifyinstagramgcarousal' => $this->namedHref('instagram.ads.channel.group.carousal'),
            'shopifyinstagrampvideo' => $this->namedHref('instagram.ads.channel.parent.video'),
            'shopifyinstagrampcarousal' => $this->namedHref('instagram.ads.channel.parent.carousal'),
            'tiktok1' => $this->namedHref('tiktok1.ads.raw'),
            'tiktok2' => $this->namedHref('tiktok.gmv.ads.raw'),
            'temu' => $this->namedHref('temu.ads'),
            'temu1' => $this->namedHref('temu.ads'),
            'temu2' => $this->namedHref('temu2.ads'),
            'walmart' => $this->namedHref('walmart.running.ads'),
        ], fn ($url) => is_string($url) && $url !== '');

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function channelAnalyticsHrefMap(): array
    {
        static $map = null;
        if (is_array($map)) {
            return $map;
        }

        $map = array_filter([
            'amazon' => url('/amazon-tabulator-view'),
            'ebay' => url('/ebay-tabulator-view'),
            'ebay2' => url('/ebay2-tabulator-view'),
            'ebay3' => url('/ebay3-tabulator-view'),
            'shopify' => url('/shopify-b2c-pricing'),
            'shopifyb2c' => url('/shopify-b2c-pricing'),
            'b2b' => url('/shopify-b2b-pricing'),
            'shopifyb2b' => url('/shopify-b2b-pricing'),
            'tiktok1' => $this->namedHref('tiktok.pricing'),
            'tiktok2' => $this->namedHref('tiktok2.pricing'),
            'temu' => $this->namedHref('newtemuone.index'),
            'temu1' => $this->namedHref('newtemuone.index'),
            'temu2' => $this->namedHref('newtemutwo.index'),
            'temu3' => $this->namedHref('temu3.decrease'),
            'aliexpress' => $this->namedHref('aliexpress.pricing.view'),
            'bestbuyusa' => $this->namedHref('bestbuy.pricing'),
            'bestbuy' => $this->namedHref('bestbuy.pricing'),
            'depop' => $this->namedHref('depop.pricing'),
            'doba' => url('/doba-tabulator'),
            'faire' => $this->namedHref('faire.pricing.view'),
            'fbmarketplace' => $this->namedHref('fb.marketplace.tabulator.view'),
            'facebookmarketplace' => $this->namedHref('fb.marketplace.tabulator.view'),
            'instagramshop' => $this->namedHref('zero.instagramshop'),
            'macys' => $this->namedHref('macys.pricing'),
            'macy' => $this->namedHref('macys.pricing'),
            'mercariwship' => $this->namedHref('mercari.wship.tabulator.view'),
            'mercariwithship' => $this->namedHref('mercari.wship.tabulator.view'),
            'mercariwoship' => $this->namedHref('mercari.woship.tabulator.view'),
            'mercariwithoutship' => $this->namedHref('mercari.woship.tabulator.view'),
            'newegg' => $this->namedHref('newegg.pricing.view'),
            'purchasingpower' => $this->namedHref('purchasing.power.pricing'),
            'reverb' => $this->namedHref('reverb.pricing'),
            'shein' => $this->namedHref('shein.pricing.view'),
            'topdawg' => $this->namedHref('topdawg.pricing'),
            'vinted' => $this->namedHref('vinted.pricing'),
            'wayfair' => $this->namedHref('wayfair.pricing.view'),
            'pls' => $this->namedHref('pls.pricing'),
            'walmart' => $this->namedHref('walmart.sheet.upload'),
        ], fn ($url) => is_string($url) && $url !== '');

        return $map;
    }

    /**
     * @param  array<string, string>  $query
     */
    private function namedHref(string $name, array $query = []): ?string
    {
        try {
            if (! Route::has($name)) {
                return null;
            }
            $url = route($name);
        } catch (\Throwable $e) {
            return null;
        }

        if ($query === []) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
    }
}
