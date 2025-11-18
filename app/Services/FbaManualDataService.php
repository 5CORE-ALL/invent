<?php

namespace App\Services;

use App\Models\FbaManualData;
use App\Models\FbaShipCalculation;
use App\Models\ProductMaster;
use App\Models\FbaTable;
use App\Models\FbaPrice;
use App\Models\FbaReportsMaster;
use App\Models\FbaMonthlySale;
use App\Models\FbaOrder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class FbaManualDataService
{
    /**
     * Export FBA data to CSV with all columns
     */
    public function exportToCSV()
    {
        // Get data
        $productData = ProductMaster::whereNull('deleted_at')->get()->keyBy(fn($p) => strtoupper(trim($p->sku)));
        $fbaData = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")->get()
            ->keyBy(fn($item) => strtoupper(trim(preg_replace('/\s*FBA\s*/i', '', $item->seller_sku))));
        $fbaPriceData = FbaPrice::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")->get()
            ->keyBy(fn($item) => strtoupper(trim(preg_replace('/\s*FBA\s*/i', '', $item->seller_sku))));
        $fbaReportsData = FbaReportsMaster::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")->get()
            ->keyBy(fn($item) => strtoupper(trim(preg_replace('/\s*FBA\s*/i', '', $item->seller_sku))));
        $fbaMonthlySales = FbaMonthlySale::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")->get()
            ->keyBy(fn($item) => strtoupper(trim(preg_replace('/\s*FBA\s*/i', '', $item->seller_sku))));
        $fbaManualData = FbaManualData::all()->keyBy(fn($item) => strtoupper(trim($item->sku)));
        $fbaDispatchDates = FbaOrder::all()->keyBy('sku');

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="fba_complete_data_' . date('Y-m-d_H-i-s') . '.csv"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ];

        $callback = function () use ($fbaData, $productData, $fbaPriceData, $fbaReportsData, $fbaMonthlySales, $fbaManualData, $fbaDispatchDates) {
            $file = fopen('php://output', 'w');

            // Write headers
            $columns = [
                'Parent', 'Child SKU', 'FBA SKU', 'FBA INV', 'L60 Units', 'L30 Units', 'FBA Dil',
                'FBA Price', 'Pft%', 'ROI%', 'TPFT', 'S Price', 'SPft%', 'SROI%', 'LP', 'FBA Ship',
                'CVR', 'Views', 'Listed', 'Live', 'FBA Fee', 'FBA Fee Manual', 'ASIN', 'Barcode',
                'Done', 'Dispatch Date', 'Weight', 'Qty Box', 'Sent Qty', 'Send Cost', 'IN Charges',
                'WH INV Red', 'Ship Amt', 'Inbound Qty', 'FBA Send', 'Dimensions',
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
            ];
            fputcsv($file, $columns);

            // Write data
            foreach ($fbaData as $sku => $fba) {
                try {
                    $product = $productData->get($sku);
                    $fbaPriceInfo = $fbaPriceData->get($sku);
                    $fbaReportsInfo = $fbaReportsData->get($sku);
                    $monthlySales = $fbaMonthlySales->get($sku);
                    $manual = $fbaManualData->get(strtoupper(trim($fba->seller_sku)));
                    $dispatchDate = $fbaDispatchDates->get($sku);

                    $PRICE = $fbaPriceInfo ? floatval($fbaPriceInfo->price ?? 0) : 0;
                    $LP = $product ? floatval($product->lp ?? 0) : 0;
                    $FBA_SHIP = $this->calculateFbaShipCalculation(
                        $fba->seller_sku,
                        $manual ? ($manual->data['fba_fee_manual'] ?? 0) : 0,
                        $manual ? ($manual->data['send_cost'] ?? 0) : 0,
                        $manual ? ($manual->data['in_charges'] ?? 0) : 0
                    );
                    $S_PRICE = $manual ? floatval($manual->data['s_price'] ?? 0) : 0;

                    $pft = ($PRICE > 0) ? (($PRICE * 0.66) - $LP - $FBA_SHIP) / $PRICE : 0;
                    $roi = ($LP > 0) ? (($PRICE * 0.66) - $LP - $FBA_SHIP) / $LP : 0;
                    $spft = ($S_PRICE > 0) ? (($S_PRICE * 0.66) - $LP - $FBA_SHIP) / $S_PRICE : 0;
                    $sroi = ($LP > 0) ? (($S_PRICE * 0.66) - $LP - $FBA_SHIP) / $LP : 0;
                    $fbaDil = ($monthlySales ? floatval($monthlySales->l30_units ?? 0) : 0) / ($fba->quantity_available ?: 1) * 100;
                    $cvr = ($monthlySales ? floatval($monthlySales->l30_units ?? 0) : 0) / ($fbaReportsInfo ? floatval($fbaReportsInfo->current_month_views ?: 1) : 1) * 100;

                    $rowData = [
                        $product ? ($product->parent ?? '') : '',
                        $sku,
                        $fba->seller_sku,
                        $fba->quantity_available ?? 0,
                        $monthlySales ? ($monthlySales->l60_units ?? 0) : 0,
                        $monthlySales ? ($monthlySales->l30_units ?? 0) : 0,
                        round($fbaDil, 2),
                        round($PRICE, 2),
                        round($pft * 100, 2),
                        round($roi * 100, 2),
                        round(($PRICE * 0.66) - $LP - $FBA_SHIP, 2),
                        round($S_PRICE, 2),
                        round($spft * 100, 2),
                        round($sroi * 100, 2),
                        round($LP, 2),
                        round($FBA_SHIP, 2),
                        round($cvr, 2),
                        $fbaReportsInfo ? ($fbaReportsInfo->current_month_views ?? 0) : 0,
                        $manual && isset($manual->data['listed']) && $manual->data['listed'] ? 'Yes' : 'No',
                        $manual && isset($manual->data['live']) && $manual->data['live'] ? 'Yes' : 'No',
                        $fbaReportsInfo ? round($fbaReportsInfo->fulfillment_fee ?? 0, 2) : 0,
                        $manual ? ($manual->data['fba_fee_manual'] ?? 0) : 0,
                        $fba->asin ?? '',
                        $manual ? ($manual->data['barcode'] ?? '') : '',
                        $manual && isset($manual->data['done']) && $manual->data['done'] ? 'Yes' : 'No',
                        $dispatchDate ? $dispatchDate->dispatch_date : ($manual ? ($manual->data['dispatch_date'] ?? '') : ''),
                        $manual ? ($manual->data['weight'] ?? 0) : 0,
                        $manual ? ($manual->data['quantity_in_each_box'] ?? 0) : 0,
                        $manual ? ($manual->data['total_quantity_sent'] ?? 0) : 0,
                        $manual ? ($manual->data['send_cost'] ?? 0) : 0,
                        $manual ? ($manual->data['in_charges'] ?? 0) : 0,
                        $manual && isset($manual->data['warehouse_inv_reduction']) && $manual->data['warehouse_inv_reduction'] ? 'Yes' : 'No',
                        $manual ? ($manual->data['shipping_amount'] ?? 0) : 0,
                        $manual ? ($manual->data['inbound_quantity'] ?? 0) : 0,
                        $manual && isset($manual->data['fba_send']) && $manual->data['fba_send'] ? 'Yes' : 'No',
                        $manual ? ($manual->data['dimensions'] ?? '') : '',
                        $monthlySales ? ($monthlySales->jan ?? 0) : 0,
                        $monthlySales ? ($monthlySales->feb ?? 0) : 0,
                        $monthlySales ? ($monthlySales->mar ?? 0) : 0,
                        $monthlySales ? ($monthlySales->apr ?? 0) : 0,
                        $monthlySales ? ($monthlySales->may ?? 0) : 0,
                        $monthlySales ? ($monthlySales->jun ?? 0) : 0,
                        $monthlySales ? ($monthlySales->jul ?? 0) : 0,
                        $monthlySales ? ($monthlySales->aug ?? 0) : 0,
                        $monthlySales ? ($monthlySales->sep ?? 0) : 0,
                        $monthlySales ? ($monthlySales->oct ?? 0) : 0,
                        $monthlySales ? ($monthlySales->nov ?? 0) : 0,
                        $monthlySales ? ($monthlySales->dec ?? 0) : 0
                    ];

                    fputcsv($file, $rowData);
                } catch (\Exception $e) {
                    Log::error("CSV export error for SKU {$sku}: " . $e->getMessage());
                    continue;
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import FBA manual data from CSV
     */
    public function importFromCSV($file)
    {
        try {
            $content = file_get_contents($file->getRealPath());
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
            $csvData = array_map('str_getcsv', explode("\n", $content));
            array_shift($csvData); // Remove header

            $imported = 0;

            foreach ($csvData as $row) {
                if (empty($row) || count($row) < 1) continue;
                
                $sku = trim($row[0] ?? '');
                if (empty($sku)) continue;

                // Try to find by exact SKU or by FBA SKU pattern
                $manual = FbaManualData::where('sku', strtoupper($sku))
                    ->orWhere('sku', 'LIKE', '%' . strtoupper($sku) . ' FBA%')
                    ->orWhere('sku', 'LIKE', '%' . strtoupper($sku) . 'FBA%')
                    ->first();

                if (!$manual) {
                    $manual = new FbaManualData();
                    $manual->sku = strtoupper($sku);
                }
                
                $existingData = $manual->data ?? [];
                
                $manual->data = array_merge($existingData, [
                    'dimensions' => $this->cleanText($row[1] ?? ''),
                    'weight' => $this->cleanText($row[2] ?? ''),
                    'quantity_in_each_box' => $this->cleanText($row[3] ?? ''),
                    'total_quantity_sent' => $this->cleanText($row[4] ?? ''),
                    'total_send_cost' => $this->cleanText($row[5] ?? ''),
                    'inbound_quantity' => $this->cleanText($row[6] ?? ''),
                    'send_cost' => $this->cleanText($row[7] ?? ''),
                    'in_charges' => $this->cleanText($row[8] ?? '')
                ]);
                
                $manual->save();
                $imported++;
            }

            return ['success' => true, 'imported' => $imported, 'updated' => 0, 'errors' => []];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Clean text to ensure UTF-8 encoding
     */
    private function cleanText($text)
    {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        return preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
    }

    /**
     * Download sample CSV template
     */
    public function downloadSampleTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="fba_manual_data_sample.csv"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['SKU', 'Dimensions', 'Weight', 'Qty in each box', 'Total qty Sent', 'Total Send Cost', 'Inbound qty', 'Send cost', 'IN Charges']);
            fputcsv($file, ['SAMPLE-SKU-001', '10x8x6', '2.5', '10', '100', '500', '20', '50', '25']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Calculate FBA Ship Calculation (Direct calculation only - No database save)
     * 
     * Logic:
     * - If fulfillment_fee exists and > 0 in FbaReportsMaster, use fulfillment_fee + send_cost + in_charges
     * - If fulfillment_fee is 0 or SKU doesn't exist, use manual data: fba_fee_manual + send_cost + in_charges
     *
     * @param string $sku The FBA SKU (can be with or without 'FBA' suffix)
     * @param float|string $fbaFeeManual Manual FBA fee value
     * @param float|string $sendCost Send cost value from manual data
     * @param float|string $inCharges IN charges value from manual data
     * @return float Calculated FBA Ship Calculation value
     */
    public function calculateFbaShipCalculation($sku, $fbaFeeManual = 0, $sendCost = 0, $inCharges = 0)
    {
        // Normalize SKU by removing FBA suffix
        $baseSku = preg_replace('/\s*FBA\s*/i', '', $sku);
        $baseSku = strtoupper(trim($baseSku));

        // Get Fulfillment Fee from FbaReportsMaster
        $fbaReport = \App\Models\FbaReportsMaster::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->get()
            ->filter(function ($item) use ($baseSku) {
                $itemSku = preg_replace('/\s*FBA\s*/i', '', $item->seller_sku);
                return strtoupper(trim($itemSku)) === $baseSku;
            })
            ->first();

        $fulfillmentFee = $fbaReport ? floatval($fbaReport->fulfillment_fee ?? 0) : 0;
        $fbaFeeManualValue = floatval($fbaFeeManual);
        $sendCostValue = floatval($sendCost);
        $inChargesValue = floatval($inCharges);

        // Calculate final value
        $finalCalculation = 0;

        // If fulfillment fee exists and is greater than 0, use fulfillment_fee + send_cost + in_charges
        if ($fulfillmentFee > 0) {
            $finalCalculation = round($fulfillmentFee + $sendCostValue + $inChargesValue, 2);
        } else {
            // Otherwise, use manual data calculation: fba_fee_manual + send_cost + in_charges
            $finalCalculation = round($fbaFeeManualValue + $sendCostValue + $inChargesValue, 2);
        }

        return $finalCalculation;
    }
}
