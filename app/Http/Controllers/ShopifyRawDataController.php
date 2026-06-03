<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShopifyRawDataController extends Controller
{
    // Sources/tags this page tracks
    const FILTER_SOURCES = ['checkout-via-buy-now-button', 'wsaio-app', 'shopify_draft_order'];

    // Marketplace tags/sources to always exclude
    const EXCLUDE_TAGS = [
        'ebay', 'ebay integration & importer', 'ebay integration',
        'amazon', 'faire', 'doba', 'wayfair', 'reverb', 'shein',
        'bestbuy', 'best buy', 'macys', "macy's", 'walmart',
        'temu', 'purchasing power', 'purchasingpower',
        'mirakl', 'houzz', 'overstock',
    ];

    public function index()
    {
        return view('shopify-raw-data.index');
    }

    /**
     * Apply marketplace exclusions to a query builder instance.
     * Skips rows whose tags or source_name contain any known marketplace name.
     */
    private function applyExclusions($query)
    {
        foreach (self::EXCLUDE_TAGS as $term) {
            $query->where('tags', 'NOT LIKE', '%' . $term . '%')
                  ->where('source_name', 'NOT LIKE', '%' . $term . '%');
        }
        return $query;
    }

    public function getData(Request $request)
    {
        $pstTimezone = 'America/Los_Angeles';

        // Date range – default to last 30 days
        $dateFrom = $request->input('date_from')
            ? Carbon::parse($request->input('date_from'), $pstTimezone)->startOfDay()
            : Carbon::now($pstTimezone)->subDays(30)->startOfDay();

        $dateTo = $request->input('date_to')
            ? Carbon::parse($request->input('date_to'), $pstTimezone)->endOfDay()
            : Carbon::now($pstTimezone)->endOfDay();

        // Active source filter (optional: single source or 'all')
        $sourceFilter = $request->input('source', 'all');

        $query = DB::connection('apicentral')
            ->table('shopify_order_items')
            ->where('order_date', '>=', $dateFrom)
            ->where('order_date', '<=', $dateTo)
            ->where(function ($q) {
                // Match on source_name OR tags for all three filter values
                $q->whereIn('source_name', self::FILTER_SOURCES);
                foreach (self::FILTER_SOURCES as $tag) {
                    $q->orWhere('tags', 'LIKE', '%' . $tag . '%');
                }
            });

        // Exclude marketplace-tagged orders
        $this->applyExclusions($query);

        // Optional per-source narrowing
        if ($sourceFilter !== 'all') {
            $query->where(function ($q) use ($sourceFilter) {
                $q->where('source_name', $sourceFilter)
                  ->orWhere('tags', 'LIKE', '%' . $sourceFilter . '%');
            });
        }

        $rows = $query->orderBy('order_date', 'desc')->get();

        $data = $rows->map(function ($row) {
            return [
                'id'                 => $row->id ?? '',
                'order_id'           => $row->order_id ?? '',
                'order_number'       => $row->order_number ?? '',
                'sku'                => $row->sku ?? '',
                'product_title'      => $row->product_title ?? '',
                'variant_title'      => $row->variant_title ?? '',
                'quantity'           => (int) ($row->quantity ?? 0),
                'price'              => round((float) ($row->price ?? 0), 2),
                'total_amount'       => round((float) ($row->total_amount ?? 0), 2),
                'order_date'         => $row->order_date ?? '',
                'financial_status'   => $row->financial_status ?? '',
                'fulfillment_status' => $row->fulfillment_status ?? '',
                'customer_name'      => $row->customer_name ?? '',
                'customer_email'     => $row->customer_email ?? '',
                'shipping_address'   => $row->shipping_address ?? '',
                'shipping_city'      => $row->shipping_city ?? '',
                'shipping_state'     => $row->shipping_state ?? '',
                'shipping_country'   => $row->shipping_country ?? '',
                'shipping_zip'       => $row->shipping_zip ?? '',
                'tracking_number'    => $row->tracking_number ?? '',
                'tracking_company'   => $row->tracking_company ?? '',
                'tags'               => $row->tags ?? '',
                'source_name'        => $row->source_name ?? '',
                'discount_codes'     => $row->discount_codes ?? '',
                'note'               => $row->note ?? '',
                'currency'           => $row->currency ?? '',
                'channel'            => $row->channel ?? '',
            ];
        });

        return response()->json([
            'data'    => $data,
            'total'   => $data->count(),
            'status'  => 200,
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

        $baseQuery = function () use ($dateFrom, $dateTo) {
            $q = DB::connection('apicentral')
                ->table('shopify_order_items')
                ->where('order_date', '>=', $dateFrom)
                ->where('order_date', '<=', $dateTo)
                ->where(function ($q) {
                    $q->whereIn('source_name', self::FILTER_SOURCES);
                    foreach (self::FILTER_SOURCES as $tag) {
                        $q->orWhere('tags', 'LIKE', '%' . $tag . '%');
                    }
                });
            $this->applyExclusions($q);
            return $q;
        };

        $stats = [];
        foreach (self::FILTER_SOURCES as $src) {
            $count = (clone $baseQuery())
                ->where(function ($q) use ($src) {
                    $q->where('source_name', $src)
                      ->orWhere('tags', 'LIKE', '%' . $src . '%');
                })
                ->count();

            $revenue = (clone $baseQuery())
                ->where(function ($q) use ($src) {
                    $q->where('source_name', $src)
                      ->orWhere('tags', 'LIKE', '%' . $src . '%');
                })
                ->sum('total_amount');

            $stats[$src] = [
                'count'   => $count,
                'revenue' => round((float) $revenue, 2),
            ];
        }

        $totalCount   = $baseQuery()->count();
        $totalRevenue = round((float) $baseQuery()->sum('total_amount'), 2);
        $totalQty     = (int) $baseQuery()->sum('quantity');

        return response()->json([
            'total_orders'  => $totalCount,
            'total_revenue' => $totalRevenue,
            'total_qty'     => $totalQty,
            'by_source'     => $stats,
        ]);
    }
}
