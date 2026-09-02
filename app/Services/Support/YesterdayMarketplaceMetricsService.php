<?php

namespace App\Services\Support;

use App\Http\Controllers\ShopifyRawDataController;
use App\Models\AmazonOrder;
use App\Models\ChannelMaster;
use App\Models\ChannelMasterCalculatedData;
use App\Models\ChannelYesterdayView;
use App\Models\DobaDailyData;
use App\Models\Ebay2Order;
use App\Models\Ebay3DailyData;
use App\Models\EbayOrder;
use App\Models\FacebookMarketplaceSale;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\Temu2Order;
use App\Models\TemuOrder;
use App\Models\Tiktok2Order;
use App\Models\TiktokOrder;
use App\Services\TemuShopifySalesService;
use App\Support\ProductMasterShipBb;
use App\Support\ProductMasterTemuShip;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 1-day or 7-day GPFT / GROI / NROI / NPFT / orders, plus Spend / ACOS from campaign ads.
 * Sales, orders, qty, spend, and Ads% use each channel's latest complete day
 * (the full day before its newest raw timestamp) — not a partial calendar yesterday.
 * L7 is that complete day plus the 6 days before it.
 */
class YesterdayMarketplaceMetricsService
{
    private const TZ = 'America/Los_Angeles';

    /** @var array<string, array{lp: float, ship: float, wt: float, temu_ship: float, ship_bb: float}>|null */
    private ?array $pmBySku = null;

    /** @var array<string, float> */
    private array $fallbackSales = [];

    /** @var array<string, string> */
    private array $adReportKeyCache = [];

    /** When true, Y Sales uses All Marketplace's latest-order-minus-1-day window. */
    private bool $alignLatestCompleteDay = false;

    /** 1 = yesterday complete day; 7 = that day plus the prior 6 days. */
    private int $windowDays = 1;

    /** @var array<string, array<string, mixed>|null> */
    private array $metricsByDayCache = [];

    /** @var array<string, mixed> */
    private array $orderCollectionCache = [];

    /** @var array<string, array<string, int>>|null */
    private ?array $viewsByDateCache = null;

    public function build(int $days = 1): array
    {
        $this->windowDays = max(1, $days);
        $this->alignLatestCompleteDay = true;
        $this->adReportKeyCache = [];
        $day = Carbon::yesterday(self::TZ)->subDay();
        $date = $day->toDateString();
        $end = $day->copy()->endOfDay();
        $start = $day->copy()->subDays($this->windowDays - 1)->startOfDay();

        $this->fallbackSales = ChannelMasterCalculatedData::query()
            ->get(['channel', 'yesterday_sales'])
            ->mapWithKeys(function ($row) {
                return [$this->key((string) $row->channel) => (float) ($row->yesterday_sales ?? 0)];
            })
            ->all();

        $channels = ChannelMaster::whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->orderBy('id')
            ->pluck('channel')
            ->filter()
            ->unique()
            ->values();

        $viewsByChannel = [];
        try {
            $viewsSvc = app(YesterdayViewsService::class);
            $viewsByChannel = $this->windowDays >= 7
                ? $viewsSvc->viewsByChannelL7()
                : $viewsSvc->viewsByChannel($date);
        } catch (\Throwable $e) {
            Log::warning('Yesterday views lookup failed: '.$e->getMessage());
        }

        $rows = [];
        foreach ($channels as $name) {
            $name = (string) $name;
            try {
                $m = $this->forChannel($name, $start, $end, $date);
            } catch (\Throwable $e) {
                Log::warning('Yesterday marketplace metrics failed for '.$name.': '.$e->getMessage());
                $m = $this->emptyMetrics();
            }
            $m['views'] = $this->viewsForChannel($name, $viewsByChannel);
            $rows[] = $this->formatRow($name, $m);
        }

        usort($rows, static fn ($a, $b) => ($b['sales'] <=> $a['sales']));

        $label = $this->windowDays >= 7
            ? $start->format('M j').' – '.$day->format('M j, Y')
            : $day->format('M j, Y');

        return [
            'date' => $date,
            'from' => $start->toDateString(),
            'to' => $date,
            'days' => $this->windowDays,
            'label' => $label,
            'rows' => $rows,
        ];
    }

    /**
     * One Pacific calendar day's reported sales for a channel_master snapshot key.
     * Returns null when this channel has no 1-day source (do not use L30 / current Y Sales).
     */
    public function salesForPacificDate(string $channelKey, string $ymd): ?float
    {
        $this->windowDays = 1;
        $this->alignLatestCompleteDay = false;
        $day = Carbon::parse($ymd, self::TZ);
        $date = $day->toDateString();
        $m = $this->forChannel(
            $channelKey,
            $day->copy()->startOfDay(),
            $day->copy()->endOfDay(),
            $date
        );
        if (! ($m['computed'] ?? false)) {
            return null;
        }

        return round((float) ($m['sales'] ?? 0), 2);
    }

    /**
     * Sum of reported sales for $days Pacific calendar days ending on $endYmd.
     */
    public function salesForPacificWindow(string $channelKey, string $endYmd, int $days = 7): ?float
    {
        $sum = 0.0;
        $any = false;
        $end = Carbon::parse($endYmd, self::TZ);
        for ($i = 0; $i < max(1, $days); $i++) {
            $v = $this->salesForPacificDate($channelKey, $end->copy()->subDays($i)->toDateString());
            if ($v === null) {
                continue;
            }
            $sum += $v;
            $any = true;
        }

        return $any ? round($sum, 2) : null;
    }

    /**
     * Full 1-day row for a Pacific calendar date (sales, orders, qty, spend, profit, views).
     * Does not shift to the latest-complete-day window. Returns null when uncomputed.
     *
     * @return array<string, mixed>|null
     */
    public function metricsForPacificDate(string $channelKey, string $ymd): ?array
    {
        $this->windowDays = 1;
        $this->alignLatestCompleteDay = false;
        $day = Carbon::parse($ymd, self::TZ);
        $date = $day->toDateString();
        $cacheKey = $this->key($channelKey).'|'.$date;
        if (array_key_exists($cacheKey, $this->metricsByDayCache)) {
            return $this->metricsByDayCache[$cacheKey];
        }

        try {
            $m = $this->forChannel(
                $channelKey,
                $day->copy()->startOfDay(),
                $day->copy()->endOfDay(),
                $date
            );
        } catch (\Throwable $e) {
            Log::warning('metricsForPacificDate failed for '.$channelKey.' '.$date.': '.$e->getMessage());
            $this->metricsByDayCache[$cacheKey] = null;

            return null;
        }

        $views = $this->storedViewsForDate($channelKey, $date);
        if ($views !== null) {
            $m['views'] = $views;
        }

        if (! ($m['computed'] ?? false) && ($m['views'] ?? null) === null) {
            $this->metricsByDayCache[$cacheKey] = null;

            return null;
        }

        $row = $this->formatRow($channelKey, $m);
        $this->metricsByDayCache[$cacheKey] = $row;

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function forChannel(string $name, Carbon $start, Carbon $end, string $date): array
    {
        return match ($this->key($name)) {
            'amazon' => $this->amazon($start, $end, $date),
            'ebay' => $this->ebay(1, $date),
            'ebay2' => $this->ebay(2, $date),
            'ebay3' => $this->ebay3($date),
            'temu' => $this->temu($start, $end, $date),
            'temu2' => $this->temu2($start, $end, $date),
            'shopify', 'shopifyb2c' => $this->shopify($start, $end),
            'doba' => $this->doba($start, $end),
            'bestbuy', 'bestbuyusa' => $this->mirakl('Best Buy USA', 'BestbuyUSA', 80, 'ship_bb', $start, $end),
            'macys', 'macysinc' => $this->mirakl("Macy's, Inc.", 'Macys', 76, 'ship', $start, $end),
            'fbmarketplace', 'facebookmarketplace' => $this->fbMarketplace($start, $end, $date),
            'tiktok', 'tiktokshop' => $this->tiktok($start, $end),
            'tiktok2', 'tiktokshop2' => $this->tiktok2($start, $end),
            'wayfair' => $this->wayfair($date),
            'shein' => $this->salesOnly($this->sheinSales($start, $end)),
            'aliexpress' => $this->salesOnly($this->aliexpressSales($start, $end)),
            'mercariwship' => $this->salesOnly($this->mercariSales($start, $end, true)),
            'mercariwoship' => $this->salesOnly($this->mercariSales($start, $end, false)),
            'topdawg' => $this->salesOnly($this->topdawgSales($date)),
            'depop' => $this->salesOnly($this->depopSales($date)),
            'vinted' => $this->salesOnly($this->vintedSales($date)),
            'faire' => $this->faire($start, $end),
            'purchasingpower' => $this->salesOnly($this->purchasingPowerSales($start, $end)),
            'reverb' => $this->reverb($date),
            default => $this->fallback($name),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function amazon(Carbon $start, Carbon $end, string $date): array
    {
        $latest = DB::table('amazon_orders')->whereNotNull('order_date')->max('order_date');
        $window = $this->latestCompleteDay($latest, 'to_pacific');
        if ($window !== null) {
            [$start, $end, $date] = $window;
        }

        $yStartUtc = $start->copy()->utc();
        $yEndUtc = $end->copy()->utc();

        $displaySales = (float) AmazonOrder::productSalesByOrderDate($yStartUtc, $yEndUtc);

        $orderRows = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $yStartUtc)
            ->where('o.order_date', '<=', $yEndUtc)
            ->where(function ($q) {
                $q->whereNull('o.status')
                    ->orWhereNotIn('o.status', ['Canceled', 'Cancelled']);
            })
            ->select(['o.amazon_order_id', 'i.sku', 'i.quantity', 'i.price as line_price'])
            ->get();

        $qty = 0;
        $pft = 0.0;
        $cogs = 0.0;
        $skuLineSales = 0.0;
        $orderIds = [];

        foreach ($orderRows as $row) {
            $quantity = (int) $row->quantity;
            $linePrice = (float) $row->line_price;
            $lineRevenue = AmazonOrder::salesTotalMode() === AmazonOrder::SALES_TOTAL_MODE_QTY_TIMES_PRICE
                ? $quantity * $linePrice
                : $linePrice;
            $unitPrice = $quantity > 0 ? $lineRevenue / $quantity : 0.0;
            $sku = (string) ($row->sku ?? '');
            $qty += $quantity;
            if ($row->amazon_order_id) {
                $orderIds[(string) $row->amazon_order_id] = true;
            }
            if ($sku !== '' && $quantity > 0) {
                $skuLineSales += round($lineRevenue, 2);
            }

            $pm = $this->pm($sku);
            $shipCost = $this->shipCost($pm['ship'], $pm['wt'], $quantity);
            $cogs += round($pm['lp'] * $quantity, 2);
            $pft += round((($unitPrice * 0.80) - $pm['lp'] - $shipCost) * $quantity, 2);
        }

        $ads = $this->amazonAdMetrics($date);

        return $this->pack($displaySales, $skuLineSales, $pft, $cogs, $ads['spend'], count($orderIds), $qty, $skuLineSales, $ads['sales']);
    }

    /**
     * @return array<string, mixed>
     */
    private function ebay(int $which, string $date): array
    {
        $model = $which === 1 ? EbayOrder::class : Ebay2Order::class;
        $latest = $model::where('period', 'l30')->whereNotNull('order_date')->max('order_date');
        $window = $this->latestCompleteDay($latest, 'to_pacific');
        if ($window !== null) {
            $date = $window[2];
        }

        $cacheKey = 'ebay_orders_'.$which;
        if (! isset($this->orderCollectionCache[$cacheKey])) {
            $this->orderCollectionCache[$cacheKey] = $model::with('items')->where('period', 'l30')->get();
        }
        $orders = $this->orderCollectionCache[$cacheKey];

        $orderSales = 0.0;
        $merch = 0.0;
        $pft = 0.0;
        $cogs = 0.0;
        $qty = 0;
        $orderCount = 0;

        foreach ($orders as $order) {
            $raw = is_array($order->raw_data) ? $order->raw_data : json_decode((string) $order->raw_data, true);
            if (! is_array($raw)) {
                continue;
            }
            $cs = $raw['cancelStatus']['cancelState'] ?? '';
            $ps = $raw['orderPaymentStatus'] ?? '';
            if ($cs === 'CANCELED' || $ps === 'FULLY_REFUNDED') {
                continue;
            }
            $created = $raw['creationDate'] ?? $order->order_date;
            if (! $created) {
                continue;
            }
            $createdYmd = Carbon::parse($created)->setTimezone(self::TZ)->toDateString();
            if (! $this->ymdInCompleteWindow($createdYmd, $date)) {
                continue;
            }

            $base = (float) ($raw['pricingSummary']['total']['value'] ?? 0);
            $car = 0.0;
            foreach (($raw['lineItems'] ?? []) as $li) {
                foreach (($li['ebayCollectAndRemitTaxes'] ?? []) as $t) {
                    $car += (float) ($t['amount']['value'] ?? 0);
                }
            }
            $orderTotal = round($base + $car, 2);
            if ($orderTotal <= 0) {
                $orderTotal = round((float) ($order->total_amount ?? 0), 2);
            }
            $orderSales += $orderTotal;
            $orderCount++;

            foreach ($order->items as $item) {
                $quantity = (float) ($item->quantity ?? 0);
                $price = (float) ($item->price ?? 0);
                $unitPrice = $quantity > 0 ? $price / $quantity : 0.0;
                $pm = $this->pm((string) ($item->sku ?? ''));
                $shipCost = $this->shipCost($pm['ship'], $pm['wt'], $quantity);
                $merch += $quantity * $unitPrice;
                $qty += (int) $quantity;
                $cogs += $pm['lp'] * $quantity;
                $pft += (($unitPrice * 0.85) - $pm['lp'] - $shipCost) * $quantity;
            }
        }

        $ads = $this->ebayAdMetrics($which, $date);

        return $this->pack($orderSales, $merch, $pft, $cogs, $ads['spend'], $orderCount, $qty, $orderSales, $ads['sales']);
    }

    /**
     * @return array<string, mixed>
     */
    private function ebay3(string $date): array
    {
        $latest = Ebay3DailyData::where('period', 'l30')->whereNotNull('creation_date')->max('creation_date');
        $window = $this->latestCompleteDay($latest, 'naive');
        if ($window !== null) {
            $date = $window[2];
        }

        if (! isset($this->orderCollectionCache['ebay3_l30'])) {
            $this->orderCollectionCache['ebay3_l30'] = Ebay3DailyData::where('period', 'l30')->get();
        }
        $rows = $this->orderCollectionCache['ebay3_l30'];
        $byOrder = [];
        $merch = 0.0;
        $pft = 0.0;
        $cogs = 0.0;
        $qty = 0;

        foreach ($rows as $row) {
            if (($row->cancel_status ?? '') === 'CANCELED' || ($row->order_payment_status ?? '') === 'FULLY_REFUNDED') {
                continue;
            }
            $day = $row->creation_date ? Carbon::parse($row->creation_date)->format('Y-m-d') : null;
            if ($day === null || ! $this->ymdInCompleteWindow($day, $date)) {
                continue;
            }
            $oid = (string) $row->order_id;
            if (! isset($byOrder[$oid])) {
                $byOrder[$oid] = ['total_price' => (float) ($row->total_price ?? 0), 'car' => 0.0];
            }
            $byOrder[$oid]['car'] += (float) ($row->ebay_collect_and_remit_tax ?? 0);

            $quantity = (float) ($row->quantity ?? 0);
            $lineTotal = (float) ($row->unit_price ?? 0);
            $unitPrice = $quantity > 0 ? $lineTotal / $quantity : 0.0;
            $pm = $this->pm((string) ($row->sku ?? ''));
            $shipCost = $this->shipCost($pm['ship'], $pm['wt'], $quantity);
            $merch += $lineTotal;
            $qty += (int) $quantity;
            $cogs += $pm['lp'] * $quantity;
            $pft += (($unitPrice * 0.85) - $pm['lp'] - $shipCost) * $quantity;
        }

        $orderSales = 0.0;
        foreach ($byOrder as $v) {
            $orderSales += round($v['total_price'] + $v['car'], 2);
        }

        $ads = $this->ebayAdMetrics(3, $date);

        return $this->pack($orderSales, $merch, $pft, $cogs, $ads['spend'], count($byOrder), $qty, $orderSales, $ads['sales']);
    }

    /**
     * @return array<string, mixed>
     */
    private function temu(Carbon $start, Carbon $end, string $date): array
    {
        $latest = TemuOrder::query()->whereNotNull('parent_order_time')->max('parent_order_time');
        $window = $this->latestCompleteDay($latest, 'to_pacific');
        if ($window !== null) {
            [$start, $end, $date] = $window;
        }

        $m = TemuShopifySalesService::computeMetricsFromOrders($start, $end);
        $ads = $this->temuAdMetrics('temu_campaign_reports', $date);

        return $this->pack(
            (float) $m['base_sales'],
            (float) $m['sales'],
            (float) $m['pft'],
            (float) $m['cogs'],
            $ads['spend'],
            (int) $m['orders'],
            (int) $m['qty'],
            (float) $m['sales'],
            $ads['sales']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function temu2(Carbon $start, Carbon $end, string $date): array
    {
        $latest = Temu2Order::query()->whereNotNull('parent_order_time')->max('parent_order_time');
        $window = $this->latestCompleteDay($latest, 'to_pacific');
        if ($window !== null) {
            [$start, $end, $date] = $window;
        }

        $m = TemuShopifySalesService::computeMetricsFromOrders($start, $end, true);
        $ads = $this->temuAdMetrics('temu2_campaign_reports', $date);

        return $this->pack(
            (float) $m['base_sales'],
            (float) $m['sales'],
            (float) $m['pft'],
            (float) $m['cogs'],
            $ads['spend'],
            (int) $m['orders'],
            (int) $m['qty'],
            (float) $m['sales'],
            $ads['sales']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function shopify(Carbon $start, Carbon $end): array
    {
        if (! Schema::hasTable('shopify_raw_orders')) {
            return $this->fallback('shopify');
        }

        $latest = DB::table('shopify_raw_orders')->whereNotNull('order_date')->max('order_date');
        $window = $this->latestCompleteDay($latest, 'as_pacific');
        if ($window !== null) {
            [$start, $end] = $window;
        }

        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $base = DB::table('shopify_raw_orders')
            ->where('order_date', '>=', $startDate)
            ->where('order_date', '<=', $endDate);
        foreach (ShopifyRawDataController::EXCLUDE_SOURCES as $term) {
            $base->whereRaw('LOWER(COALESCE(source_name,"")) NOT LIKE ?', ['%'.strtolower($term).'%'])
                ->whereRaw('LOWER(COALESCE(tags,"")) NOT LIKE ?', ['%'.strtolower($term).'%']);
        }
        $base->where(function ($q) {
            $q->whereNull('sku')->orWhere('sku', 'NOT LIKE', '%XYZ%');
        });

        $rows = (clone $base)->select(['sku', 'quantity', 'net_sales', 'order_id'])->get();
        $sales = (float) $rows->sum('net_sales');
        $qty = (int) $rows->sum('quantity');
        $orders = $rows->pluck('order_id')->filter()->unique()->count();

        $margin = ShopifyRawDataController::SHOPIFY_GROSS_MARGIN;
        $pft = 0.0;
        $cogs = 0.0;
        foreach ($rows as $r) {
            $q = (int) ($r->quantity ?? 0);
            $ns = (float) ($r->net_sales ?? 0);
            $pm = $this->pm((string) ($r->sku ?? ''));
            $shipCost = $this->shipCost($pm['ship'], $pm['wt'], $q);
            $cogs += $pm['lp'] * $q;
            $pft += ($ns * $margin) - ($pm['lp'] * $q) - ($shipCost * $q);
        }

        $ads = $this->shopifyGoogleAdMetrics($startDate);

        return $this->pack($sales, $sales, $pft, $cogs, $ads['spend'], $orders, $qty, $sales, $ads['sales']);
    }

    /**
     * @return array<string, mixed>
     */
    private function doba(Carbon $start, Carbon $end): array
    {
        $cancelled = ['Cancelled', 'Canceled', 'cancelled', 'canceled', 'CANCELLED', 'CANCELED'];
        $latest = DobaDailyData::query()
            ->where(function ($q) use ($cancelled) {
                $q->whereNull('order_status')->orWhereNotIn('order_status', $cancelled);
            })
            ->max('order_time');
        $window = $this->latestCompleteDay($latest, 'to_pacific');
        if ($window !== null) {
            [$start, $end] = $window;
        }

        $rows = DobaDailyData::query()
            ->where('order_time', '>=', $start)
            ->where('order_time', '<=', $end)
            ->where(function ($q) use ($cancelled) {
                $q->whereNull('order_status')->orWhereNotIn('order_status', $cancelled);
            })
            ->get();

        $sales = 0.0;
        $pft = 0.0;
        $cogs = 0.0;
        $qty = 0;
        $orders = [];
        $margin = 0.95;

        foreach ($rows as $order) {
            if (! $order->sku) {
                continue;
            }
            $quantity = (int) ($order->quantity ?? 1);
            $itemPrice = (float) ($order->item_price ?? 0);
            $totalPrice = (float) ($order->total_price ?? 0);
            $pm = $this->pm((string) $order->sku);
            $sales += $totalPrice;
            $qty += $quantity;
            $orders[(string) ($order->order_no ?: $order->id)] = true;
            $cogs += $pm['lp'] * $quantity;
            $pft += (($itemPrice * $margin) - $pm['ship'] - $pm['lp']) * $quantity;
        }

        return $this->pack($sales, $sales, $pft, $cogs, 0.0, count($orders), $qty, $sales);
    }

    /**
     * @return array<string, mixed>
     */
    private function mirakl(string $channelName, string $pctName, float $defaultPct, string $shipKey, Carbon $start, Carbon $end): array
    {
        if (! Schema::hasTable('mirakl_daily_data')) {
            return $this->fallback($channelName);
        }

        $latest = DB::table('mirakl_daily_data')
            ->where('channel_name', $channelName)
            ->where('status', '!=', 'CLOSED')
            ->max('order_created_at');
        $window = $this->latestCompleteDay($latest, 'to_pacific');
        if ($window !== null) {
            [$start, $end] = $window;
        }

        $rows = DB::table('mirakl_daily_data')
            ->where('channel_name', $channelName)
            ->where('order_created_at', '>=', $start)
            ->where('order_created_at', '<=', $end)
            ->where('status', '!=', 'CLOSED')
            ->get();

        $mp = MarketplacePercentage::where('marketplace', $pctName)->first();
        $margin = (($mp && $mp->percentage !== null) ? (float) $mp->percentage : $defaultPct) / 100;

        $sales = 0.0;
        $pft = 0.0;
        $cogs = 0.0;
        $qty = 0;
        $orders = [];

        foreach ($rows as $order) {
            if (empty($order->sku) || empty($order->order_id)) {
                continue;
            }
            $quantity = (int) ($order->quantity ?? 1);
            if ($quantity <= 0) {
                continue;
            }
            $unitPrice = (float) ($order->unit_price ?? 0);
            $saleAmount = $unitPrice * $quantity;
            $pm = $this->pm((string) $order->sku);
            $ship = $shipKey === 'ship_bb' ? $pm['ship_bb'] : $pm['ship'];
            $shipCost = $this->shipCost($ship, $pm['wt'], $quantity);
            $sales += $saleAmount;
            $qty += $quantity;
            $orders[(string) $order->order_id] = true;
            $cogs += $pm['lp'] * $quantity;
            $pft += (($unitPrice * $margin) - $pm['lp'] - $shipCost) * $quantity;
        }

        return $this->pack($sales, $sales, $pft, $cogs, 0.0, count($orders), $qty, $sales);
    }

    /**
     * @return array<string, mixed>
     */
    private function fbMarketplace(Carbon $start, Carbon $end, string $date): array
    {
        if (! Schema::hasTable('facebook_marketplace_sales')) {
            return $this->fallback('fbmarketplace');
        }

        $latest = FacebookMarketplaceSale::query()
            ->selectRaw('MAX(COALESCE(order_date, created_at)) as latest')
            ->value('latest');
        $window = $this->latestCompleteDay($latest, 'to_pacific');
        if ($window !== null) {
            [$start, $end, $date] = $window;
        }

        $rangeStartUtc = $start->copy()->utc();
        $rangeEndUtc = $end->copy()->utc();
        $mpRow = MarketplacePercentage::where('marketplace', 'FB Marketplace')->first()
            ?: MarketplacePercentage::where('marketplace', 'FBMarketplace')->first();
        $factor = (($mpRow && $mpRow->percentage !== null) ? (float) $mpRow->percentage : 100) / 100;

        $sales = 0.0;
        $pft = 0.0;
        $cogs = 0.0;
        $qty = 0;
        $orders = [];

        FacebookMarketplaceSale::query()
            ->where(function ($q) use ($start, $end, $rangeStartUtc, $rangeEndUtc) {
                $q->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($q2) use ($rangeStartUtc, $rangeEndUtc) {
                        $q2->whereNull('order_date')
                            ->whereBetween('created_at', [
                                $rangeStartUtc->toDateTimeString(),
                                $rangeEndUtc->toDateTimeString(),
                            ]);
                    });
            })
            ->get(['sold_price', 'qty_sold', 'order_number', 'sku'])
            ->each(function ($r) use ($factor, &$sales, &$pft, &$cogs, &$qty, &$orders) {
                $lineQty = (int) $r->qty_sold;
                $price = (float) $r->sold_price;
                if ($lineQty <= 0 || $price <= 0) {
                    return;
                }
                $pm = $this->pm((string) $r->sku);
                $sales += $price * $lineQty;
                $qty += $lineQty;
                $cogs += $pm['lp'] * $lineQty;
                $pft += (($price * $factor) - $pm['lp']) * $lineQty;
                $orderNo = trim((string) ($r->order_number ?? ''));
                if ($orderNo !== '') {
                    $orders[$orderNo] = true;
                }
            });

        return $this->pack($sales, $sales, $pft, $cogs, 0.0, count($orders), $qty, $sales);
    }

    /**
     * @return array<string, mixed>
     */
    private function tiktok(Carbon $start, Carbon $end): array
    {
        if ($this->alignLatestCompleteDay) {
            $latest = TiktokOrder::latestCreatedAt();
            $window = $this->latestCompleteDay($latest ? $latest->toDateTimeString() : null, 'to_pacific');
            if ($window !== null) {
                [$start, $end] = $window;
            }
        }

        $items = TiktokOrder::linesInWindow($start, $end);
        $sales = 0.0;
        $pft = 0.0;
        $cogs = 0.0;
        $qty = 0;
        $orders = [];
        $margin = 0.80;

        foreach ($items as $item) {
            $quantity = (int) ($item->quantity ?? 1);
            if ($quantity <= 0) {
                continue;
            }
            $unitPrice = (float) ($item->sale_price ?? 0);
            $pm = $this->pm((string) ($item->seller_sku ?? ''));
            $shipCost = $this->shipCost($pm['ship'], $pm['wt'], $quantity);
            $sales += $unitPrice * $quantity;
            $qty += $quantity;
            if (! empty($item->order_id)) {
                $orders[(string) $item->order_id] = true;
            }
            $cogs += $pm['lp'] * $quantity;
            $pft += (($unitPrice * $margin) - $pm['lp'] - $shipCost) * $quantity;
        }

        return $this->pack($sales, $sales, $pft, $cogs, 0.0, count($orders), $qty, $sales);
    }

    /**
     * @return array<string, mixed>
     */
    private function wayfair(string $date): array
    {
        if (! Schema::hasTable('wayfair_daily_data')) {
            return $this->fallback('wayfair');
        }

        $latest = DB::table('wayfair_daily_data')->whereNotNull('po_date')->max('po_date');
        $window = $this->latestCompleteDay($latest, 'to_pacific');
        if ($window !== null) {
            $date = $window[2];
        }

        [$from, $to] = $this->windowYmdBounds($date);
        $rows = DB::table('wayfair_daily_data')
            ->whereDate('po_date', '>=', $from)
            ->whereDate('po_date', '<=', $to)
            ->where('quantity', '>', 0)
            ->get();

        $mp = MarketplacePercentage::where('marketplace', 'Wayfair')->first();
        $margin = (($mp && $mp->percentage !== null) ? (float) $mp->percentage : 80) / 100;

        $sales = 0.0;
        $pft = 0.0;
        $cogs = 0.0;
        $qty = 0;
        $orders = [];

        foreach ($rows as $row) {
            $quantity = (int) ($row->quantity ?? 0);
            $unitPrice = (float) ($row->unit_price ?? 0);
            if ($quantity <= 0) {
                continue;
            }
            $pm = $this->pm((string) ($row->sku ?? ''));
            $shipCost = $this->shipCost($pm['ship'], $pm['wt'], $quantity);
            $sales += $unitPrice * $quantity;
            $qty += $quantity;
            $oid = (string) ($row->po_number ?? $row->order_id ?? $row->id ?? '');
            if ($oid !== '') {
                $orders[$oid] = true;
            }
            $cogs += $pm['lp'] * $quantity;
            $pft += (($unitPrice * $margin) - $pm['lp'] - $shipCost) * $quantity;
        }

        return $this->pack($sales, $sales, $pft, $cogs, 0.0, count($orders), $qty, $sales);
    }

    /**
     * @return array<string, mixed>
     */
    private function tiktok2(Carbon $start, Carbon $end): array
    {
        $latestPacific = Tiktok2Order::tableReady() ? Tiktok2Order::latestActiveCreatedAt() : null;
        if ($latestPacific) {
            $endDay = $latestPacific->copy()->subDay();
            $end = $endDay->copy()->endOfDay();
            $start = $endDay->copy()->subDays(max(1, $this->windowDays) - 1)->startOfDay();
        } elseif (Schema::hasTable('tiktok_sales_two')) {
            $latest = DB::table('tiktok_sales_two')->whereNotNull('order_date')->max('order_date');
            $window = $this->latestCompleteDay($latest, 'to_pacific');
            if ($window !== null) {
                [$start, $end] = $window;
            }
        }

        $items = Tiktok2Order::linesInWindow($start, $end);
        $sales = 0.0;
        if (Schema::hasTable('tiktok_sales_two')) {
            $sales = (float) DB::table('tiktok_sales_two')
                ->where('order_date', '>=', $start)
                ->where('order_date', '<=', $end)
                ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as revenue')
                ->value('revenue');
        }
        $pft = 0.0;
        $cogs = 0.0;
        $qty = 0;
        $orders = [];
        $margin = 0.80;
        $lineSales = 0.0;

        foreach ($items as $item) {
            $quantity = (int) ($item->quantity ?? 1);
            if ($quantity <= 0) {
                continue;
            }
            $unitPrice = (float) ($item->sale_price ?? 0);
            $pm = $this->pm((string) ($item->seller_sku ?? ''));
            $shipCost = $this->shipCost($pm['ship'], $pm['wt'], $quantity);
            $lineSales += $unitPrice * $quantity;
            $qty += $quantity;
            if (! empty($item->order_id)) {
                $orders[(string) $item->order_id] = true;
            }
            $cogs += $pm['lp'] * $quantity;
            $pft += (($unitPrice * $margin) - $pm['lp'] - $shipCost) * $quantity;
        }

        if ($sales <= 0) {
            $sales = $lineSales;
        }

        return $this->pack($sales, $lineSales, $pft, $cogs, 0.0, count($orders), $qty, $lineSales);
    }

    /**
     * @return array<string, mixed>
     */
    private function salesOnly(float $sales): array
    {
        // Sales only — keep gpft_sales at 0 so GPFT/GROI render as "—" not 0%.
        return $this->pack($sales, 0.0, 0.0, 0.0, 0.0, 0, 0, 0.0);
    }

    private function sheinSales(Carbon $start, Carbon $end): float
    {
        if (! Schema::hasTable('shein_daily_data')) {
            return 0.0;
        }
        $latest = DB::table('shein_daily_data')->whereNotNull('order_processed_on')->max('order_processed_on');
        $window = $this->latestCompleteDay($latest, 'to_pacific');
        if ($window !== null) {
            [$start, $end] = $window;
        }
        $sum = 0.0;
        foreach (
            DB::table('shein_daily_data')
                ->where('order_processed_on', '>=', $start)
                ->where('order_processed_on', '<=', $end)
                ->cursor() as $row
        ) {
            $orderStatus = strtolower((string) ($row->order_status ?? ''));
            if (str_contains($orderStatus, 'refund') || str_contains($orderStatus, 'return')
                || str_contains($orderStatus, 'cancel') || str_contains($orderStatus, 'closed')
                || str_contains($orderStatus, 'exchange')) {
                continue;
            }
            $quantity = max(1, (int) ($row->quantity ?? 0));
            $sum += (float) ($row->product_price ?? 0) * $quantity;
        }

        return $sum;
    }

    private function aliexpressSales(Carbon $start, Carbon $end): float
    {
        if (! Schema::hasTable('aliexpress_daily_data')) {
            return 0.0;
        }
        $latest = DB::table('aliexpress_daily_data')->whereNotNull('order_date')->max('order_date');
        $window = $this->latestCompleteDay($latest, 'to_pacific');
        if ($window !== null) {
            [$start, $end] = $window;
        }
        $sum = 0.0;
        foreach (
            DB::table('aliexpress_daily_data')
                ->where('order_date', '>=', $start)
                ->where('order_date', '<=', $end)
                ->cursor() as $row
        ) {
            $status = strtolower((string) ($row->order_status ?? ''));
            if (str_contains($status, 'refund') || str_contains($status, 'return')
                || str_contains($status, 'cancel') || str_contains($status, 'closed')) {
                continue;
            }
            if (empty($row->sku_code) || empty($row->order_id)) {
                continue;
            }
            $line = (float) ($row->product_total ?? 0);
            if ($line <= 0) {
                $line = (float) ($row->supply_price ?? 0);
            }
            if ($line <= 0) {
                $line = (float) ($row->order_amount ?? 0);
            }
            $sum += $line;
        }

        return $sum;
    }

    private function mercariSales(Carbon $start, Carbon $end, bool $withShip): float
    {
        if (! Schema::hasTable('mercari_daily_data')) {
            return 0.0;
        }
        $q = DB::table('mercari_daily_data')
            ->whereNotNull('sold_date')
            ->whereNotNull('item_id')
            ->where('item_id', '!=', '')
            ->whereNull('canceled_date')
            ->where(function ($q2) {
                $q2->whereNull('order_status')
                    ->orWhereRaw('LOWER(order_status) NOT LIKE ?', ['%cancel%']);
            });
        if ($withShip) {
            $q->where(function ($q3) {
                $q3->whereNull('buyer_shipping_fee')
                    ->orWhere('buyer_shipping_fee', '=', 0)
                    ->orWhere('buyer_shipping_fee', '=', '');
            });
        } else {
            $q->where('buyer_shipping_fee', '>', 0);
        }

        $latest = (clone $q)->max('sold_date');
        $window = $this->latestCompleteDay($latest, 'to_pacific');
        if ($window !== null) {
            [$start, $end] = $window;
        }

        return (float) $q->where('sold_date', '>=', $start)
            ->where('sold_date', '<=', $end)
            ->selectRaw('COALESCE(SUM(item_price), 0) as revenue')
            ->value('revenue');
    }

    private function topdawgSales(string $date): float
    {
        if (! Schema::hasTable('topdawg_order_metrics')) {
            return 0.0;
        }

        $latest = DB::table('topdawg_order_metrics')->whereNotNull('order_date')->max('order_date');
        $window = $this->latestCompleteDay($latest, 'to_pacific');
        if ($window !== null) {
            $date = $window[2];
        }

        [$from, $to] = $this->windowYmdBounds($date);

        return (float) DB::table('topdawg_order_metrics')
            ->whereDate('order_date', '>=', $from)
            ->whereDate('order_date', '<=', $to)
            ->selectRaw('COALESCE(SUM(amount), 0) as revenue')
            ->value('revenue');
    }

    private function depopSales(string $date): float
    {
        if (! Schema::hasTable('depop_sales_data')) {
            return 0.0;
        }

        $latest = DB::table('depop_sales_data')->whereNotNull('sale_date')->max('sale_date');
        $window = $this->latestCompleteDay($latest, 'naive');
        if ($window !== null) {
            $date = $window[2];
        }

        [$from, $to] = $this->windowYmdBounds($date);

        return (float) DB::table('depop_sales_data')
            ->whereDate('sale_date', '>=', $from)
            ->whereDate('sale_date', '<=', $to)
            ->selectRaw('COALESCE(SUM(item_price * GREATEST(COALESCE(NULLIF(quantity, 0), 1), 1)), 0) as revenue')
            ->value('revenue');
    }

    private function vintedSales(string $date): float
    {
        if (! Schema::hasTable('vinted_sales_data')) {
            return 0.0;
        }

        $latest = DB::table('vinted_sales_data')->whereNotNull('sale_date')->max('sale_date');
        $window = $this->latestCompleteDay($latest, 'naive');
        if ($window !== null) {
            $date = $window[2];
        }

        [$from, $to] = $this->windowYmdBounds($date);

        return (float) DB::table('vinted_sales_data')
            ->whereDate('sale_date', '>=', $from)
            ->whereDate('sale_date', '<=', $to)
            ->selectRaw('COALESCE(SUM(item_price * GREATEST(COALESCE(NULLIF(quantity, 0), 1), 1)), 0) as revenue')
            ->value('revenue');
    }

    /**
     * Faire 1-day sales / orders / qty from shopify_raw_orders (same source as All Marketplace).
     *
     * @return array<string, mixed>
     */
    private function faire(Carbon $start, Carbon $end): array
    {
        if (! Schema::hasTable('shopify_raw_orders')) {
            return $this->salesOnly(0.0);
        }
        $faireWhere = function ($q) {
            $q->where('source_name', 'faire')
                ->orWhere('source_name', 'LIKE', '%faire%')
                ->orWhere('tags', 'LIKE', '%Faire%');
        };

        $latest = DB::table('shopify_raw_orders')
            ->where($faireWhere)
            ->whereNotNull('order_date')
            ->max('order_date');
        $window = $this->latestCompleteDay($latest, 'to_pacific');
        if ($window !== null) {
            [$start, $end] = $window;
        }

        $row = DB::table('shopify_raw_orders')
            ->where($faireWhere)
            ->where('order_date', '>=', $start)
            ->where('order_date', '<=', $end)
            ->where('quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(price * quantity), 0) as revenue')
            ->selectRaw('COALESCE(SUM(quantity), 0) as qty')
            ->selectRaw('COUNT(DISTINCT COALESCE(NULLIF(TRIM(order_number), ""), CAST(order_id AS CHAR))) as orders')
            ->first();

        $m = $this->salesOnly((float) ($row->revenue ?? 0));
        $m['qty'] = (int) ($row->qty ?? 0);
        $m['orders'] = (int) ($row->orders ?? 0);

        return $m;
    }

    private function purchasingPowerSales(Carbon $start, Carbon $end): float
    {
        try {
            $ppWhere = function ($q) {
                $q->where('source_name', 'LIKE', '%purchasing power%')
                    ->orWhere('source_name', 'LIKE', '%purchasingpower%')
                    ->orWhere('tags', 'LIKE', '%Purchasing Power%')
                    ->orWhere('tags', 'LIKE', '%PurchasingPower%');
            };

            $latest = DB::connection('apicentral')->table('shopify_order_items')
                ->where($ppWhere)
                ->whereNotNull('order_date')
                ->max('order_date');
            $window = $this->latestCompleteDay($latest, 'to_pacific');
            if ($window !== null) {
                [$start, $end] = $window;
            }

            return (float) DB::connection('apicentral')->table('shopify_order_items')
                ->where($ppWhere)
                ->where('order_date', '>=', $start)
                ->where('order_date', '<=', $end)
                ->where('quantity', '>', 0)
                ->selectRaw('COALESCE(SUM(price * quantity), 0) as revenue')
                ->value('revenue');
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function reverb(string $date): array
    {
        if (! Schema::hasTable('reverb_daily_data')) {
            return $this->salesOnly(0.0);
        }

        if ($this->alignLatestCompleteDay) {
            $date = Carbon::now('UTC')->subDay()->toDateString();
        }

        [$from, $to] = $this->windowYmdBounds($date);
        $row = DB::table('reverb_daily_data')
            ->whereDate('order_date', '>=', $from)
            ->whereDate('order_date', '<=', $to)
            ->whereRaw('LOWER(COALESCE(status, "")) NOT LIKE ?', ['%cancel%'])
            ->whereRaw('LOWER(COALESCE(status, "")) NOT LIKE ?', ['%refund%'])
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereNotNull('order_number')->where('order_number', '!=', '')
            ->selectRaw('COALESCE(SUM(COALESCE(NULLIF(amount, 0), product_subtotal, 0)), 0) as revenue')
            ->selectRaw('COALESCE(SUM(quantity), 0) as qty')
            ->selectRaw('COUNT(DISTINCT order_number) as orders')
            ->first();

        $m = $this->salesOnly((float) ($row->revenue ?? 0));
        $m['qty'] = (int) ($row->qty ?? 0);
        $m['orders'] = (int) ($row->orders ?? 0);

        return $m;
    }

    /**
     * @return array<string, mixed>
     */
    private function fallback(string $name): array
    {
        $sales = (float) ($this->fallbackSales[$this->key($name)] ?? 0);
        $m = $this->emptyMetrics();
        $m['sales'] = $sales;
        $m['computed'] = false;

        return $m;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMetrics(): array
    {
        return [
            'sales' => 0.0,
            'gpft_sales' => 0.0,
            'ads_sales' => 0.0,
            'pft' => 0.0,
            'cogs' => 0.0,
            'ad_spend' => 0.0,
            'attributed_ad_sales' => 0.0,
            'orders' => 0,
            'qty' => 0,
            'views' => null,
            'computed' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pack(
        float $sales,
        float $gpftSales,
        float $pft,
        float $cogs,
        float $adSpend,
        int $orders,
        int $qty,
        float $adsSales,
        float $attributedAdSales = 0.0
    ): array {
        return [
            'sales' => round($sales, 2),
            'gpft_sales' => round($gpftSales, 2),
            'ads_sales' => round($adsSales, 2),
            'pft' => round($pft, 2),
            'cogs' => round($cogs, 2),
            'ad_spend' => round($adSpend, 2),
            'attributed_ad_sales' => round($attributedAdSales, 2),
            'orders' => $orders,
            'qty' => $qty,
            'computed' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $m
     * @return array<string, mixed>
     */
    private function formatRow(string $name, array $m): array
    {
        $sales = (float) ($m['sales'] ?? 0);
        $gpftSales = (float) ($m['gpft_sales'] ?? 0);
        $adsSales = (float) ($m['ads_sales'] ?? $sales);
        $pft = (float) ($m['pft'] ?? 0);
        $cogs = (float) ($m['cogs'] ?? 0);
        $adSpend = (float) ($m['ad_spend'] ?? 0);
        $attributedAdSales = (float) ($m['attributed_ad_sales'] ?? 0);
        $computed = (bool) ($m['computed'] ?? false);
        $qty = (int) ($m['qty'] ?? 0);
        $views = $m['views'] ?? null;
        $views = ($views === null || $views === '') ? null : (int) $views;
        $cvr = ($views !== null && $views > 0) ? ($qty / $views) * 100 : null;

        $gpftBase = $sales > 0 ? $sales : $gpftSales;
        $adsBase = $sales > 0 ? $sales : $adsSales;
        $gpft = ($computed && $gpftBase > 0) ? ($pft / $gpftBase) * 100 : null;
        $groi = ($computed && $cogs > 0) ? ($pft / $cogs) * 100 : null;
        $adsPct = ($computed && $adsBase > 0) ? ($adSpend / $adsBase) * 100 : 0.0;
        $npft = ($gpft !== null) ? $gpft - $adsPct : null;
        $nroi = ($computed && $cogs > 0) ? (($pft - $adSpend) / $cogs) * 100 : null;
        $acos = null;
        if ($computed && ($adSpend > 0 || $attributedAdSales > 0)) {
            $acos = $attributedAdSales > 0
                ? ($adSpend / $attributedAdSales) * 100
                : 100.0;
        }

        return [
            'channel' => $name,
            'sales' => $sales,
            'gpft' => $gpft,
            'groi' => $groi,
            'nroi' => $nroi,
            'npft' => $npft,
            'ads_pct' => $adsPct,
            'acos' => $acos,
            'views' => $views,
            'cvr' => $cvr,
            'orders' => (int) ($m['orders'] ?? 0),
            'qty' => $qty,
            'pft' => $pft,
            'cogs' => $cogs,
            'ad_spend' => $adSpend,
            'attributed_ad_sales' => $attributedAdSales,
            'gpft_sales' => $gpftSales,
            'computed' => $computed,
        ];
    }

    /**
     * 1-day Amazon spend/sales from the same SP+SB campaign tables as /amazon-ads/all.
     * Uses L1 (the ads-page yesterday pull) — dated daily keys are a thin subset.
     *
     * @return array{spend: float, sales: float}
     */
    private function amazonAdMetrics(string $date): array
    {
        $spend = 0.0;
        $sales = 0.0;
        try {
            if (Schema::hasTable('amazon_sp_campaign_reports')) {
                $key = $this->adReportKey('amazon_sp_campaign_reports', 'report_date_range', $date);
                $sp = $this->amazonDistinctCampaignTotals(
                    'amazon_sp_campaign_reports',
                    $key,
                    'COALESCE(cost, spend, 0)',
                    $this->amazonDailySalesExpr('amazon_sp_campaign_reports')
                );
                $spend += $sp['spend'];
                $sales += $sp['sales'];
            }
            if (Schema::hasTable('amazon_sb_campaign_reports')) {
                $key = $this->adReportKey('amazon_sb_campaign_reports', 'report_date_range', $date);
                $sb = $this->amazonDistinctCampaignTotals(
                    'amazon_sb_campaign_reports',
                    $key,
                    'COALESCE(cost, 0)',
                    $this->amazonDailySalesExpr('amazon_sb_campaign_reports')
                );
                $spend += $sb['spend'];
                $sales += $sb['sales'];
            }
        } catch (\Throwable $e) {
            Log::warning('Yesterday Amazon ad metrics failed: '.$e->getMessage());
        }

        return ['spend' => $spend, 'sales' => $sales];
    }

    /**
     * One row per campaign_id (MAX spend/sales) so re-imported L1/daily rows
     * do not inflate totals — same distinct rule as /amazon-ads/all badges.
     *
     * @return array{spend: float, sales: float}
     */
    private function amazonDistinctCampaignTotals(string $table, string $key, string $spendExpr, string $salesExpr): array
    {
        $inner = DB::table($table)
            ->where('report_date_range', $key)
            ->where(function ($q) {
                $q->whereNull('campaignStatus')
                    ->orWhereRaw("UPPER(TRIM(campaignStatus)) != 'ARCHIVED'");
            })
            ->selectRaw('campaign_id')
            ->selectRaw('MAX('.$spendExpr.') as spend')
            ->selectRaw('MAX('.$salesExpr.') as sales')
            ->groupBy('campaign_id');

        $row = DB::query()->fromSub($inner, 'amz_ads_c')
            ->selectRaw('COALESCE(SUM(spend), 0) as spend')
            ->selectRaw('COALESCE(SUM(sales), 0) as sales')
            ->first();

        return [
            'spend' => (float) ($row->spend ?? 0),
            'sales' => (float) ($row->sales ?? 0),
        ];
    }

    private function amazonDailySalesExpr(string $table): string
    {
        $cols = Schema::getColumnListing($table);
        if (in_array('sales1d', $cols, true)) {
            return 'sales1d';
        }
        if (in_array('sales', $cols, true)) {
            return 'sales';
        }

        return '0';
    }

    /**
     * @return array{spend: float, sales: float}
     */
    private function ebayAdMetrics(int $which, string $date): array
    {
        $priority = match ($which) {
            2 => 'ebay_2_priority_reports',
            3 => 'ebay_3_priority_reports',
            default => 'ebay_priority_reports',
        };
        $general = match ($which) {
            2 => 'ebay_2_general_reports',
            3 => 'ebay_3_general_reports',
            default => 'ebay_general_reports',
        };

        $spend = 0.0;
        $sales = 0.0;
        // Never use L1. eBay 2/3 L1 is $289 / $132 from another pull;
        // pairing that with Aug 12 Y Sales ($94 / $77) would invent 300%+ Ads%.
        try {
            if (Schema::hasTable($priority)) {
                $key = $this->adReportKey($priority, 'report_range', $date);
                $row = DB::table($priority)
                    ->where('report_range', $key)
                    ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")), 0) as spend')
                    ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")), 0) as sales')
                    ->first();
                $spend += (float) ($row->spend ?? 0);
                $sales += (float) ($row->sales ?? 0);
            }
            if (Schema::hasTable($general)) {
                $key = $this->adReportKey($general, 'report_range', $date);
                $row = DB::table($general)
                    ->where('report_range', $key)
                    ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")), 0) as spend')
                    ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(sale_amount, "USD ", ""), ",", "")), 0) as sales')
                    ->first();
                $spend += (float) ($row->spend ?? 0);
                $sales += (float) ($row->sales ?? 0);
            }
        } catch (\Throwable $e) {
            Log::warning("Yesterday eBay {$which} ad metrics failed: ".$e->getMessage());
        }

        return ['spend' => $spend, 'sales' => $sales];
    }

    /**
     * @return array{spend: float, sales: float}
     */
    private function shopifyGoogleAdMetrics(string $date): array
    {
        if (! Schema::hasTable('google_ads_campaigns')) {
            return ['spend' => 0.0, 'sales' => 0.0];
        }

        try {
            $key = $this->googleAdDate($date);
            [$from, $to] = $this->windowYmdBounds($key);
            $row = DB::table('google_ads_campaigns')
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to)
                ->whereIn('advertising_channel_type', ['SHOPPING', 'SEARCH'])
                ->whereIn('campaign_status', ['ENABLED', 'PAUSED'])
                ->selectRaw('COALESCE(SUM(metrics_cost_micros), 0) / 1000000 as spend')
                ->selectRaw('COALESCE(SUM(ga4_actual_revenue), 0) as sales')
                ->first();

            return [
                'spend' => (float) ($row->spend ?? 0),
                'sales' => (float) ($row->sales ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::warning('Yesterday Shopify Google ad metrics failed: '.$e->getMessage());

            return ['spend' => 0.0, 'sales' => 0.0];
        }
    }

    /**
     * @return array{spend: float, sales: float}
     */
    private function temuAdMetrics(string $table, string $date): array
    {
        if (! Schema::hasTable($table)) {
            return ['spend' => 0.0, 'sales' => 0.0];
        }

        try {
            $key = $this->adReportKey($table, 'report_range', $date);
            $row = DB::table($table)
                ->where('report_range', $key)
                ->selectRaw('COALESCE(SUM(spend), 0) as spend')
                ->selectRaw('COALESCE(SUM(base_price_sales), 0) as sales')
                ->first();

            return [
                'spend' => (float) ($row->spend ?? 0),
                'sales' => (float) ($row->sales ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::warning("Yesterday {$table} ad metrics failed: ".$e->getMessage());

            return ['spend' => 0.0, 'sales' => 0.0];
        }
    }

    private function adReportHasDatedKey(string $table, string $column, string $date): bool
    {
        try {
            return Schema::hasTable($table) && DB::table($table)->where($column, $date)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Spend key for campaign-ads tables.
     * L7 dashboard prefers L7 (same as /amazon-ads/all and /ebay/campaign-ads L7).
     * Yesterday prefers L1 — dated daily keys are often a thin subset.
     */
    private function adReportKey(string $table, string $column, string $yesterday): string
    {
        $prefer = $this->windowDays >= 7 ? 'L7' : 'L1';
        $cacheKey = $table.'|'.$column.'|'.$yesterday.'|'.$prefer;
        if (isset($this->adReportKeyCache[$cacheKey])) {
            return $this->adReportKeyCache[$cacheKey];
        }

        $key = $yesterday;
        try {
            if (DB::table($table)->where($column, $prefer)->exists()) {
                $key = $prefer;
            } elseif ($prefer === 'L7' && DB::table($table)->where($column, 'L1')->exists()) {
                $key = 'L1';
            } elseif (DB::table($table)->where($column, $yesterday)->exists()) {
                $key = $yesterday;
            }
        } catch (\Throwable $e) {
            Log::warning("Yesterday ad report key failed for {$table}: ".$e->getMessage());
        }

        return $this->adReportKeyCache[$cacheKey] = $key;
    }

    private function googleAdDate(string $yesterday): string
    {
        return $yesterday;
    }

    /**
     * Same "latest complete day" All Marketplace Master uses for Y Sales:
     * the Pacific/calendar day before the newest raw timestamp.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}|null
     */
    private function latestCompleteDay(?string $latestRaw, string $style = 'to_pacific'): ?array
    {
        if (! $this->alignLatestCompleteDay) {
            return null;
        }
        if ($latestRaw === null || $latestRaw === '') {
            return null;
        }

        $latest = match ($style) {
            'as_pacific' => Carbon::parse($latestRaw, self::TZ),
            'naive' => Carbon::parse($latestRaw),
            default => Carbon::parse($latestRaw)->timezone(self::TZ),
        };

        $endDay = $latest->copy()->subDay();
        $end = $endDay->copy()->endOfDay();
        $start = $endDay->copy()->subDays(max(1, $this->windowDays) - 1)->startOfDay();

        return [$start, $end, $endDay->toDateString()];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function windowYmdBounds(string $endYmd): array
    {
        $end = Carbon::parse($endYmd, self::TZ);
        $start = $end->copy()->subDays(max(1, $this->windowDays) - 1);

        return [$start->toDateString(), $end->toDateString()];
    }

    private function ymdInCompleteWindow(string $ymd, string $endYmd): bool
    {
        [$from, $to] = $this->windowYmdBounds($endYmd);

        return $ymd >= $from && $ymd <= $to;
    }

    /**
     * @return array{lp: float, ship: float, wt: float, temu_ship: float, ship_bb: float}
     */
    private function pm(string $sku): array
    {
        $this->ensurePm();
        $sku = trim($sku);
        if ($sku === '') {
            return $this->emptyPm();
        }

        return $this->pmBySku[$sku]
            ?? $this->pmBySku[strtoupper($sku)]
            ?? $this->emptyPm();
    }

    private function ensurePm(): void
    {
        if ($this->pmBySku !== null) {
            return;
        }

        $this->pmBySku = [];
        foreach (ProductMaster::query()->get(['sku', 'Values']) as $pm) {
            $values = is_array($pm->Values)
                ? $pm->Values
                : (is_string($pm->Values) ? (json_decode($pm->Values, true) ?: []) : []);
            if (! is_array($values)) {
                $values = [];
            }
            $lp = 0.0;
            $ship = 0.0;
            $wt = 0.0;
            $temuShip = 0.0;
            $shipBb = 0.0;
            foreach ($values as $k => $v) {
                $lk = strtolower((string) $k);
                if ($lk === 'lp') {
                    $lp = (float) $v;
                } elseif ($lk === 'ship') {
                    $ship = (float) $v;
                } elseif ($lk === 'wt_act') {
                    $wt = (float) $v;
                } elseif ($lk === 'temu_ship') {
                    $temuShip = (float) $v;
                } elseif ($lk === 'ship_bb') {
                    $shipBb = (float) $v;
                }
            }
            $temuShip = ProductMasterTemuShip::forPricing($values, $pm);
            $shipBb = ProductMasterShipBb::forPricing($values, $pm);
            $row = ['lp' => $lp, 'ship' => $ship, 'wt' => $wt, 'temu_ship' => $temuShip, 'ship_bb' => $shipBb];
            $this->pmBySku[(string) $pm->sku] = $row;
            $this->pmBySku[strtoupper((string) $pm->sku)] = $row;
        }
    }

    /**
     * @return array{lp: float, ship: float, wt: float, temu_ship: float, ship_bb: float}
     */
    private function emptyPm(): array
    {
        return ['lp' => 0.0, 'ship' => 0.0, 'wt' => 0.0, 'temu_ship' => 0.0, 'ship_bb' => 0.0];
    }

    private function shipCost(float $ship, float $wt, float $quantity): float
    {
        if ($quantity <= 1) {
            return $ship;
        }
        $tWeight = $wt * $quantity;

        return $tWeight < 20 ? $ship / $quantity : $ship;
    }

    private function storedViewsForDate(string $name, string $ymd): ?int
    {
        if ($this->viewsByDateCache === null) {
            $this->viewsByDateCache = [];
            if (Schema::hasTable('channel_yesterday_views')) {
                foreach (ChannelYesterdayView::query()->get(['channel', 'snapshot_date', 'views']) as $row) {
                    $key = $this->key((string) $row->channel);
                    $date = $row->snapshot_date instanceof \DateTimeInterface
                        ? $row->snapshot_date->format('Y-m-d')
                        : (string) $row->snapshot_date;
                    if ($key !== '' && $date !== '') {
                        $this->viewsByDateCache[$key][$date] = (int) $row->views;
                    }
                }
            }
        }

        $key = $this->key($name);
        $lookup = match ($key) {
            'shopifyb2c', 'shopify' => 'shopify',
            default => $key,
        };

        if (! isset($this->viewsByDateCache[$lookup][$ymd])) {
            return null;
        }

        return $this->viewsByDateCache[$lookup][$ymd];
    }

    /**
     * @param  array<string, int>  $viewsByChannel
     */
    private function viewsForChannel(string $name, array $viewsByChannel): ?int
    {
        $key = $this->key($name);
        $lookup = match ($key) {
            'shopifyb2c', 'shopify' => 'shopify',
            default => $key,
        };

        if (! array_key_exists($lookup, $viewsByChannel)) {
            return null;
        }

        return (int) $viewsByChannel[$lookup];
    }

    private function key(string $name): string
    {
        $k = preg_replace('/[^a-z0-9]/', '', strtolower($name));

        return match ($k) {
            'ebaytwo', 'ebay2' => 'ebay2',
            'ebaythree', 'ebay3' => 'ebay3',
            'bestbuyusa', 'bestbuy' => 'bestbuy',
            'macys', 'macysinc' => 'macys',
            'fbmarketplace', 'facebookmarketplace' => 'fbmarketplace',
            'tiktokshop', 'tiktok' => 'tiktok',
            'tiktok2', 'tiktokshop2' => 'tiktok2',
            default => $k,
        };
    }
}
