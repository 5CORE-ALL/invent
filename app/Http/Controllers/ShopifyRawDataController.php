<?php

namespace App\Http\Controllers;

use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShopifyRawDataController extends Controller
{
    /**
     * Gross-margin assumption used by /shopify NPFT / NROI stat cards.
     * Same shape as Reverb's 0.85 margin in CalculateChannelMasterData (gross
     * profit per row = revenue × MARGIN − cogs). User-requested 0.95 here.
     */
    const SHOPIFY_GROSS_MARGIN = 0.95;

    // Sources/tags this page tracks
    const FILTER_SOURCES = ['checkout-via-buy-now-button', 'wsaio-app', 'shopify_draft_order'];


    public function index()
    {
        return view('shopify-raw-data.index');
    }

    public function shopifyIndex()
    {
        return view('shopify.index');
    }

    // Marketplace source_name / tag values to always exclude
    const EXCLUDE_SOURCES = [
        'amazon', 'shein', 'ebay', 'tiktok', 'temu',
        '179763773441', "macy's, inc.", "macy's", 'macys',
        'purchasing power', 'purchasingpower', 'reverb',
        'faire', 'best buy', 'bestbuy', 'best buy usa',
        'doba', '145019994113',
        'newegg', '189863297025',
        'depop', 'tiendamia',
        'mercari', 'aliexpress', 'ali express', 'wayfair',
    ];

    /**
     * Apply exclusions to a query builder instance.
     * Skips rows whose source_name or tags match any excluded marketplace,
     * and skips rows whose SKU contains "XYZ".
     */
    private function applyExclusions($query)
    {
        foreach (self::EXCLUDE_SOURCES as $term) {
            $query->whereRaw('LOWER(COALESCE(source_name,"")) NOT LIKE ?', ['%' . strtolower($term) . '%'])
                  ->whereRaw('LOWER(COALESCE(tags,"")) NOT LIKE ?',        ['%' . strtolower($term) . '%']);
        }

        $query->where('sku', 'NOT LIKE', '%XYZ%');

        return $query;
    }

    private function baseQuery(Carbon $dateFrom, Carbon $dateTo, bool $withExclusions = true)
    {
        $q = DB::table('shopify_raw_orders')
            ->where('order_date', '>=', $dateFrom->toDateString())
            ->where('order_date', '<=', $dateTo->toDateString());

        if ($withExclusions) {
            $this->applyExclusions($q);
        }

        return $q;
    }

    public function getData(Request $request)
    {
        $pstTimezone = 'America/Los_Angeles';

        $dateFrom = $request->input('date_from')
            ? Carbon::parse($request->input('date_from'), $pstTimezone)->startOfDay()
            : Carbon::now($pstTimezone)->subDays(30)->startOfDay();

        $dateTo = $request->input('date_to')
            ? Carbon::parse($request->input('date_to'), $pstTimezone)->endOfDay()
            : Carbon::now($pstTimezone)->endOfDay();

        $sourceFilter  = $request->input('source', 'all');
        $rawPage       = $request->input('page') === 'raw';  // raw-data page — no exclusions

        $query = $this->baseQuery($dateFrom, $dateTo, !$rawPage);

        if ($sourceFilter !== 'all') {
            $query->where(function ($q) use ($sourceFilter) {
                $q->where('source_name', $sourceFilter)
                  ->orWhere('tags', 'LIKE', '%' . $sourceFilter . '%');
            });
        }

        $rows = $query->orderBy('order_date', 'desc')->get();

        // Build a SKU → LP lookup so the stat-cards can compute COGS per row
        // client-side. LP lives in product_master.Values JSON (key 'lp', case
        // insensitive) — same extraction path /ebay/daily-sales uses. We pull
        // only the SKUs actually present in this result set so this stays
        // O(rows) instead of O(table).
        $lpBySku = $this->fetchLpBySkuForRows($rows);

        $data = $rows->map(function ($row) use ($lpBySku) {
            $totalAmount    = round((float) ($row->total_amount    ?? 0), 2);
            $discountAmount = round((float) ($row->discount_amount ?? 0), 2);
            $netSales       = round((float) ($row->net_sales       ?? ($totalAmount - $discountAmount)), 2);
            $orderTotal     = $row->order_total    !== null ? round((float) $row->order_total,    2) : null;
            $orderSubtotal  = $row->order_subtotal !== null ? round((float) $row->order_subtotal, 2) : null;
            $sku            = $row->sku ?? '';
            $lp             = (float) ($lpBySku[$sku] ?? 0);

            return [
                'id'                 => $row->id              ?? '',
                'order_id'           => $row->order_id        ?? '',
                'order_number'       => $row->order_number    ?? '',
                'sku'                => $sku,
                'product_title'      => $row->product_title   ?? '',
                'quantity'           => (int) ($row->quantity ?? 0),
                'price'              => round((float) ($row->price ?? 0), 2),
                'total_amount'       => $totalAmount,
                'discount_codes'     => $row->discount_codes  ?? '',
                'discount_amount'    => $discountAmount,
                'net_sales'          => $netSales,
                'order_total'        => $orderTotal,      // actual paid total for whole order
                'order_subtotal'     => $orderSubtotal,
                'order_date'         => $row->order_date          ?? '',
                'financial_status'   => $row->financial_status    ?? '',
                'fulfillment_status' => $row->fulfillment_status  ?? '',
                'customer_name'      => $row->customer_name       ?? '',
                'customer_email'     => $row->customer_email      ?? '',
                'shipping_city'      => $row->shipping_city       ?? '',
                'shipping_country'   => $row->shipping_country    ?? '',
                'tracking_number'    => $row->tracking_number     ?? '',
                'tracking_company'   => $row->tracking_company    ?? '',
                'shipment_status'        => $row->shipment_status        ?? '',
                'shipment_status_detail' => $row->shipment_status_detail ?? '',
                'shipment_checked_at'    => $row->shipment_checked_at    ?? '',
                'tags'               => $row->tags                ?? '',
                'source_name'        => $row->source_name         ?? '',
                // LP per unit (used by NPFT / NROI cards). 0 when SKU not found
                // in product_master so missing-SKU rows simply contribute 0 COGS.
                'lp'                 => round($lp, 2),
            ];
        });

        // Total Shopify ad spend (Google Ads — Shopping + Search) for the SAME
        // date range the table is showing. Mirrors the channel-level ad-spend
        // pattern /ebay/daily-sales uses (KW + PMT spent over the rolling
        // window). 'metrics_cost_micros' is in micros (USD * 1e6).
        $totalAdSpend = $this->fetchShopifyAdSpendForRange($dateFrom, $dateTo);

        return response()->json([
            'data'           => $data,
            'total'          => $data->count(),
            'total_ad_spend' => round($totalAdSpend, 2),
            'gross_margin'   => self::SHOPIFY_GROSS_MARGIN,
            'status'         => 200,
        ]);
    }

    /**
     * Extract LP from product_master.Values JSON for a set of SKUs.
     *
     * @param  iterable $rows  collection of order rows (must expose ->sku)
     * @return array<string, float>  sku → lp
     */
    private function fetchLpBySkuForRows($rows): array
    {
        $skus = collect($rows)->pluck('sku')->filter()->unique()->values()->all();
        if (empty($skus)) return [];

        $masters = ProductMaster::whereIn('sku', $skus)->get(['sku', 'Values']);
        $out = [];
        foreach ($masters as $pm) {
            $values = is_array($pm->Values)
                ? $pm->Values
                : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
            $lp = 0.0;
            if (is_array($values)) {
                foreach ($values as $k => $v) {
                    if (strtolower((string) $k) === 'lp') {
                        $lp = (float) $v;
                        break;
                    }
                }
            }
            if ($lp <= 0 && isset($pm->lp)) {
                $lp = (float) $pm->lp;
            }
            $out[$pm->sku] = $lp;
        }
        return $out;
    }

    /**
     * Sum Google Ads cost (Shopping + Search) for the given date range — the
     * Shopify ad-spend source used by /all-marketplace-master's Shopify B2C
     * row. 'metrics_cost_micros' is stored as USD micros (1e6 = $1).
     */
    private function fetchShopifyAdSpendForRange(Carbon $dateFrom, Carbon $dateTo): float
    {
        try {
            $costMicros = (float) DB::table('google_ads_campaigns')
                ->whereDate('date', '>=', $dateFrom->toDateString())
                ->whereDate('date', '<=', $dateTo->toDateString())
                ->whereIn('advertising_channel_type', ['SHOPPING', 'SEARCH'])
                ->whereIn('campaign_status', ['ENABLED', 'PAUSED'])
                ->sum('metrics_cost_micros');
            return $costMicros / 1_000_000.0;
        } catch (\Throwable $e) {
            \Log::warning('fetchShopifyAdSpendForRange failed: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * eBay1 sales from shopify_raw_orders, excluding cancelled orders.
     * eBay1 = source_name 'eBay' and NOT tagged as the other eBay stores (Ebay 2 / Ebay3).
     * "Cancelled" excluded via tags and voided/cancelled financial status (no dedicated column exists).
     * Defaults to the last 30 days (PST) to align with the eBay L30 view.
     */
    public function ebay1Sales(Request $request)
    {
        $pstTimezone = 'America/Los_Angeles';

        $dateFrom = $request->input('date_from')
            ? Carbon::parse($request->input('date_from'), $pstTimezone)->startOfDay()
            : Carbon::now($pstTimezone)->subDays(30)->startOfDay();

        $dateTo = $request->input('date_to')
            ? Carbon::parse($request->input('date_to'), $pstTimezone)->endOfDay()
            : Carbon::now($pstTimezone)->endOfDay();

        $query = DB::table('shopify_raw_orders')
            ->where('order_date', '>=', $dateFrom->toDateString())
            ->where('order_date', '<=', $dateTo->toDateString())
            ->where('source_name', 'eBay')
            ->whereRaw('LOWER(COALESCE(tags,"")) NOT LIKE ?', ['%ebay 2%'])
            ->whereRaw('LOWER(COALESCE(tags,"")) NOT LIKE ?', ['%ebay3%'])
            // Exclude cancelled orders
            ->whereRaw('LOWER(COALESCE(tags,"")) NOT LIKE ?', ['%cancel%'])
            ->whereRaw('LOWER(COALESCE(financial_status,"")) NOT IN (?, ?)', ['voided', 'cancelled']);

        return response()->json([
            'sales'     => round((float) (clone $query)->sum('total_amount'), 2),
            'net_sales' => round((float) (clone $query)->sum('net_sales'), 2),
            'orders'    => (int) (clone $query)->distinct('order_id')->count('order_id'),
            'qty'       => (int) (clone $query)->sum('quantity'),
            'date_from' => $dateFrom->toDateString(),
            'date_to'   => $dateTo->toDateString(),
            'status'    => 200,
        ]);
    }

    public function getStats(Request $request)
    {
        $pstTimezone = 'America/Los_Angeles';

        $dateFrom = $request->input('date_from')
            ? Carbon::parse($request->input('date_from'), $pstTimezone)->startOfDay()
            : Carbon::now($pstTimezone)->subDays(30)->startOfDay();

        $dateTo = $request->input('date_to')
            ? Carbon::parse($request->input('date_to'), $pstTimezone)->endOfDay()
            : Carbon::now($pstTimezone)->endOfDay();

        $rawPage = $request->input('page') === 'raw';

        $stats = [];
        foreach (self::FILTER_SOURCES as $src) {
            $q = $this->baseQuery($dateFrom, $dateTo, !$rawPage)
                ->where(function ($q) use ($src) {
                    $q->where('source_name', $src)
                      ->orWhere('tags', 'LIKE', '%' . $src . '%');
                });

            $stats[$src] = [
                'count'   => (clone $q)->count(),
                'revenue' => round((float) (clone $q)->sum('net_sales'), 2),
            ];
        }

        $base = $this->baseQuery($dateFrom, $dateTo, !$rawPage);

        return response()->json([
            'total_orders'  => (clone $base)->count(),
            'total_revenue' => round((float) (clone $base)->sum('total_amount'), 2),
            'total_qty'     => (int) (clone $base)->sum('quantity'),
            'by_source'     => $stats,
        ]);
    }
}
