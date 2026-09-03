<?php

namespace App\Services\Support;

use App\Http\Controllers\MarketPlace\FaireController;
use App\Http\Controllers\ShopifyRawDataController;
use App\Models\AmazonOrder;
use App\Models\FacebookMarketplaceSale;
use App\Models\Tiktok2Order;
use App\Models\TiktokOrder;
use App\Services\EbayChannelMetricsService;
use App\Services\TemuShopifySalesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * All Marketplace Master "Today Sales": current Eastern calendar day from 00:00.
 */
class ChannelTodaySalesService
{
    public const TZ = 'America/New_York';

    public const CACHE_PREFIX = 'amm_today_sales_est_v1_';

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string} [startOfDay, endOfDay, Y-m-d]
     */
    public function todayWindow(): array
    {
        $now = Carbon::now(self::TZ);

        return [$now->copy()->startOfDay(), $now->copy()->endOfDay(), $now->toDateString()];
    }

    /**
     * @return array<string, float> keyed by stripped lowercase channel name(s)
     */
    public function salesByLookupKey(): array
    {
        [$start, $end, $ymd] = $this->todayWindow();

        return Cache::remember(self::CACHE_PREFIX.$ymd, 60, function () use ($start, $end, $ymd) {
            return $this->computeSalesByLookupKey($start, $end, $ymd);
        });
    }

    public function valueForChannel(string $channelName, ?array $sales = null): ?float
    {
        $sales ??= $this->salesByLookupKey();
        foreach ($this->lookupKeys($channelName) as $key) {
            if (array_key_exists($key, $sales)) {
                return $sales[$key];
            }
        }

        return null;
    }

    /**
     * @return array<string, float>
     */
    private function computeSalesByLookupKey(Carbon $start, Carbon $end, string $ymd): array
    {
        $out = [];

        $channels = [
            'amazon' => fn () => $this->amazon($start, $end),
            'ebay' => fn () => EbayChannelMetricsService::sumSalesForTimezoneDates(1, $ymd, $ymd, self::TZ),
            'ebaytwo' => fn () => EbayChannelMetricsService::sumSalesForTimezoneDates(2, $ymd, $ymd, self::TZ),
            'ebaythree' => fn () => EbayChannelMetricsService::sumSalesForTimezoneDates(3, $ymd, $ymd, self::TZ),
            'temu' => fn () => $this->temu($start, $end, false),
            'temu2' => fn () => $this->temu($start, $end, true),
            'temu3' => fn () => $this->temu3($start, $end),
            'shopify' => fn () => $this->shopifyDirect($ymd),
            'shopifyb2c' => fn () => $this->shopifyB2x(false, $start, $end, $ymd),
            'shopifyb2b' => fn () => $this->shopifyB2x(true, $start, $end, $ymd),
            'doba' => fn () => $this->doba($start, $end),
            'bestbuyusa' => fn () => $this->mirakl('Best Buy USA', $start, $end),
            'macys' => fn () => $this->mirakl("Macy's, Inc.", $start, $end),
            'fbmarketplace' => fn () => $this->fbMarketplace($start, $end, $ymd),
            'tiktokshop' => fn () => $this->tiktok($start, $end),
            'tiktok2' => fn () => $this->tiktok2($start, $end),
            'wayfair' => fn () => $this->wayfair($ymd),
            'shein' => fn () => $this->shein($start, $end),
            'aliexpress' => fn () => $this->aliexpress($start, $end),
            'mercariwship' => fn () => $this->mercari($start, $end, true),
            'mercariwoship' => fn () => $this->mercari($start, $end, false),
            'topdawg' => fn () => $this->dateSum('topdawg_order_metrics', 'order_date', $ymd, 'COALESCE(SUM(amount), 0)'),
            'depop' => fn () => $this->depop($ymd),
            'vinted' => fn () => $this->vinted($ymd),
            'faire' => fn () => $this->faire($start, $end),
            'purchasingpower' => fn () => $this->purchasingPower($start, $end),
            'reverb' => fn () => $this->reverb($ymd),
            'newegg' => fn () => $this->newegg($start, $end),
            'pls' => fn () => $this->pls($start, $end),
            'walmart' => fn () => $this->walmart($start, $end),
        ];

        foreach ($channels as $key => $fn) {
            try {
                $value = $fn();
                if ($value === null) {
                    continue;
                }
                $this->put($out, (float) $value, $key);
            } catch (\Throwable $e) {
                Log::warning('Today Sales failed for '.$key.': '.$e->getMessage());
            }
        }

        $this->copyAliases($out, 'ebaytwo', ['ebay2']);
        $this->copyAliases($out, 'ebaythree', ['ebay3']);
        $this->copyAliases($out, 'bestbuyusa', ['bestbuy']);
        $this->copyAliases($out, 'macys', ['macysinc', "macy'sinc", "macy's,inc."]);
        $this->copyAliases($out, 'fbmarketplace', ['facebookmarketplace']);
        $this->copyAliases($out, 'tiktokshop', ['tiktok']);
        $this->copyAliases($out, 'tiktok2', ['tiktokshop2']);
        $this->copyAliases($out, 'temu3', ['temuthree']);

        return $out;
    }

    /**
     * @return list<string>
     */
    private function lookupKeys(string $name): array
    {
        $raw = strtolower(trim($name));
        $keys = [
            preg_replace('/[^a-z0-9]/', '', $raw) ?: '',
            strtolower(str_replace([' ', '-', '&', '/'], '', $raw)),
            strtolower(str_replace([' ', '-', '&', '/', ',', "'"], '', $raw)),
        ];

        return array_values(array_unique(array_filter($keys, fn ($k) => $k !== '')));
    }

    /**
     * @param  array<string, float>  $out
     */
    private function put(array &$out, float $value, string $key): void
    {
        $out[$key] = round($value, 2);
        foreach ($this->lookupKeys($key) as $alias) {
            $out[$alias] = round($value, 2);
        }
    }

    /**
     * @param  array<string, float>  $out
     * @param  list<string>  $aliases
     */
    private function copyAliases(array &$out, string $from, array $aliases): void
    {
        if (! array_key_exists($from, $out)) {
            return;
        }
        foreach ($aliases as $alias) {
            $out[$alias] = $out[$from];
        }
    }

    private function amazon(Carbon $start, Carbon $end): float
    {
        return round((float) AmazonOrder::productSalesByOrderDate(
            $start->copy()->utc(),
            $end->copy()->utc()
        ), 2);
    }

    private function temu(Carbon $start, Carbon $end, bool $isTemu2): ?float
    {
        $table = $isTemu2 ? 'temu2_orders' : 'temu_orders';
        if (! Schema::hasTable($table)) {
            return null;
        }

        return (float) TemuShopifySalesService::computeMetricsFromOrders($start, $end, $isTemu2)['base_sales'];
    }

    private function temu3(Carbon $start, Carbon $end): ?float
    {
        if (! Schema::hasTable('temu3_orders')) {
            return null;
        }

        return (float) TemuShopifySalesService::computeMetricsFromTemu3Orders($start, $end)['sales'];
    }

    private function shopifyDirect(string $ymd): ?float
    {
        if (! Schema::hasTable('shopify_raw_orders')) {
            return null;
        }

        $q = DB::table('shopify_raw_orders')
            ->where('order_date', '>=', $ymd)
            ->where('order_date', '<=', $ymd);
        foreach (ShopifyRawDataController::EXCLUDE_SOURCES as $term) {
            $q->whereRaw('LOWER(COALESCE(source_name,"")) NOT LIKE ?', ['%'.strtolower($term).'%'])
                ->whereRaw('LOWER(COALESCE(tags,"")) NOT LIKE ?', ['%'.strtolower($term).'%']);
        }
        $q->where(function ($inner) {
            $inner->whereNull('sku')->orWhere('sku', 'NOT LIKE', '%XYZ%');
        });

        return round((float) $q->sum('net_sales'), 2);
    }

    private function shopifyB2x(bool $isB2b, Carbon $start, Carbon $end, string $ymd): ?float
    {
        $table = $isB2b ? 'shopify_b2b_daily_data' : 'shopify_b2c_daily_data';
        $sum = 0.0;
        if (Schema::hasTable($table)) {
            $sum = (float) DB::table($table)
                ->where('order_date', '>=', $start)
                ->where('order_date', '<=', $end)
                ->where('financial_status', '!=', 'refunded')
                ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
                ->value('revenue');
        } elseif ($isB2b) {
            return null;
        }

        if ($sum > 0) {
            return round($sum, 2);
        }

        if (! $isB2b) {
            return $this->shopifyDirect($ymd);
        }

        return 0.0;
    }

    private function doba(Carbon $start, Carbon $end): ?float
    {
        if (! Schema::hasTable('doba_daily_data')) {
            return null;
        }

        $cancelled = ['Cancelled', 'Canceled', 'cancelled', 'canceled', 'CANCELLED', 'CANCELED'];

        return round((float) DB::table('doba_daily_data')
            ->where('order_time', '>=', $start)
            ->where('order_time', '<=', $end)
            ->where(function ($q) use ($cancelled) {
                $q->whereNull('order_status')->orWhereNotIn('order_status', $cancelled);
            })
            ->sum('total_price'), 2);
    }

    private function mirakl(string $channelName, Carbon $start, Carbon $end): ?float
    {
        if (! Schema::hasTable('mirakl_daily_data')) {
            return null;
        }

        $sum = (float) DB::table('mirakl_daily_data')
            ->where('channel_name', $channelName)
            ->where('order_created_at', '>=', $start)
            ->where('order_created_at', '<=', $end)
            ->where('status', '!=', 'CLOSED')
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    private function fbMarketplace(Carbon $start, Carbon $end, string $ymd): ?float
    {
        if (! Schema::hasTable('facebook_marketplace_sales')) {
            return null;
        }

        $rangeStartUtc = $start->copy()->utc();
        $rangeEndUtc = $end->copy()->utc();
        $sum = 0.0;
        FacebookMarketplaceSale::query()
            ->where(function ($q) use ($ymd, $rangeStartUtc, $rangeEndUtc) {
                $q->whereBetween('order_date', [$ymd, $ymd])
                    ->orWhere(function ($q2) use ($rangeStartUtc, $rangeEndUtc) {
                        $q2->whereNull('order_date')
                            ->whereBetween('created_at', [
                                $rangeStartUtc->toDateTimeString(),
                                $rangeEndUtc->toDateTimeString(),
                            ]);
                    });
            })
            ->get(['sold_price', 'qty_sold'])
            ->each(function ($r) use (&$sum) {
                $sum += (float) $r->sold_price * (int) $r->qty_sold;
            });

        return round($sum, 2);
    }

    private function tiktok(Carbon $start, Carbon $end): ?float
    {
        if (! TiktokOrder::tableReady()) {
            return null;
        }

        return round(TiktokOrder::salesAmountBetween($start, $end), 2);
    }

    private function tiktok2(Carbon $start, Carbon $end): ?float
    {
        if (Tiktok2Order::tableReady() && Tiktok2Order::query()->whereNotNull('order_created_at')->exists()) {
            return round(Tiktok2Order::salesAmountBetween($start, $end), 2);
        }

        if (! Schema::hasTable('tiktok_sales_two')) {
            return null;
        }

        return round((float) DB::table('tiktok_sales_two')
            ->where('order_date', '>=', $start)
            ->where('order_date', '<=', $end)
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as revenue')
            ->value('revenue'), 2);
    }

    private function wayfair(string $ymd): ?float
    {
        if (! Schema::hasTable('wayfair_daily_data')) {
            return null;
        }

        return round((float) DB::table('wayfair_daily_data')
            ->whereDate('po_date', $ymd)
            ->where('quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as revenue')
            ->value('revenue'), 2);
    }

    private function shein(Carbon $start, Carbon $end): ?float
    {
        if (! Schema::hasTable('shein_daily_data')) {
            return null;
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

        return round($sum, 2);
    }

    private function aliexpress(Carbon $start, Carbon $end): ?float
    {
        $isCancelled = function (string $status): bool {
            $status = strtolower($status);

            return str_contains($status, 'refund')
                || str_contains($status, 'return')
                || str_contains($status, 'cancel')
                || str_contains($status, 'closed');
        };

        if (Schema::hasTable('aliexpress_order_metrics')) {
            $sum = 0.0;
            foreach (
                DB::table('aliexpress_order_metrics')
                    ->where('order_date', '>=', $start)
                    ->where('order_date', '<=', $end)
                    ->get(['status', 'amount']) as $row
            ) {
                if ($isCancelled((string) ($row->status ?? ''))) {
                    continue;
                }
                $sum += (float) ($row->amount ?? 0);
            }

            return round($sum, 2);
        }

        if (! Schema::hasTable('aliexpress_daily_data')) {
            return null;
        }

        $sum = 0.0;
        foreach (
            DB::table('aliexpress_daily_data')
                ->where('order_date', '>=', $start)
                ->where('order_date', '<=', $end)
                ->cursor() as $row
        ) {
            if ($isCancelled((string) ($row->order_status ?? ''))) {
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

        return round($sum, 2);
    }

    private function mercari(Carbon $start, Carbon $end, bool $withShip): ?float
    {
        if (! Schema::hasTable('mercari_daily_data')) {
            return null;
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

        return round((float) $q->where('sold_date', '>=', $start)
            ->where('sold_date', '<=', $end)
            ->selectRaw('COALESCE(SUM(item_price), 0) as revenue')
            ->value('revenue'), 2);
    }

    private function depop(string $ymd): ?float
    {
        if (! Schema::hasTable('depop_sales_data')) {
            return null;
        }

        return round((float) DB::table('depop_sales_data')
            ->whereDate('sale_date', $ymd)
            ->selectRaw('COALESCE(SUM(item_price * GREATEST(COALESCE(NULLIF(quantity, 0), 1), 1)), 0) as revenue')
            ->value('revenue'), 2);
    }

    private function vinted(string $ymd): ?float
    {
        if (! Schema::hasTable('vinted_sales_data')) {
            return null;
        }

        return round((float) DB::table('vinted_sales_data')
            ->whereDate('sale_date', $ymd)
            ->selectRaw('COALESCE(SUM(item_price * GREATEST(COALESCE(NULLIF(quantity, 0), 1), 1)), 0) as revenue')
            ->value('revenue'), 2);
    }

    private function faire(Carbon $start, Carbon $end): ?float
    {
        $shopify = 0.0;
        $api = 0.0;

        if (Schema::hasTable('shopify_raw_orders')) {
            $shopify = (float) DB::table('shopify_raw_orders')
                ->where(fn ($q) => FaireController::applyFaireShopifyOrderFilter($q))
                ->where('order_date', '>=', $start)
                ->where('order_date', '<=', $end)
                ->where('quantity', '>', 0)
                ->selectRaw('COALESCE(SUM(price * quantity), 0) as revenue')
                ->value('revenue');
        }

        if (Schema::hasTable('faire_order_metrics')) {
            $api = (float) DB::table('faire_order_metrics')
                ->where('order_date', '>=', $start)
                ->where('order_date', '<=', $end)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereRaw('UPPER(status) NOT IN (?, ?)', ['CANCELLED', 'CANCELED']);
                })
                ->selectRaw('COALESCE(SUM(amount), 0) as revenue')
                ->value('revenue');
        }

        if (! Schema::hasTable('shopify_raw_orders') && ! Schema::hasTable('faire_order_metrics')) {
            return null;
        }

        return round(max($shopify, $api), 2);
    }

    private function purchasingPower(Carbon $start, Carbon $end): ?float
    {
        try {
            $ppWhere = function ($q) {
                $q->where('source_name', 'LIKE', '%purchasing power%')
                    ->orWhere('source_name', 'LIKE', '%purchasingpower%')
                    ->orWhere('tags', 'LIKE', '%Purchasing Power%')
                    ->orWhere('tags', 'LIKE', '%PurchasingPower%');
            };

            return round((float) DB::connection('apicentral')->table('shopify_order_items')
                ->where($ppWhere)
                ->where('order_date', '>=', $start)
                ->where('order_date', '<=', $end)
                ->where('quantity', '>', 0)
                ->selectRaw('COALESCE(SUM(price * quantity), 0) as revenue')
                ->value('revenue'), 2);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function reverb(string $ymd): ?float
    {
        if (! Schema::hasTable('reverb_daily_data')) {
            return null;
        }

        return round((float) DB::table('reverb_daily_data')
            ->whereDate('order_date', $ymd)
            ->whereRaw('LOWER(COALESCE(status, "")) NOT LIKE ?', ['%cancel%'])
            ->whereRaw('LOWER(COALESCE(status, "")) NOT LIKE ?', ['%refund%'])
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereNotNull('order_number')->where('order_number', '!=', '')
            ->selectRaw('COALESCE(SUM(COALESCE(NULLIF(amount, 0), product_subtotal, 0)), 0) as revenue')
            ->value('revenue'), 2);
    }

    private function newegg(Carbon $start, Carbon $end): ?float
    {
        if (! Schema::hasTable('newegg_orders') || ! Schema::hasTable('newegg_order_items')) {
            return null;
        }

        return round((float) DB::table('newegg_orders as o')
            ->join('newegg_order_items as i', 'o.order_number', '=', 'i.order_number')
            ->where('o.order_date', '>=', $start)
            ->where('o.order_date', '<=', $end)
            ->where(function ($q) {
                $q->whereNull('o.order_status')->orWhere('o.order_status', '!=', 4);
            })
            ->selectRaw('COALESCE(SUM(i.unit_price * i.ordered_qty), 0) as revenue')
            ->value('revenue'), 2);
    }

    private function pls(Carbon $start, Carbon $end): ?float
    {
        if (! Schema::hasTable('pls_sales')) {
            return null;
        }

        return round((float) DB::table('pls_sales')
            ->where('order_date', '>=', $start)
            ->where('order_date', '<=', $end)
            ->sum('total_amount'), 2);
    }

    private function walmart(Carbon $start, Carbon $end): ?float
    {
        if (! Schema::hasTable('walmart_daily_data')) {
            return null;
        }

        return round((float) DB::table('walmart_daily_data')
            ->where('period', 'l30')
            ->where('order_date', '>=', $start)
            ->where('order_date', '<=', $end)
            ->where('fulfillment_option', 'DELIVERY')
            ->where('status', '!=', 'Cancelled')
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as revenue')
            ->value('revenue'), 2);
    }

    private function dateSum(string $table, string $dateColumn, string $ymd, string $sumExpr): ?float
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        return round((float) DB::table($table)
            ->whereDate($dateColumn, $ymd)
            ->selectRaw($sumExpr.' as revenue')
            ->value('revenue'), 2);
    }
}
