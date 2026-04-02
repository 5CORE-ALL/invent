<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FbaDailyMetrics;
use App\Models\FbaTable;
use App\Models\FbaPrice;
use App\Models\FbaMonthlySale;
use App\Models\FbaManualData;
use App\Models\FbaReportsMaster;
use App\Models\FbaShipCalculation;
use App\Models\MarketplacePercentage;
use App\Models\AmazonSpCampaignReport;

class SaveFbaDailyMetrics extends Command
{
    protected $signature   = 'fba:save-daily-metrics';
    protected $description = 'Snapshot today\'s FBA badge metrics into fba_daily_metrics for chart history.';

    public function handle(): int
    {
        $today = now()->toDateString();

        // ── Fetch all needed data ─────────────────────────────────────────
        $fbaData   = FbaTable::where('quantity_available', '>', 0)->get()->keyBy(fn($r) => strtoupper(trim($r->seller_sku)));
        $prices    = FbaPrice::all()->keyBy(fn($r) => strtoupper(trim($r->seller_sku)));
        $sales     = FbaMonthlySale::all()->keyBy(fn($r) => strtoupper(trim($r->sku)));
        $manuals   = FbaManualData::all()->keyBy(fn($r) => strtoupper(trim($r->sku)));
        $reports   = FbaReportsMaster::all()->keyBy(fn($r) => strtoupper(trim($r->seller_sku)));
        $ships     = FbaShipCalculation::all()->keyBy(fn($r) => strtoupper(trim($r->sku)));

        $amazonPct = MarketplacePercentage::where('marketplace', 'amazon')->value('percentage') ?? 34;

        // Ad spend (L30 sponsored products)
        $skus = $fbaData->keys()->toArray();
        $adSpendTotal = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%'.$sku.'%');
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->sum('cost');

        // ── Aggregate ─────────────────────────────────────────────────────
        $totalSales = 0; $totalPft = 0; $totalInv = 0; $totalL30 = 0;
        $gpftSum = 0; $gpftCount = 0;
        $priceSum = 0; $priceCount = 0;
        $cvrSum = 0; $cvrCount = 0;
        $dilSum = 0; $dilCount = 0;
        $zeroSold = 0;
        $totalViews = 0;

        foreach ($fbaData as $sku => $fba) {
            $inv   = (int) ($fba->quantity_available ?? 0);
            $price = (float) ($prices->get($sku)?->price ?? 0);
            $l30   = (int)  ($sales->get($sku)?->l30_units ?? 0);
            $views = (int)  ($reports->get($sku)?->current_month_views ?? 0);
            $ship  = (float)($ships->get($sku)?->ship_calculation ?? 0);

            $manual = $manuals->get($sku);
            $lp     = 0;
            if ($manual) {
                $data = is_array($manual->data) ? $manual->data : (json_decode($manual->data, true) ?? []);
                $lp   = (float) ($data['lp'] ?? $data['LP'] ?? 0);
            }

            $totalInv   += $inv;
            $totalL30   += $l30;
            $totalViews += $views;
            if ($l30 === 0) $zeroSold++;

            if ($price > 0) {
                $salesAmt = $price * $l30;
                $totalSales += $salesAmt;

                $gpft = ($price * (1 - ($amazonPct / 100 + 0.05)) - $lp - $ship) / $price * 100;
                $pft  = ($price * (1 - ($amazonPct / 100 + 0.05)) - $lp - $ship) * $l30;
                $totalPft  += $pft;
                $gpftSum   += $gpft; $gpftCount++;

                $priceSum  += $price; $priceCount++;

                if ($views > 0) { $cvrSum += ($l30 / $views) * 100; $cvrCount++; }
                if ($inv   > 0) { $dilSum += ($l30 / $inv)   * 100; $dilCount++; }
            }
        }

        $avgGpft  = $gpftCount  > 0 ? round($gpftSum  / $gpftCount,  2) : 0;
        $avgPrice = $priceCount > 0 ? round($priceSum  / $priceCount, 2) : 0;
        $avgCvr   = $cvrCount   > 0 ? round($cvrSum    / $cvrCount,   2) : 0;
        $avgDil   = $dilCount   > 0 ? round($dilSum    / $dilCount,   2) : 0;
        $adsPct   = $totalSales > 0 ? round(($adSpendTotal / $totalSales) * 100, 2) : 0;
        $roi      = $totalSales > 0 ? round(($totalPft / $totalSales) * 100, 2) : 0;

        FbaDailyMetrics::updateOrCreate(
            ['record_date' => $today],
            [
                'sales'     => round($totalSales, 2),
                'pft'       => round($totalPft, 2),
                'gpft'      => $avgGpft,
                'price'     => $avgPrice,
                'cvr'       => $avgCvr,
                'views'     => $totalViews,
                'inv'       => $totalInv,
                'l30'       => $totalL30,
                'dil'       => $avgDil,
                'zero_sold' => $zeroSold,
                'ads_pct'   => $adsPct,
                'spend'     => round($adSpendTotal, 2),
                'roi'       => $roi,
            ]
        );

        $this->info("FBA daily metrics saved for {$today}.");
        return Command::SUCCESS;
    }
}
