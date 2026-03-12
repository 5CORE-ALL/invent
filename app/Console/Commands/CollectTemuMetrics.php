<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\ProductMaster;
use App\Models\TemuPricing;
use App\Models\TemuViewData;
use App\Models\TemuDailyData;
use App\Models\TemuAdData;
use App\Models\TemuBadgeDailyData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CollectTemuMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'temu:collect-metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect daily Temu metrics (Price, Views, CVR%, Sales, Spend) for historical tracking';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Temu metrics collection...');
        
        // Use California timezone (Pacific Time) for date
        $today = Carbon::today('America/Los_Angeles');
        
        $this->info('Collection date (California Time): ' . $today->toDateString());
        
        // Get all Temu SKUs from temu_pricing with their current pricing
        $temuPricing = TemuPricing::select('sku', 'goods_id', 'base_price', 'quantity')
            ->whereNotNull('sku')
            ->get()
            ->keyBy(function ($item) {
                return strtoupper(trim($item->sku));
            });
        
        $this->info('Found ' . $temuPricing->count() . ' SKUs in Temu Pricing');
        
        // Get view/clicks data by goods_id
        $temuViewsData = TemuViewData::select('goods_id', DB::raw('SUM(product_clicks) as total_clicks'))
            ->groupBy('goods_id')
            ->get()
            ->keyBy('goods_id');
        
        // Get sales data (temu_l30) from temu_daily_data
        $temuSalesData = TemuDailyData::select('contribution_sku', DB::raw('SUM(quantity_purchased) as temu_l30'))
            ->groupBy('contribution_sku')
            ->get()
            ->keyBy(function ($item) {
                return strtoupper(trim($item->contribution_sku));
            });
        
        // Get ad spend by goods_id
        $temuAdData = TemuAdData::select('goods_id', 'spend')
            ->get()
            ->keyBy('goods_id');
        
        // Get product master data for calculations
        $productData = ProductMaster::whereNull('deleted_at')
            ->get()
            ->keyBy(function ($p) {
                return strtoupper(trim($p->sku));
            });
        
        $collected = 0;
        $skipped = 0;
        
        foreach ($temuPricing as $sku => $pricingData) {
            // Skip parent SKUs
            if (stripos($sku, 'PARENT') !== false || empty($sku)) {
                $skipped++;
                continue;
            }
            
            try {
                $goodsId = $pricingData->goods_id;
                
                // Get base price
                $basePrice = floatval($pricingData->base_price ?? 0);
                
                // Get view/clicks data for this goods_id
                $viewData = $goodsId ? $temuViewsData->get($goodsId) : null;
                $productClicks = $viewData ? intval($viewData->total_clicks ?? 0) : 0;
                
                // Get sales data (temu_l30)
                $salesData = $temuSalesData->get($sku);
                $temuL30 = $salesData ? intval($salesData->temu_l30 ?? 0) : 0;
                
                // Calculate CVR: (Temu L30 / Product Clicks) * 100
                $cvrPercent = 0;
                if ($productClicks > 0 && $temuL30 > 0) {
                    $cvrPercent = ($temuL30 / $productClicks) * 100;
                }
                
                // Get ad spend for this goods_id
                $adData = $goodsId ? $temuAdData->get($goodsId) : null;
                $spend = $adData ? floatval($adData->spend ?? 0) : 0;
                
                // Store in temu_sku_daily_data
                DB::table('temu_sku_daily_data')->updateOrInsert(
                    [
                        'sku' => $sku,
                        'record_date' => $today,
                    ],
                    [
                        'base_price' => round($basePrice, 2),
                        'product_clicks' => $productClicks,
                        'temu_l30' => $temuL30,
                        'cvr_percent' => round($cvrPercent, 2),
                        'spend' => round($spend, 2),
                        'updated_at' => now(),
                    ]
                );
                
                $collected++;
                
            } catch (\Exception $e) {
                $this->error("Error processing SKU {$sku}: " . $e->getMessage());
                Log::error("Error collecting Temu metrics for SKU {$sku}: " . $e->getMessage());
                $skipped++;
            }
        }
        
        $this->info("✓ Collection complete!");
        $this->info("  - Collected: {$collected} SKUs");
        $this->info("  - Skipped: {$skipped} SKUs");
        $this->info("  - Date: " . $today->toDateString());

        // Snapshot badge daily data for today only (same source as badge on decrease page - no backfill)
        $this->snapshotBadgeDailyData($today, $productData);

        return 0;
    }

    /**
     * Build one row of badge summary for the given date and upsert into temu_badge_daily_data.
     */
    protected function snapshotBadgeDailyData(Carbon $recordDate, $productData): void
    {
        $normalizeSku = function ($sku) {
            $sku = strtoupper(trim((string) $sku));
            $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
            $sku = preg_replace('/\s+/', ' ', $sku);
            return $sku;
        };
        $normalizedPmSet = $productData->keys()->flip()->all();
        $noSpaceToNormalized = [];
        foreach (array_keys($normalizedPmSet) as $nk) {
            $noSpace = str_replace(' ', '', $nk);
            if ($noSpace !== '') {
                $noSpaceToNormalized[$noSpace] = $nk;
            }
        }

        // Same sales logic as Temu decrease page badge (getTemuDecreaseData): all orders matching PM, no date filter – so chart and badge match
        $allowedRawSkus = TemuDailyData::select('contribution_sku')->distinct()->get()
            ->filter(function ($r) use ($normalizeSku, $normalizedPmSet, $noSpaceToNormalized) {
                $n = $normalizeSku($r->contribution_sku ?? '');
                return isset($normalizedPmSet[$n]) || isset($noSpaceToNormalized[str_replace(' ', '', $n)]);
            })
            ->pluck('contribution_sku')
            ->unique()
            ->values()
            ->all();
        $salesOrderRows = TemuDailyData::whereIn('contribution_sku', $allowedRawSkus)
            ->get(['contribution_sku', 'order_id', 'quantity_purchased', 'base_price_total']);
        $totalOrders = 0;
        $totalQuantity = 0;
        $totalSales = 0.0;
        foreach ($salesOrderRows as $row) {
            if (trim((string) ($row->contribution_sku ?? '')) === '' || trim((string) ($row->order_id ?? '')) === '') {
                continue;
            }
            $totalOrders++;
            $qty = (int) ($row->quantity_purchased ?? 0);
            $base = (float) ($row->base_price_total ?? 0);
            $totalQuantity += $qty;
            $totalSales += $base * $qty;
        }

        // Aggregates from temu_sku_daily_data for this date
        $skuDaily = DB::table('temu_sku_daily_data')
            ->where('record_date', $recordDate->toDateString())
            ->selectRaw('COUNT(*) as sku_count, COALESCE(SUM(product_clicks), 0) as total_views, COALESCE(AVG(product_clicks), 0) as avg_views, COALESCE(SUM(spend), 0) as total_spend, COALESCE(AVG(cvr_percent), 0) as avg_cvr_pct')
            ->first();
        $skuCount = (int) ($skuDaily->sku_count ?? 0);
        $totalViews = (int) ($skuDaily->total_views ?? 0);
        $avgViews = round((float) ($skuDaily->avg_views ?? 0), 2);
        $totalSpend = round((float) ($skuDaily->total_spend ?? 0), 2);
        $avgCvrPct = round((float) ($skuDaily->avg_cvr_pct ?? 0), 2);

        TemuBadgeDailyData::updateOrCreate(
            ['record_date' => $recordDate->toDateString()],
            [
                'total_sales' => round($totalSales, 2),
                'total_orders' => $totalOrders,
                'total_quantity' => $totalQuantity,
                'sku_count' => $skuCount,
                'total_views' => $totalViews,
                'avg_views' => $avgViews,
                'total_spend' => $totalSpend,
                'avg_cvr_pct' => $avgCvrPct,
            ]
        );
        $this->info("  - Badge daily snapshot saved for " . $recordDate->toDateString());
    }
}
