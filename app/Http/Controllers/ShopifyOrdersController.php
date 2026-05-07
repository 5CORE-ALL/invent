<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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

        $stats = [
            'total_skus' => $totalSkus,
            'total_quantity' => (int) $totalQuantity,
            'total_sources' => (int) $totalSources,
            'total_orders' => $totalOrders,
        ];

        return response()->json($stats);
    }
}
