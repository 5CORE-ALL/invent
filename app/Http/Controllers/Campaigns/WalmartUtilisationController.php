<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\WalmartCampaignReport;
use App\Models\WalmartDataView;
use App\Models\WalmartProductSheet;
use App\Models\Walmart7ubDailyCount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class WalmartUtilisationController extends Controller
{
    public function index(){
        return view('campaign.walmart-utilized-kw-ads');
    }

    public function bgtUtilisedView(){
        return view('campaign.walmart-bgt-util');
    }

    public function overUtilisedView(){
        return view('campaign.walmart-over-utili');
    }

    public function underUtilisedView(){
        return view('campaign.walmart-under-utili');
    }

    public function correctlyUtilisedView(){
        return view('campaign.walmart-correctly-utili');
    }

    public function getWalmartAdsData()
    {
        $normalizeSku = fn($sku) => strtoupper(trim(preg_replace('/\s+/', ' ', str_replace("\xc2\xa0", ' ', $sku))));

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')
            ->filter()
            ->unique()
            ->map(fn($sku) => $normalizeSku($sku))
            ->values()
            ->all();

        $walmartProductSheet = WalmartProductSheet::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));

        $nrValues = WalmartDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        // Get campaign data - prioritize L30 for status, fallback to L1, then L7
        $walmartCampaignReportsL30 = WalmartCampaignReport::where('report_range', 'L30')->whereIn('campaignName', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->campaignName));
        $walmartCampaignReportsL1  = WalmartCampaignReport::where('report_range', 'L1')->whereIn('campaignName', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->campaignName));
        $walmartCampaignReportsL7  = WalmartCampaignReport::where('report_range', 'L7')->whereIn('campaignName', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->campaignName));
        
        // Get any campaign for budget/name (prefer L30, fallback to L1, then L7)
        $walmartCampaignReportsAll = WalmartCampaignReport::whereIn('campaignName', $skus)
            ->orderByRaw("CASE WHEN report_range = 'L30' THEN 1 WHEN report_range = 'L1' THEN 2 WHEN report_range = 'L7' THEN 3 ELSE 4 END")
            ->get()
            ->unique('campaignName')
            ->keyBy(fn($item) => $normalizeSku($item->campaignName));

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = $normalizeSku($pm->sku);
            $parent = $pm->parent;

            $amazonSheet = $walmartProductSheet[$sku] ?? null;
            $shopify = $shopifyData[$sku] ?? null;

            // Campaign name & budget - get from any report range
            $matchedCampaign = $walmartCampaignReportsAll[$sku] ?? null;

            if (!$matchedCampaign) {
                continue;
            }

            // Metrics by report_range
            $matchedCampaignL30 = $walmartCampaignReportsL30[$sku] ?? null;
            $matchedCampaignL7  = $walmartCampaignReportsL7[$sku] ?? null;
            $matchedCampaignL1  = $walmartCampaignReportsL1[$sku] ?? null;

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['WA_L30'] = $amazonSheet->l30 ?? 0;

            // Campaign info (all SKUs)
            $row['campaignName'] = $matchedCampaign->campaignName ?? '';
            $row['campaignBudgetAmount'] = $matchedCampaign->budget ?? '';
            
            // Get status from L30 first, then L1, then L7, then any
            $status = null;
            if ($matchedCampaignL30 && $matchedCampaignL30->status) {
                $status = $matchedCampaignL30->status;
            } elseif ($matchedCampaignL1 && $matchedCampaignL1->status) {
                $status = $matchedCampaignL1->status;
            } elseif ($matchedCampaignL7 && $matchedCampaignL7->status) {
                $status = $matchedCampaignL7->status;
            } elseif ($matchedCampaign->status) {
                $status = $matchedCampaign->status;
            }
            
            // Normalize status: "Live", "live", "enabled" -> "LIVE", everything else -> "PAUSED"
            $statusUpper = strtoupper(trim($status ?? ''));
            if (in_array($statusUpper, ['LIVE', 'ENABLED', 'ACTIVE', 'RUNNING'])) {
                $row['campaignStatus'] = 'LIVE';
            } else {
                $row['campaignStatus'] = 'PAUSED';
            }

            // Metrics
            $row['clicks_l30'] = $matchedCampaignL30 ? ($matchedCampaignL30->clicks ?? 0) : 0;
            $row['spend_l7']   = $matchedCampaignL7 ? ($matchedCampaignL7->spend ?? 0) : 0;
            $row['spend_l1']   = $matchedCampaignL1 ? ($matchedCampaignL1->spend ?? 0) : 0;
            $row['cpc_l7']     = $matchedCampaignL7 ? ($matchedCampaignL7->cpc ?? 0) : 0;
            $row['cpc_l1']     = $matchedCampaignL1 ? ($matchedCampaignL1->cpc ?? 0) : 0;
            
            // ACOS calculation: (spend/sales) * 100
            // Use L30 data for ACOS
            $spendL30 = $matchedCampaignL30 ? (float)($matchedCampaignL30->spend ?? 0) : 0;
            $salesL30 = $matchedCampaignL30 ? (float)($matchedCampaignL30->sales ?? 0) : 0;
            if ($salesL30 > 0) {
                $row['acos_l30'] = round(($spendL30 / $salesL30) * 100, 2);
            } else {
                $row['acos_l30'] = $spendL30 > 0 ? 100 : 0;
            }

            // NR
            $row['NRA']  = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) $raw = json_decode($raw, true);
                if (is_array($raw)) {
                    $row['NRA'] = $raw['NR'] ?? null;
                }
            }

            $result[] = (object) $row;
        }

        $uniqueResult = collect($result)->unique('sku')->values()->all();

        // Calculate totals from ALL L30 campaigns (not just filtered ones)
        $allL30Campaigns = WalmartCampaignReport::where('report_range', 'L30')->get();
        
        $totalSpend = 0;
        $totalSales = 0;
        
        foreach ($allL30Campaigns as $campaign) {
            $totalSpend += (float)($campaign->spend ?? 0);
            $totalSales += (float)($campaign->sales ?? 0);
        }
        
        // Calculate average ACOS: (total spend / total sales) * 100
        $avgAcos = $totalSales > 0 ? ($totalSpend / $totalSales) * 100 : 0;

        // Calculate and store today's 7UB counts
        $this->calculateAndStore7ubCounts($avgAcos, $uniqueResult);

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $uniqueResult,
            'total_spend' => round($totalSpend, 2),
            'total_sales' => round($totalSales, 2),
            'avg_acos' => round($avgAcos, 2),
            'status'  => 200,
        ]);
    }

    /**
     * Calculate and store daily 7UB counts
     */
    private function calculateAndStore7ubCounts($avgAcos, $data)
    {
        $today = Carbon::today();
        
        // Check if today's count already exists
        $existingCount = Walmart7ubDailyCount::where('date', $today)->first();
        
        if ($existingCount) {
            // Already calculated for today
            return;
        }

        $pinkCount = 0;
        $redCount = 0;
        $greenCount = 0;

        foreach ($data as $row) {
            $spend_l7 = (float)($row->spend_l7 ?? 0);
            $acos = (float)($row->acos_l30 ?? 0);
            
            // Calculate ALD BGT using new ranges
            $aldBgt = 0;
            // ACOS > 25% → ALD BGT = 1
            if ($acos > 25) {
                $aldBgt = 1;
            }
            // ACOS 20%-25% → ALD BGT = 2
            elseif ($acos >= 20 && $acos <= 25) {
                $aldBgt = 2;
            }
            // ACOS 15%-20% → ALD BGT = 4
            elseif ($acos >= 15 && $acos < 20) {
                $aldBgt = 4;
            }
            // ACOS 10%-15% → ALD BGT = 6
            elseif ($acos >= 10 && $acos < 15) {
                $aldBgt = 6;
            }
            // ACOS 5%-10% → ALD BGT = 8
            elseif ($acos >= 5 && $acos < 10) {
                $aldBgt = 8;
            }
            // ACOS 0.01%-5% → ALD BGT = 10
            elseif ($acos >= 0.01 && $acos < 5) {
                $aldBgt = 10;
            }
            
            // Calculate 7UB = (L7 ad spend/(ald bgt*7))*100
            $ub7 = ($aldBgt > 0 && $aldBgt * 7 > 0) ? ($spend_l7 / ($aldBgt * 7)) * 100 : 0;
            
            // Categorize
            if ($ub7 > 90) {
                $pinkCount++;
            } elseif ($ub7 < 70) {
                $redCount++;
            } elseif ($ub7 >= 70 && $ub7 <= 90) {
                $greenCount++;
            }
        }

        // Store the counts
        Walmart7ubDailyCount::updateOrCreate(
            ['date' => $today],
            [
                'pink_count' => $pinkCount,
                'red_count' => $redCount,
                'green_count' => $greenCount,
            ]
        );
    }

    /**
     * Get 7UB chart data
     */
    public function get7ubChartData()
    {
        try {
            // Check if table exists
            if (!\Schema::hasTable('walmart_7ub_daily_counts')) {
                return response()->json([
                    'status' => 200,
                    'data' => [],
                    'message' => 'Table does not exist yet. Please run migration.',
                ]);
            }

            $data = Walmart7ubDailyCount::orderBy('date', 'asc')
                ->get()
                ->map(function ($item) {
                    $date = $item->date;
                    if ($date instanceof \Carbon\Carbon || $date instanceof \DateTime) {
                        $dateStr = $date->format('Y-m-d');
                    } else {
                        $dateStr = is_string($date) ? $date : (string)$date;
                    }
                    
                    return [
                        'date' => $dateStr,
                        'pink_count' => (int)($item->pink_count ?? 0),
                        'red_count' => (int)($item->red_count ?? 0),
                        'green_count' => (int)($item->green_count ?? 0),
                    ];
                });

            return response()->json([
                'status' => 200,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in get7ubChartData: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'status' => 500,
                'message' => 'Error loading chart data: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

}
