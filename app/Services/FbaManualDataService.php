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
     * Export FBA data to CSV with selected columns
     */
    public function exportToCSV($selectedColumns = [])
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

        // Column mapping: field => [header_name, data_extractor]
        $columnMap = [
            'Parent' => ['Parent', function($product) { return $product ? ($product->parent ?? '') : ''; }],
            'SKU' => ['Child SKU', function($product, $sku) { return $sku; }],
            'FBA_SKU' => ['FBA SKU', function($product, $sku, $fba) { return $fba->seller_sku ?? ''; }],
            'FBA_Quantity' => ['FBA INV', function($product, $sku, $fba) { return $fba->quantity_available ?? 0; }],
            'l60_units' => ['L60 Units', function($product, $sku, $fba, $monthlySales) { return $monthlySales ? ($monthlySales->l60_units ?? 0) : 0; }],
            'l30_units' => ['L30 Units', function($product, $sku, $fba, $monthlySales) { return $monthlySales ? ($monthlySales->l30_units ?? 0) : 0; }],
            'FBA_Dil' => ['FBA Dil', function($product, $sku, $fba, $monthlySales) { 
                $fbaDil = ($monthlySales ? floatval($monthlySales->l30_units ?? 0) : 0) / ($fba->quantity_available ?: 1) * 100;
                return round($fbaDil, 2);
            }],
            'FBA_Price' => ['FBA Price', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo) { 
                $PRICE = $fbaPriceInfo ? floatval($fbaPriceInfo->price ?? 0) : 0;
                return round($PRICE, 2);
            }],
            'GPFT%' => ['GPFT%', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual, $LP, $FBA_SHIP) {
                $PRICE = $fbaPriceInfo ? floatval($fbaPriceInfo->price ?? 0) : 0;
                $gpft = $PRICE > 0 ? (($PRICE * 0.86) - $LP - $FBA_SHIP) / $PRICE * 100 : 0;
                return round($gpft, 2);
            }],
            'GROI%' => ['GROI%', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual, $LP, $FBA_SHIP) {
                $PRICE = $fbaPriceInfo ? floatval($fbaPriceInfo->price ?? 0) : 0;
                $groi = $LP > 0 ? (($PRICE * 0.86) - $LP - $FBA_SHIP) / $LP * 100 : 0;
                return round($groi, 2);
            }],
            'TCOS_Percentage' => ['TACOS', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual) {
                return $manual && isset($manual->data['tcos_percentage']) ? round($manual->data['tcos_percentage'], 0) : 0;
            }],
            'TPFT' => ['PRFT%', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual, $LP, $FBA_SHIP) {
                $PRICE = $fbaPriceInfo ? floatval($fbaPriceInfo->price ?? 0) : 0;
                $tpft = round((($PRICE * 0.66) - $LP - $FBA_SHIP), 2);
                return $tpft;
            }],
            'ROI' => ['ROI%', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual, $LP, $FBA_SHIP) {
                $PRICE = $fbaPriceInfo ? floatval($fbaPriceInfo->price ?? 0) : 0;
                $roi = ($LP > 0) ? (($PRICE * 0.66) - $LP - $FBA_SHIP) / $LP * 100 : 0;
                return round($roi, 2);
            }],
            'S_Price' => ['S Price', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual) {
                $S_PRICE = $manual ? floatval($manual->data['s_price'] ?? 0) : 0;
                return round($S_PRICE, 2);
            }],
            'SPFT' => ['SPft%', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual, $LP, $FBA_SHIP) {
                $S_PRICE = $manual ? floatval($manual->data['s_price'] ?? 0) : 0;
                $spft = ($S_PRICE > 0) ? (($S_PRICE * 0.66) - $LP - $FBA_SHIP) / $S_PRICE * 100 : 0;
                return round($spft, 2);
            }],
            'SROI%' => ['SROI%', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual, $LP, $FBA_SHIP) {
                $S_PRICE = $manual ? floatval($manual->data['s_price'] ?? 0) : 0;
                $sroi = ($LP > 0) ? (($S_PRICE * 0.66) - $LP - $FBA_SHIP) / $LP * 100 : 0;
                return round($sroi, 2);
            }],
            'SGPFT%' => ['SGPFT%', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual, $LP, $FBA_SHIP) {
                $S_PRICE = $manual ? floatval($manual->data['s_price'] ?? 0) : 0;
                $sgpft = $S_PRICE > 0 ? (($S_PRICE * 0.86) - $LP - $FBA_SHIP) / $S_PRICE * 100 : 0;
                return round($sgpft, 2);
            }],
            'LP' => ['LP', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual) {
                $LP = $product ? floatval($product->lp ?? 0) : 0;
                return round($LP, 2);
            }],
            'FBA_Ship_Calculation' => ['FBA Ship', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual) {
                $FBA_SHIP = $this->calculateFbaShipCalculation(
                    $fba->seller_sku,
                    $manual ? ($manual->data['fba_fee_manual'] ?? 0) : 0,
                    $manual ? ($manual->data['send_cost'] ?? 0) : 0,
                    $manual ? ($manual->data['in_charges'] ?? 0) : 0
                );
                return round($FBA_SHIP, 2);
            }],
            'FBA_CVR' => ['CVR', function($product, $sku, $fba, $monthlySales, $fbaReportsInfo) {
                $cvr = ($monthlySales ? floatval($monthlySales->l30_units ?? 0) : 0) / ($fbaReportsInfo ? floatval($fbaReportsInfo->current_month_views ?: 1) : 1) * 100;
                return round($cvr, 2);
            }],
            'Current_Month_Views' => ['Views', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual, $LP, $FBA_SHIP, $fbaReportsInfo) {
                return $fbaReportsInfo ? ($fbaReportsInfo->current_month_views ?? 0) : 0;
            }],
            'Inv_age' => ['Inv age', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual) {
                return $manual ? ($manual->data['inv_age'] ?? '') : '';
            }],
            'lmp_1' => ['LMP', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual) {
                return $manual ? ($manual->data['lmp_1'] ?? '') : '';
            }],
            'Fulfillment_Fee' => ['FBA Fee', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual, $LP, $FBA_SHIP, $fbaReportsInfo) {
                return $fbaReportsInfo ? round($fbaReportsInfo->fulfillment_fee ?? 0, 2) : 0;
            }],
            'FBA_Fee_Manual' => ['FBA Fee Manual', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual) {
                return $manual ? ($manual->data['fba_fee_manual'] ?? 0) : 0;
            }],
            'Send_Cost' => ['Send Cost', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual) {
                return $manual ? ($manual->data['send_cost'] ?? 0) : 0;
            }],
            'Commission_Percentage' => ['Commission Percentage', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual) {
                return $manual ? ($manual->data['commission_percentage'] ?? 0) : 0;
            }],
            'Ratings' => ['Ratings', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual) {
                return $manual ? ($manual->data['ratings'] ?? 0) : 0;
            }],
            'Shipment_Track_Status' => ['Shipment Track Status', function($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual) {
                return $manual ? ($manual->data['shipment_track_status'] ?? '') : '';
            }],
        ];

        // If no columns selected, export all
        if (empty($selectedColumns)) {
            $selectedColumns = array_keys($columnMap);
        }

        // Filter column map to only selected columns
        $selectedColumnMap = array_intersect_key($columnMap, array_flip($selectedColumns));

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="fba_complete_data_' . date('Y-m-d_H-i-s') . '.csv"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ];

        $callback = function () use ($fbaData, $productData, $fbaPriceData, $fbaReportsData, $fbaMonthlySales, $fbaManualData, $fbaDispatchDates, $selectedColumnMap) {
            $file = fopen('php://output', 'w');

            // Write headers (only selected columns)
            $headers = array_column($selectedColumnMap, 0);
            fputcsv($file, $headers);

            // Write data
            foreach ($fbaData as $sku => $fba) {
                try {
                    $product = $productData->get($sku);
                    $fbaPriceInfo = $fbaPriceData->get($sku);
                    $fbaReportsInfo = $fbaReportsData->get($sku);
                    $monthlySales = $fbaMonthlySales->get($sku);
                    
                    // Try to find manual data by both SKU and FBA SKU
                    $manual = $fbaManualData->get($sku) ?? $fbaManualData->get(strtoupper(trim($fba->seller_sku)));
                    $dispatchDate = $fbaDispatchDates->get($sku);

                    $PRICE = $fbaPriceInfo ? floatval($fbaPriceInfo->price ?? 0) : 0;
                    $LP = $product ? floatval($product->lp ?? 0) : 0;
                    $FBA_SHIP = $this->calculateFbaShipCalculation(
                        $fba->seller_sku,
                        $manual ? ($manual->data['fba_fee_manual'] ?? 0) : 0,
                        $manual ? ($manual->data['send_cost'] ?? 0) : 0,
                        $manual ? ($manual->data['in_charges'] ?? 0) : 0
                    );

                    // Build row data using selected columns
                    $rowData = [];
                    foreach ($selectedColumnMap as $extractor) {
                        $rowData[] = $extractor[1]($product, $sku, $fba, $monthlySales, $fbaPriceInfo, $manual, $LP, $FBA_SHIP, $fbaReportsInfo);
                    }

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

                if (strtoupper($sku) === 'ALL') {
                    // Apply to all existing FbaManualData
                    $allManuals = FbaManualData::all();
                    $updateData = [
                        'dimensions' => $this->cleanText($row[1] ?? '') . 'x' . $this->cleanText($row[2] ?? '') . 'x' . $this->cleanText($row[3] ?? ''), // Combine LxWxH for backward compatibility
                        'length' => $this->cleanText($row[1] ?? ''),
                        'width' => $this->cleanText($row[2] ?? ''),
                        'height' => $this->cleanText($row[3] ?? ''),
                        'weight' => $this->cleanText($row[4] ?? ''),
                        'quantity_in_each_box' => $this->cleanText($row[5] ?? ''),
                        'total_quantity_sent' => $this->cleanText($row[6] ?? ''),
                        'total_send_cost' => $this->cleanText($row[7] ?? ''),
                        'inbound_quantity' => $this->cleanText($row[8] ?? ''),
                        'send_cost' => $this->cleanText($row[9] ?? ''),
                        'commission_percentage' => $this->cleanText($row[10] ?? '')
                    ];
                    foreach ($allManuals as $manual) {
                        $existingData = $manual->data ?? [];
                        $manual->data = array_merge($existingData, $updateData);
                        $manual->save();
                    }
                    $imported += $allManuals->count();
                    continue;
                }

                // Try to find by exact SKU or by FBA SKU pattern (more precise)
                $upperSku = strtoupper($sku);
                $manual = FbaManualData::where('sku', $upperSku)
                    ->orWhere('sku', $upperSku . ' FBA')
                    ->orWhere('sku', $upperSku . 'FBA')
                    ->first();

                if (!$manual) {
                    $manual = new FbaManualData();
                    $manual->sku = strtoupper($sku);
                }
                
                $existingData = $manual->data ?? [];
                
                // Only update non-empty values to prevent data loss
                $updateData = [];
                // Combine Length, Width, Height into dimensions
                $length = $this->cleanText($row[1] ?? '');
                $width = $this->cleanText($row[2] ?? '');
                $height = $this->cleanText($row[3] ?? '');
                if (!empty($length) || !empty($width) || !empty($height)) {
                    $updateData['dimensions'] = $length . 'x' . $width . 'x' . $height;
                    $updateData['length'] = $length;
                    $updateData['width'] = $width;
                    $updateData['height'] = $height;
                }
                if (!empty($row[4])) $updateData['weight'] = $this->cleanText($row[4]);
                if (!empty($row[5])) $updateData['quantity_in_each_box'] = $this->cleanText($row[5]);
                if (!empty($row[6])) $updateData['total_quantity_sent'] = $this->cleanText($row[6]);
                if (!empty($row[7])) $updateData['total_send_cost'] = $this->cleanText($row[7]);
                if (!empty($row[8])) $updateData['inbound_quantity'] = $this->cleanText($row[8]);
                if (!empty($row[9])) $updateData['send_cost'] = $this->cleanText($row[9]);
                if (!empty($row[10])) $updateData['commission_percentage'] = $this->cleanText($row[10]);
                
                // ✅ Validate s_price if provided in CSV (column 9)
                if (isset($row[9]) && !empty($row[9])) {
                    $sPrice = floatval($this->cleanText($row[9]));
                    if ($sPrice > 0) {
                        $updateData['s_price'] = $sPrice;
                    } else {
                        Log::warning("Invalid s_price rejected for SKU: {$sku}", ['s_price' => $row[9]]);
                    }
                }
                
                // ✅ Validate ratings if provided in CSV (column 10)
                if (isset($row[10]) && !empty($row[10])) {
                    $ratings = floatval($this->cleanText($row[10]));
                    if ($ratings >= 0 && $ratings <= 5) {
                        $updateData['ratings'] = $ratings;
                    } else {
                        Log::warning("Invalid ratings rejected for SKU: {$sku}", ['ratings' => $row[10]]);
                    }
                }
                
                // ✅ Validate shipment_track_status if provided in CSV (column 13)
                if (isset($row[13]) && !empty($row[13])) {
                    $updateData['shipment_track_status'] = $this->cleanText($row[13]);
                }
                
                $manual->data = array_merge($existingData, $updateData);
                
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
            fputcsv($file, ['SKU', 'Length', 'Width', 'Height', 'Weight', 'Qty in each box', 'Total qty Sent', 'Total Send Cost', 'Inbound qty', 'Send cost', 'Commission Percentage', 'S Price', 'Ratings', 'Shipment Track Status']);
            fputcsv($file, ['SAMPLE-SKU-001', '10', '8', '6', '2.5', '10', '100', '500', '20', '50', '10', '29.99', '4.5', 'Shipped']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Calculate FBA Ship Calculation (Direct calculation only - No database save)
     * 
     * Logic:
     * - If fulfillment_fee exists and > 0 in FbaReportsMaster, use fulfillment_fee + send_cost
     * - If fulfillment_fee is 0 or SKU doesn't exist, use manual data: fba_fee_manual + send_cost
     *
     * @param string $sku The FBA SKU (can be with or without 'FBA' suffix)
     * @param float|string $fbaFeeManual Manual FBA fee value
     * @param float|string $sendCost Send cost value from manual data
     * @return float Calculated FBA Ship Calculation value
     */
    public function calculateFbaShipCalculation($sku, $fbaFeeManual = 0, $sendCost = 0)
    {
        // Normalize SKU by removing FBA suffix
        $baseSku = preg_replace('/\s*FBA\s*/i', '', $sku);
        $baseSku = strtoupper(trim($baseSku));

        // Get Fulfillment Fee from FbaReportsMaster - Optimized query
        $fbaReport = \App\Models\FbaReportsMaster::where(function($query) use ($baseSku) {
                // Try exact match with FBA variations
                $query->where('seller_sku', $baseSku . ' FBA')
                      ->orWhere('seller_sku', $baseSku . 'FBA')
                      ->orWhere('seller_sku', $baseSku . ' fba')
                      ->orWhere('seller_sku', $baseSku);
            })
            ->first();

        $fulfillmentFee = $fbaReport ? floatval($fbaReport->fulfillment_fee ?? 0) : 0;
        $fbaFeeManualValue = floatval($fbaFeeManual);
        $sendCostValue = floatval($sendCost);

        // Calculate final value
        $finalCalculation = 0;

        // If fulfillment fee exists and is greater than 0, use fulfillment_fee + send_cost
        if ($fulfillmentFee > 0) {
            $finalCalculation = round($fulfillmentFee + $sendCostValue, 2);
        } else {
            // Otherwise, use manual data calculation: fba_fee_manual + send_cost
            $finalCalculation = round($fbaFeeManualValue + $sendCostValue, 2);
        }

        return $finalCalculation;
    }
}
