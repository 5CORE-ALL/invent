<?php

namespace App\Services;

use App\Models\FbaManualData;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class FbaManualDataService
{
    /**
     * Export FBA manual data to CSV
     */
    public function exportToCSV()
    {
        $data = FbaManualData::all();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="fba_manual_data_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, ['SKU', 'Dimensions', 'Weight', 'Qty in each box', 'Total qty Sent', 'Total Send Cost', 'Inbound qty', 'Send cost', 'IN Charges']);

            foreach ($data as $row) {
                $manual = $row->data ?? [];
                fputcsv($file, [
                    $row->sku,
                    $manual['dimensions'] ?? '',
                    $manual['weight'] ?? '',
                    $manual['quantity_in_each_box'] ?? '',
                    $manual['total_quantity_sent'] ?? '',
                    $manual['total_send_cost'] ?? '',
                    $manual['inbound_quantity'] ?? '',
                    $manual['send_cost'] ?? '',
                    $manual['in_charges'] ?? ''
                ]);
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
     * Calculate FBA Ship Calculation: Fulfillment Fee + Send Cost + IN Charges
     *
     * @param string $sku The FBA SKU (can be with or without 'FBA' suffix)
     * @param float|string $sendCost Send cost value
     * @param float|string $inCharges IN charges value
     * @return float Calculated FBA Ship Calculation value
     */
    public function calculateFbaShipCalculation($sku, $sendCost = 0, $inCharges = 0)
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
        $sendCost = floatval($sendCost);
        $inCharges = floatval($inCharges);

        return round($fulfillmentFee + $sendCost + $inCharges, 2);
    }
}