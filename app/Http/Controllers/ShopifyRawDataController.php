<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShopifyRawDataController extends Controller
{
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

        $data = $rows->map(function ($row) {
            $totalAmount    = round((float) ($row->total_amount    ?? 0), 2);
            $discountAmount = round((float) ($row->discount_amount ?? 0), 2);
            $netSales       = round((float) ($row->net_sales       ?? ($totalAmount - $discountAmount)), 2);
            $orderTotal     = $row->order_total    !== null ? round((float) $row->order_total,    2) : null;
            $orderSubtotal  = $row->order_subtotal !== null ? round((float) $row->order_subtotal, 2) : null;

            return [
                'id'                 => $row->id              ?? '',
                'order_id'           => $row->order_id        ?? '',
                'order_number'       => $row->order_number    ?? '',
                'sku'                => $row->sku             ?? '',
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
                'tags'               => $row->tags                ?? '',
                'source_name'        => $row->source_name         ?? '',
            ];
        });

        return response()->json([
            'data'   => $data,
            'total'  => $data->count(),
            'status' => 200,
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
