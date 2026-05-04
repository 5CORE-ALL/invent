<?php

namespace App\Http\Controllers\JungleScout;

use App\Http\Controllers\Controller;
use App\Models\JungleScoutProductData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompetitorSalesController extends Controller
{
    /**
     * Get competitor sales data for a specific ASIN
     */
    public function getCompetitorSales(Request $request)
    {
        $asin = $request->input('asin');
        
        if (!$asin) {
            return response()->json(['error' => 'ASIN is required'], 400);
        }
        
        $data = JungleScoutProductData::where('asin', $asin)->first();
        
        if (!$data) {
            return response()->json(['error' => 'ASIN not found in JungleScout data'], 404);
        }
        
        return response()->json([
            'asin' => $asin,
            'sku' => $data->sku,
            'parent' => $data->parent,
            'sales_data' => [
                'monthly_revenue' => $data->data['approximate_30_day_revenue'] ?? null,
                'monthly_units_sold' => $data->data['approximate_30_day_units_sold'] ?? null,
                'price' => $data->data['price'] ?? null,
                'calculated_unit_price' => $this->calculateUnitPrice($data->data),
            ],
            'competition' => [
                'number_of_sellers' => $data->data['number_of_sellers'] ?? null,
                'buy_box_owner' => $data->data['buy_box_owner'] ?? null,
                'buy_box_seller_id' => $data->data['buy_box_owner_seller_id'] ?? null,
                'seller_type' => $data->data['seller_type'] ?? null,
            ],
            'product_info' => [
                'brand' => $data->data['brand'] ?? null,
                'category' => $data->data['category'] ?? null,
                'product_rank' => $data->data['product_rank'] ?? null,
                'reviews' => $data->data['reviews'] ?? null,
                'rating' => $data->data['rating'] ?? null,
                'listing_quality_score' => $data->data['listing_quality_score'] ?? null,
            ],
            'last_updated' => $data->updated_at,
        ]);
    }
    
    /**
     * Compare sales data for multiple competitors
     */
    public function compareCompetitors(Request $request)
    {
        $asins = $request->input('asins', []);
        
        if (empty($asins)) {
            return response()->json(['error' => 'ASINs array is required'], 400);
        }
        
        $competitors = JungleScoutProductData::whereIn('asin', $asins)->get();
        
        $comparison = $competitors->map(function($comp) {
            return [
                'asin' => $comp->asin,
                'brand' => $comp->data['brand'] ?? 'Unknown',
                'monthly_revenue' => $comp->data['approximate_30_day_revenue'] ?? 0,
                'monthly_units' => $comp->data['approximate_30_day_units_sold'] ?? 0,
                'price' => $comp->data['price'] ?? 0,
                'sellers_count' => $comp->data['number_of_sellers'] ?? 0,
                'buy_box_owner' => $comp->data['buy_box_owner'] ?? 'Unknown',
                'reviews' => $comp->data['reviews'] ?? 0,
                'rating' => $comp->data['rating'] ?? 0,
                'bsr' => $comp->data['product_rank'] ?? null,
            ];
        });
        
        // Calculate market totals
        $totalRevenue = $comparison->sum('monthly_revenue');
        $totalUnits = $comparison->sum('monthly_units');
        
        // Add market share to each competitor
        $comparison = $comparison->map(function($comp) use ($totalRevenue, $totalUnits) {
            $comp['market_share_revenue'] = $totalRevenue > 0 
                ? round(($comp['monthly_revenue'] / $totalRevenue) * 100, 2) 
                : 0;
            $comp['market_share_units'] = $totalUnits > 0 
                ? round(($comp['monthly_units'] / $totalUnits) * 100, 2) 
                : 0;
            return $comp;
        });
        
        return response()->json([
            'competitors' => $comparison,
            'market_totals' => [
                'total_monthly_revenue' => $totalRevenue,
                'total_monthly_units' => $totalUnits,
                'average_price' => $comparison->avg('price'),
                'total_competitors_analyzed' => $comparison->count(),
            ],
            'top_performer' => [
                'by_revenue' => $comparison->sortByDesc('monthly_revenue')->first(),
                'by_units' => $comparison->sortByDesc('monthly_units')->first(),
                'by_reviews' => $comparison->sortByDesc('reviews')->first(),
            ]
        ]);
    }
    
    /**
     * Get top selling products in a category
     */
    public function getTopSellers(Request $request)
    {
        $category = $request->input('category');
        $limit = $request->input('limit', 20);
        
        $query = JungleScoutProductData::whereNotNull('data')
            ->orderByRaw('CAST(JSON_UNQUOTE(JSON_EXTRACT(data, "$.approximate_30_day_revenue")) AS DECIMAL(10,2)) DESC')
            ->limit($limit);
            
        if ($category) {
            $query->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.category")) = ?', [$category]);
        }
        
        $topSellers = $query->get()->map(function($product) {
            return [
                'asin' => $product->asin,
                'brand' => $product->data['brand'] ?? 'Unknown',
                'category' => $product->data['category'] ?? 'Unknown',
                'monthly_revenue' => $product->data['approximate_30_day_revenue'] ?? 0,
                'monthly_units' => $product->data['approximate_30_day_units_sold'] ?? 0,
                'price' => $product->data['price'] ?? 0,
                'bsr' => $product->data['product_rank'] ?? null,
            ];
        });
        
        return response()->json([
            'top_sellers' => $topSellers,
            'category' => $category ?? 'All Categories',
            'count' => $topSellers->count(),
        ]);
    }
    
    /**
     * Calculate unit price from revenue and units sold
     */
    private function calculateUnitPrice($data)
    {
        $revenue = $data['approximate_30_day_revenue'] ?? 0;
        $units = $data['approximate_30_day_units_sold'] ?? 0;
        
        if ($units > 0) {
            return round($revenue / $units, 2);
        }
        
        return $data['price'] ?? null;
    }
    
    /**
     * Get buy box statistics
     */
    public function getBuyBoxStats(Request $request)
    {
        $stats = JungleScoutProductData::select(
                DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.buy_box_owner")) as buy_box_owner'),
                DB::raw('COUNT(*) as product_count'),
                DB::raw('SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(data, "$.approximate_30_day_revenue")) AS DECIMAL(10,2))) as total_revenue'),
                DB::raw('SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(data, "$.approximate_30_day_units_sold")) AS UNSIGNED)) as total_units')
            )
            ->whereNotNull('data')
            ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.buy_box_owner")) IS NOT NULL')
            ->groupBy('buy_box_owner')
            ->orderByDesc('total_revenue')
            ->limit(20)
            ->get();
        
        return response()->json([
            'buy_box_leaders' => $stats,
            'total_sellers_tracked' => $stats->count(),
        ]);
    }
}
