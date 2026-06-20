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
     *
     * Reads from the live decrease-page endpoint (TemuController::getTemuDecreaseData)
     * so the saved snapshot is BYTE-FOR-BYTE the same dataset the live badge sums
     * over — same SKU set, same sales source (Temu Orders API for Temu 1 / temu2 daily
     * for Temu 2), same view-data joins, same $2.99 ship-bumper applied to revenue,
     * etc. That keeps the badge value on the page and the chart point for "today"
     * perfectly in sync.
     */
    protected function snapshotBadgeDailyData(Carbon $recordDate, $productData): void
    {
        try {
            // Hit the same endpoint the page hits. Default Request = Temu 1 / L30,
            // which is what /temu-decrease shows.
            $controller = app(\App\Http\Controllers\MarketPlace\TemuController::class);
            $response = $controller->getTemuDecreaseData(new \Illuminate\Http\Request());
            $payload = json_decode($response->getContent(), true);
        } catch (\Throwable $e) {
            Log::error('Temu badge snapshot: getTemuDecreaseData call failed: ' . $e->getMessage());
            return;
        }

        $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $salesSummary = is_array($payload['sales_summary'] ?? null) ? $payload['sales_summary'] : [];

        // Order/quantity/revenue come from the controller's own sales_summary block
        // (already correct — it uses TemuShopifySalesService for Temu 1 / temu2_daily_data
        // for Temu 2, exactly like the badge on the live page).
        $totalOrders   = (int) ($salesSummary['total_orders'] ?? 0);
        $totalQuantity = (int) ($salesSummary['total_quantity'] ?? 0);
        $totalSales    = round((float) ($salesSummary['total_revenue'] ?? 0), 2);

        // Views + spend + sku count are aggregated from the same row payload the
        // live updateSummary loop iterates, so they match what the user sees.
        $totalViews = 0;
        $totalSpend = 0.0;
        $skuCount = 0;
        $skusWithViews = 0;
        foreach ($rows as $row) {
            $sku = (string) ($row['sku'] ?? '');
            if ($sku === '' || stripos($sku, 'PARENT') !== false) continue;
            $skuCount++;
            $clicks = (int) ($row['product_clicks'] ?? 0);
            if ($clicks > 0) {
                $totalViews += $clicks;
                $skusWithViews++;
            }
            $totalSpend += (float) ($row['spend'] ?? 0);
        }
        $totalSpend = round($totalSpend, 2);
        $avgViews   = $skusWithViews > 0 ? round($totalViews / $skusWithViews, 2) : 0.0;

        // Weighted CVR — exact formula the live badge uses (qtyPerViews = totalQuantity / totalViews × 100).
        $avgCvrPct = $totalViews > 0 ? round(($totalQuantity / $totalViews) * 100, 2) : 0.0;

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
