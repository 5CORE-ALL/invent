<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FbaDailyMetrics;
use App\Http\Controllers\FbaDataController;
use Illuminate\Http\Request;

class SaveFbaDailyMetrics extends Command
{
    protected $signature   = 'fba:save-daily-metrics';
    protected $description = 'Snapshot today\'s FBA badge metrics into fba_daily_metrics for chart history.';

    public function handle(): int
    {
        $today = now()->toDateString();

        // Reuse the exact same data pipeline as the frontend
        $controller = app(FbaDataController::class);
        $request    = Request::create('/fba-data-json', 'GET');
        $response   = $controller->fbaDataJson($request);
        $rows       = json_decode($response->getContent(), true);

        if (!is_array($rows)) {
            $this->error('Failed to fetch FBA data.');
            return Command::FAILURE;
        }

        // Aggregate — same logic as updateSummary() in JS
        $totalSales = 0; $totalPft = 0;
        $priceSum = 0; $priceCount = 0;
        $dilSum = 0; $dilCount = 0;
        $totalInv = 0; $totalL30 = 0;
        $totalViews = 0; $zeroSold = 0;
        $totalSpend = 0;

        foreach ($rows as $row) {
            if (!empty($row['is_parent'])) continue;
            if (($row['FBA_Quantity'] ?? 0) <= 0) continue;

            $totalSales  += (float) ($row['SALES_AMT']       ?? 0);
            $totalPft    += (float) ($row['PFT_AMT']         ?? 0);
            $totalInv    += (int)   ($row['FBA_Quantity']    ?? 0);
            $totalL30    += (int)   ($row['l30_units']       ?? 0);
            $totalViews  += (int)   ($row['Current_Month_Views'] ?? 0);
            $totalSpend  += (float) ($row['Total_Spend_L30'] ?? 0);

            $price = (float) ($row['FBA_Price'] ?? 0);
            if ($price > 0) { $priceSum += $price; $priceCount++; }

            $l30 = (int) ($row['l30_units'] ?? 0);
            if ($l30 === 0) $zeroSold++;

            // DIL: use FBA_Dil already computed, same as badge (simple avg of all rows)
            $dil = (float) ($row['FBA_Dil'] ?? 0);
            if (!is_nan($dil)) { $dilSum += $dil; $dilCount++; }
        }

        $avgGpft  = $totalSales > 0 ? round(($totalPft  / $totalSales) * 100, 2) : 0;
        $avgPrice = $priceCount > 0 ? round($priceSum  / $priceCount, 2) : 0;
        $avgCvr   = $totalViews > 0 ? round(($totalL30  / $totalViews) * 100, 2) : 0;
        $avgDil   = $dilCount   > 0 ? round($dilSum / $dilCount, 2) : 0;
        $adsPct   = $totalSales > 0 ? round(($totalSpend / $totalSales) * 100, 2) : 0;
        $roi      = $totalSales > 0 ? round(($totalPft  / $totalSales) * 100, 2) : 0;

        FbaDailyMetrics::updateOrCreate(
            ['record_date' => $today],
            [
                'sales'     => round($totalSales, 2),
                'pft'       => round($totalPft,   2),
                'gpft'      => $avgGpft,
                'price'     => $avgPrice,
                'cvr'       => $avgCvr,
                'views'     => $totalViews,
                'inv'       => $totalInv,
                'l30'       => $totalL30,
                'dil'       => $avgDil,
                'zero_sold' => $zeroSold,
                'ads_pct'   => $adsPct,
                'spend'     => round($totalSpend, 2),
                'roi'       => $roi,
            ]
        );

        $this->info("FBA daily metrics saved for {$today}: Sales=$" . number_format($totalSales, 0));
        return Command::SUCCESS;
    }
}
