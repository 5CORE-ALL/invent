<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\PurchasingPowerSale;

class ShopifyOrdersController extends Controller
{
    public function index()
    {
        return view('shopify-orders.index');
    }

    public function getData(Request $request)
    {
        // Get date 30 days ago in PST
        $pstTimezone = 'America/Los_Angeles';
        $thirtyDaysAgo = Carbon::now($pstTimezone)->subDays(30)->startOfDay();

        // Known mappings for numeric source names
        $knownMappings = [
            '2329312' => 'Facebook',
            'shopify_draft' => 'Shopify Draft',
            'pos' => 'POS',
            'web' => 'Web',
        ];

        // First, create a mapping of source_name to display name
        $sourceMapping = [];
        $sourceSamples = DB::connection('apicentral')
            ->table('shopify_order_items')
            ->select('source_name', 'tags')
            ->where('order_date', '>=', $thirtyDaysAgo)
            ->whereNotNull('source_name')
            ->where('source_name', '!=', '')
            ->distinct()
            ->get();
        
        foreach ($sourceSamples as $sample) {
            $sourceName = trim($sample->source_name ?? '');
            $tags = trim($sample->tags ?? '');
            
            // Check if we have a known mapping first
            if (isset($knownMappings[strtolower($sourceName)])) {
                $source = $knownMappings[strtolower($sourceName)];
            }
            // Determine actual source: if source_name is numeric, use tags
            elseif (is_numeric($sourceName) || empty($sourceName)) {
                if (!empty($tags)) {
                    // Tags might be comma-separated
                    $tagArray = array_map('trim', explode(',', $tags));
                    $source = null;
                    foreach ($tagArray as $tag) {
                        // Skip numeric tags, empty, and very long tags
                        if (!empty($tag) && !is_numeric($tag) && strlen($tag) < 50) {
                            $source = $tag;
                            break;
                        }
                    }
                    $source = $source ?: ($sourceName ?: 'Unknown');
                } else {
                    $source = $sourceName ?: 'Unknown';
                }
            } else {
                $source = $sourceName;
            }
            
            // Capitalize properly
            $source = ucwords(strtolower(trim($source)));
            
            // Special cases for brands
            $sourceLower = strtolower($source);
            if ($sourceLower === 'ebay') {
                $source = 'eBay';
            } elseif ($sourceLower === 'amazon') {
                $source = 'Amz Shp';
            } elseif ($sourceLower === 'bestbuy' || strpos($sourceLower, 'best buy') !== false) {
                $source = 'BestBuy Shp';
            } elseif (strpos($sourceLower, 'macy') !== false) {
                $source = "Macy's Shp";
            } elseif ($sourceLower === 'doba') {
                $source = 'Doba Shp';
            } elseif ($sourceLower === 'faire') {
                $source = 'Faire Shp';
            } elseif (strpos($sourceLower, 'purchasing power') !== false || strpos($sourceLower, 'purchasingpower') !== false) {
                $source = 'PP Shp';
            } elseif ($sourceLower === 'reverb') {
                $source = 'R Shp';
            } elseif ($sourceLower === 'shein') {
                $source = 'Sen Shp';
            } elseif ($sourceLower === 'wayfair') {
                $source = 'WF Shp';
            } elseif ($sourceLower === 'pos') {
                $source = 'POS';
            } elseif (strpos($sourceLower, 'shopify') !== false && strpos($sourceLower, 'draft') !== false) {
                $source = 'Shopify Draft';
            }
            
            $sourceMapping[$sourceName] = $source;
        }

        // Query shopify_order_items
        $orderItems = DB::connection('apicentral')
            ->table('shopify_order_items')
            ->select('sku', 'source_name', DB::raw('SUM(quantity) as total_quantity'))
            ->where('order_date', '>=', $thirtyDaysAgo)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('sku', 'source_name')
            ->get();

        // Query Amazon sales data from amazon_order_items
        $amazonSales = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->select('i.sku', DB::raw('SUM(i.quantity) as total_quantity'))
            ->where('o.order_date', '>=', $thirtyDaysAgo)
            ->where(function ($q) {
                $q->whereNull('o.status')
                    ->orWhereNotIn('o.status', ['Canceled', 'Cancelled']);
            })
            ->whereNotNull('i.sku')
            ->where('i.sku', '!=', '')
            ->groupBy('i.sku')
            ->pluck('total_quantity', 'sku')
            ->toArray();

        // Query Faire sales data from faire_daily_data
        $faireSales = DB::table('faire_daily_data')
            ->select('sku', DB::raw('SUM(quantity) as total_quantity'))
            ->where('order_date', '>=', $thirtyDaysAgo)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('sku')
            ->pluck('total_quantity', 'sku')
            ->toArray();

        // Query Best Buy sales data from mirakl_daily_data
        $bestBuySales = DB::table('mirakl_daily_data')
            ->select('sku', DB::raw('SUM(quantity) as total_quantity'))
            ->where('channel_name', 'Best Buy USA')
            ->where('period', 'l30')
            ->where('status', '!=', 'CLOSED')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('sku')
            ->pluck('total_quantity', 'sku')
            ->toArray();

        // Query Doba sales data from doba_daily_data
        $dobaSales = DB::table('doba_daily_data')
            ->select('sku', DB::raw('SUM(quantity) as total_quantity'))
            ->where('period', 'L30')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('sku')
            ->pluck('total_quantity', 'sku')
            ->toArray();

        // Query Macy's sales data from mirakl_daily_data
        $macysSales = DB::table('mirakl_daily_data')
            ->select('sku', DB::raw('SUM(quantity) as total_quantity'))
            ->where('channel_name', "Macy's, Inc.")
            ->where('period', 'l30')
            ->where('status', '!=', 'CLOSED')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('sku')
            ->pluck('total_quantity', 'sku')
            ->toArray();

        // Query Purchasing Power sales data from purchasing_power_sales
        $purchasingPowerSales = PurchasingPowerSale::where('date_created', '>=', $thirtyDaysAgo)
            ->whereNotIn('status', ['Canceled', 'canceled'])
            ->whereNotNull('offer_sku')
            ->where('offer_sku', '!=', '')
            ->selectRaw('UPPER(offer_sku) as sku, SUM(quantity) as total_quantity')
            ->groupBy('sku')
            ->pluck('total_quantity', 'sku')
            ->toArray();

        // Query Reverb sales data from reverb_daily_data
        $reverbSales = DB::table('reverb_daily_data')
            ->select('sku', DB::raw('SUM(quantity) as total_quantity'))
            ->where('order_date', '>=', $thirtyDaysAgo)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('sku')
            ->pluck('total_quantity', 'sku')
            ->toArray();

        // Query Shein sales data from shein_daily_data
        $sheinSales = DB::table('shein_daily_data')
            ->select('seller_sku as sku', DB::raw('SUM(quantity) as total_quantity'))
            ->where('order_processed_on', '>=', $thirtyDaysAgo)
            ->whereNotNull('seller_sku')
            ->where('seller_sku', '!=', '')
            ->groupBy('seller_sku')
            ->pluck('total_quantity', 'sku')
            ->toArray();

        // Query Wayfair sales data from wayfair_daily_data
        $wayfairSales = DB::table('wayfair_daily_data')
            ->select('sku', DB::raw('SUM(quantity) as total_quantity'))
            ->where('period', 'l30')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('sku')
            ->pluck('total_quantity', 'sku')
            ->toArray();

        // Pivot data by SKU
        $skuData = [];
        $sources = [];
        
        foreach ($orderItems as $item) {
            $sku = $item->sku ?? 'N/A';
            $sourceName = trim($item->source_name ?? '');
            
            // Get mapped source name
            $source = $sourceMapping[$sourceName] ?? ucwords(strtolower($sourceName ?: 'Unknown'));
            
            // Track unique sources
            if (!in_array($source, $sources)) {
                $sources[] = $source;
            }
            
            if (!isset($skuData[$sku])) {
                $skuData[$sku] = [
                    'sku' => $sku,
                    'purchasing_power_sales' => isset($purchasingPowerSales[strtoupper($sku)]) ? (int) $purchasingPowerSales[strtoupper($sku)] : 0,
                    'amz_sales' => isset($amazonSales[$sku]) ? (int) $amazonSales[$sku] : 0,
                    'bestbuy_sales' => isset($bestBuySales[$sku]) ? (int) $bestBuySales[$sku] : 0,
                    'macys_sales' => isset($macysSales[$sku]) ? (int) $macysSales[$sku] : 0,
                    'doba_sales' => isset($dobaSales[$sku]) ? (int) $dobaSales[$sku] : 0,
                    'faire_sales' => isset($faireSales[$sku]) ? (int) $faireSales[$sku] : 0,
                    'reverb_sales' => isset($reverbSales[$sku]) ? (int) $reverbSales[$sku] : 0,
                    'shein_sales' => isset($sheinSales[$sku]) ? (int) $sheinSales[$sku] : 0,
                    'wayfair_sales' => isset($wayfairSales[$sku]) ? (int) $wayfairSales[$sku] : 0,
                    'total' => 0,
                ];
            }
            
            $quantity = (int) ($item->total_quantity ?? 0);
            
            // Add quantity to the specific source
            if (!isset($skuData[$sku][$source])) {
                $skuData[$sku][$source] = 0;
            }
            $skuData[$sku][$source] += $quantity;
            $skuData[$sku]['total'] += $quantity;
        }

        // Ensure all SKUs have all sources (with 0 if not present)
        foreach ($skuData as $sku => &$data) {
            foreach ($sources as $source) {
                if (!isset($data[$source])) {
                    $data[$source] = 0;
                }
            }
        }

        // Convert to indexed array and sort by total quantity
        $result = array_values($skuData);
        usort($result, function($a, $b) {
            return $b['total'] - $a['total'];
        });

        // Sort sources alphabetically for better display
        sort($sources);

        return response()->json([
            'message' => 'SKU sales by source loaded successfully (Last 30 days)',
            'data' => $result,
            'sources' => $sources,
            'status' => 200,
        ]);
    }

    public function getStats(Request $request)
    {
        // Get date 30 days ago in PST
        $pstTimezone = 'America/Los_Angeles';
        $thirtyDaysAgo = Carbon::now($pstTimezone)->subDays(30)->startOfDay();
        
        $totalSkus = DB::connection('apicentral')
            ->table('shopify_order_items')
            ->where('order_date', '>=', $thirtyDaysAgo)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->distinct('sku')
            ->count('sku');

        $totalQuantity = DB::connection('apicentral')
            ->table('shopify_order_items')
            ->where('order_date', '>=', $thirtyDaysAgo)
            ->sum('quantity');

        $totalSources = DB::connection('apicentral')
            ->table('shopify_order_items')
            ->where('order_date', '>=', $thirtyDaysAgo)
            ->whereNotNull('source_name')
            ->where('source_name', '!=', '')
            ->distinct('source_name')
            ->count('source_name');

        $totalOrders = DB::connection('apicentral')
            ->table('shopify_order_items')
            ->where('order_date', '>=', $thirtyDaysAgo)
            ->count();

        // Get Amazon sales total
        $amazonSalesTotal = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $thirtyDaysAgo)
            ->where(function ($q) {
                $q->whereNull('o.status')
                    ->orWhereNotIn('o.status', ['Canceled', 'Cancelled']);
            })
            ->sum('i.quantity');

        // Get Faire sales total
        $faireSalesTotal = DB::table('faire_daily_data')
            ->where('order_date', '>=', $thirtyDaysAgo)
            ->sum('quantity');

        // Get Best Buy sales total
        $bestBuySalesTotal = DB::table('mirakl_daily_data')
            ->where('channel_name', 'Best Buy USA')
            ->where('period', 'l30')
            ->where('status', '!=', 'CLOSED')
            ->sum('quantity');

        // Get Doba sales total
        $dobaSalesTotal = DB::table('doba_daily_data')
            ->where('period', 'L30')
            ->sum('quantity');

        // Get Macy's sales total
        $macysSalesTotal = DB::table('mirakl_daily_data')
            ->where('channel_name', "Macy's, Inc.")
            ->where('period', 'l30')
            ->where('status', '!=', 'CLOSED')
            ->sum('quantity');

        // Get Purchasing Power sales total
        $purchasingPowerSalesTotal = PurchasingPowerSale::where('date_created', '>=', $thirtyDaysAgo)
            ->whereNotIn('status', ['Canceled', 'canceled'])
            ->sum('quantity');

        // Get Reverb sales total
        $reverbSalesTotal = DB::table('reverb_daily_data')
            ->where('order_date', '>=', $thirtyDaysAgo)
            ->sum('quantity');

        // Get Shein sales total
        $sheinSalesTotal = DB::table('shein_daily_data')
            ->where('order_processed_on', '>=', $thirtyDaysAgo)
            ->sum('quantity');

        // Get Wayfair sales total
        $wayfairSalesTotal = DB::table('wayfair_daily_data')
            ->where('period', 'l30')
            ->sum('quantity');

        $stats = [
            'total_skus' => $totalSkus,
            'total_quantity' => (int) $totalQuantity,
            'total_sources' => (int) $totalSources,
            'total_orders' => $totalOrders,
            'purchasing_power_sales_total' => (int) $purchasingPowerSalesTotal,
            'amazon_sales_total' => (int) $amazonSalesTotal,
            'bestbuy_sales_total' => (int) $bestBuySalesTotal,
            'macys_sales_total' => (int) $macysSalesTotal,
            'doba_sales_total' => (int) $dobaSalesTotal,
            'faire_sales_total' => (int) $faireSalesTotal,
            'reverb_sales_total' => (int) $reverbSalesTotal,
            'shein_sales_total' => (int) $sheinSalesTotal,
            'wayfair_sales_total' => (int) $wayfairSalesTotal,
        ];

        return response()->json($stats);
    }
}
