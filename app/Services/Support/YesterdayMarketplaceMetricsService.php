<?php

namespace App\Services\Support;

use App\Http\Controllers\ShopifyRawDataController;
use App\Models\AmazonOrder;
use App\Models\ChannelMaster;
use App\Models\ChannelMasterCalculatedData;
use App\Models\DobaDailyData;
use App\Models\Ebay2Order;
use App\Models\Ebay3DailyData;
use App\Models\EbayOrder;
use App\Models\FacebookMarketplaceSale;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\Temu2DailyData;
use App\Models\TemuOrder;
use App\Models\Tiktok2Order;
use App\Models\TiktokOrder;
use App\Services\TemuShopifySalesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One-day GPFT / GROI / NROI / NPFT / orders, plus Spend / ACOS from daily raw ads.
 * Y Sales, orders, qty, spend, and Ads% use each channel's latest complete day
 * (the full day before its newest raw timestamp) — not a partial calendar yesterday.
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

    public function build(): array
    {
        $this->alignLatestCompleteDay = true;
        $day = Carbon::yesterday(self::TZ)->subDay();
        $date = $day->toDateString();
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

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
            $viewsByChannel = app(YesterdayViewsService::class)->viewsByChannel($date);
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

        return [
            'date' => $date,
            'label' => $day->format('M j, Y'),
            'rows' => $rows,
        ];
    }

    /**
     * One Pacific calendar day's reported sales for a channel_master snapshot key.
     * Returns null when this channel has no 1-day source (do not use L30 / current Y Sales).
     */
    public function salesForPacificDate(string $channelKey, string $ymd): ?float
    {
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
            'shopify' => $this->shopify($start, $end),
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
            'faire' => $this->salesOnly($this->faireSales($start, $end)),
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

        $ads = $this->amazonAdMetrics($date, false);

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

        $orders = $model::with('items')->where('period', 'l30')->get();

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
            if (Carbon::parse($created)->setTimezone(self::TZ)->toDateString() !== $date) {
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

        $rows = Ebay3DailyData::where('period', 'l30')->get();
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
            if ($day !== $date) {
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
        $normalizeSku = static function ($sku) {
            $sku = strtoupper(trim((string) $sku));
            $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
            $sku = preg_replace('/\s+/', ' ', $sku);

            return $sku;
        };

        $pmSkus = ProductMaster::query()
            ->pluck('sku')
            ->filter(fn ($sku) => stripos((string) $sku, 'PARENT') === false)
            ->unique()
            ->values();
        $normalizedPmSet = [];
        $noSpaceToNormalized = [];
        foreach ($pmSkus as $s) {
            $nk = $normalizeSku($s);
            $normalizedPmSet[$nk] = true;
            $noSpace = str_replace(' ', '', $nk);
            if ($noSpace !== '') {
                $noSpaceToNormalized[$noSpace] = $nk;
            }
        }

        $latest = Temu2DailyData::whereNotNull('purchase_date')->max('purchase_date');
        $window = $this->latestCompleteDay($latest, 'naive');
        if ($window !== null) {
            [$start, $end, $date] = $window;
        }

        $margin = TemuShopifySalesService::temu2MarginDecimal();
        $parentsBySku = ProductMaster::query()->pluck('parent', 'sku');
        $rows = Temu2DailyData::where('purchase_date', '>=', $start)
            ->where('purchase_date', '<=', $end)
            ->get();

        $baseSales = 0.0;
        $fbSales = 0.0;
        $pft = 0.0;
        $cogs = 0.0;
        $qty = 0;
        $orders = [];

        foreach ($rows as $row) {
            $rawSku = trim((string) ($row->contribution_sku ?? ''));
            if ($rawSku === '') {
                continue;
            }

            $quantity = (int) ($row->quantity_purchased ?? 0);
            $basePrice = (float) ($row->base_price_total ?? 0);
            if ($quantity <= 0 || $basePrice <= 0) {
                continue;
            }

            $pm = $this->pm($rawSku);
            $parent = (string) ($parentsBySku[$rawSku] ?? '');
            if ($parent !== '' && str_starts_with($parent, 'PARENT')) {
                continue;
            }

            // Y Sales matches All Marketplace: base price × qty, no Product Master / order_id filter.
            $baseSales += $basePrice * $quantity;

            $orderId = trim((string) ($row->order_id ?? ''));
            $normalizedRowSku = $normalizeSku($rawSku);
            $normalizedRowSkuNoSpace = str_replace(' ', '', $normalizedRowSku);
            $inPm = isset($normalizedPmSet[$normalizedRowSku]) || isset($noSpaceToNormalized[$normalizedRowSkuNoSpace]);
            if (! $inPm) {
                continue;
            }

            $fbPrice = $basePrice <= 26.99 ? $basePrice + 2.99 : $basePrice;
            $fbSales += $fbPrice * $quantity;
            $qty += $quantity;
            if ($orderId !== '') {
                $orders[$orderId] = true;
            }
            $cogs += $pm['lp'] * $quantity;
            $pft += ($fbPrice * $margin - $pm['lp'] - $pm['temu_ship']) * $quantity;
        }

        $ads = $this->temuAdMetrics('temu2_campaign_reports', $date);

        return $this->pack($baseSales, $fbSales, $pft, $cogs, $ads['spend'], count($orders), $qty, $fbSales, $ads['sales']);
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
            ->where(function ($q) use ($date, $rangeStartUtc, $rangeEndUtc) {
                $q->whereBetween('order_date', [$date, $date])
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
            if ($latest) {
                $start = $latest->copy()->subDay()->startOfDay();
                $end = $latest->copy()->subDay()->endOfDay();
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

        $rows = DB::table('wayfair_daily_data')
            ->whereDate('po_date', $date)
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
        if (Schema::hasTable('tiktok_sales_two')) {
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

        return (float) DB::table('topdawg_order_metrics')
            ->whereDate('order_date', $date)
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

        return (float) DB::table('depop_sales_data')
            ->whereDate('sale_date', $date)
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

        return (float) DB::table('vinted_sales_data')
            ->whereDate('sale_date', $date)
            ->selectRaw('COALESCE(SUM(item_price * GREATEST(COALESCE(NULLIF(quantity, 0), 1), 1)), 0) as revenue')
            ->value('revenue');
    }

    private function faireSales(Carbon $start, Carbon $end): float
    {
        if (! Schema::hasTable('shopify_raw_orders')) {
            return 0.0;
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

        return (float) DB::table('shopify_raw_orders')
            ->where($faireWhere)
            ->where('order_date', '>=', $start)
            ->where('order_date', '<=', $end)
            ->where('quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(price * quantity), 0) as revenue')
            ->value('revenue');
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

        $row = DB::table('reverb_daily_data')
            ->whereDate('order_date', $date)
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
     * @return array{spend: float, sales: float}
     */
    private function amazonAdMetrics(string $date, bool $allowL1 = false): array
    {
        $spend = 0.0;
        $sales = 0.0;
        try {
            if (Schema::hasTable('amazon_sp_campaign_reports')) {
                $key = $this->adReportKey('amazon_sp_campaign_reports', 'report_date_range', $date, $allowL1);
                $salesExpr = $this->amazonDailySalesExpr('amazon_sp_campaign_reports');
                $kw = DB::table('amazon_sp_campaign_reports')
                    ->where('report_date_range', $key)
                    ->whereRaw("campaignName NOT REGEXP '(PT\\.?$|FBA$)'")
                    ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
                    ->selectRaw('COALESCE(SUM(spend), 0) as spend')
                    ->selectRaw('COALESCE(SUM('.$salesExpr.'), 0) as sales')
                    ->first();
                $spend += (float) ($kw->spend ?? 0);
                $sales += (float) ($kw->sales ?? 0);

                $pt = DB::table('amazon_sp_campaign_reports')
                    ->where('report_date_range', $key)
                    ->where(function ($query) {
                        $query->whereRaw("campaignName LIKE '%PT'")
                            ->orWhereRaw("campaignName LIKE '%PT.'");
                    })
                    ->whereRaw("campaignName NOT LIKE '%FBA PT%'")
                    ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
                    ->selectRaw('COALESCE(SUM(spend), 0) as spend')
                    ->selectRaw('COALESCE(SUM('.$salesExpr.'), 0) as sales')
                    ->first();
                $spend += (float) ($pt->spend ?? 0);
                $sales += (float) ($pt->sales ?? 0);
            }
            if (Schema::hasTable('amazon_sb_campaign_reports')) {
                $key = $this->adReportKey('amazon_sb_campaign_reports', 'report_date_range', $date, $allowL1);
                $salesExpr = $this->amazonDailySalesExpr('amazon_sb_campaign_reports');
                $hl = DB::table('amazon_sb_campaign_reports')
                    ->where('report_date_range', $key)
                    ->selectRaw('COALESCE(SUM(cost), 0) as spend')
                    ->selectRaw('COALESCE(SUM('.$salesExpr.'), 0) as sales')
                    ->first();
                $spend += (float) ($hl->spend ?? 0);
                $sales += (float) ($hl->sales ?? 0);
            }
        } catch (\Throwable $e) {
            Log::warning('Yesterday Amazon ad metrics failed: '.$e->getMessage());
        }

        return ['spend' => $spend, 'sales' => $sales];
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
                $key = $this->adReportKey($priority, 'report_range', $date, false);
                $row = DB::table($priority)
                    ->where('report_range', $key)
                    ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")), 0) as spend')
                    ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")), 0) as sales')
                    ->first();
                $spend += (float) ($row->spend ?? 0);
                $sales += (float) ($row->sales ?? 0);
            }
            if (Schema::hasTable($general)) {
                $key = $this->adReportKey($general, 'report_range', $date, false);
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
            $row = DB::table('google_ads_campaigns')
                ->whereDate('date', $key)
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
            $key = $this->adReportKey($table, 'report_range', $date, false);
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
     * Spend / Ads% must use the same calendar day as Y Sales.
     * Do not fall back to L1 — Amazon L1 is a newer/different pull (~$703)
     * and pairing it with complete-day sales (~$3,261) invents a 21.6% TACOS.
     * Dated daily keys (e.g. 2026-08-12 = $284 → 8.7%) match L30 Ads% (~9.5%).
     */
    private function adReportKey(string $table, string $column, string $yesterday, bool $allowL1 = false): string
    {
        $cacheKey = $table.'|'.$column.'|'.$yesterday.'|'.($allowL1 ? '1' : '0');
        if (isset($this->adReportKeyCache[$cacheKey])) {
            return $this->adReportKeyCache[$cacheKey];
        }

        $key = $yesterday;
        try {
            // Prefer the same calendar day as Y Sales. L1 is a different pull.
            if ($allowL1 && ! DB::table($table)->where($column, $yesterday)->exists()
                && DB::table($table)->where($column, 'L1')->exists()) {
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

        $start = $latest->copy()->subDay()->startOfDay();
        $end = $latest->copy()->subDay()->endOfDay();

        return [$start, $end, $start->toDateString()];
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
