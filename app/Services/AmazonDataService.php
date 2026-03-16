<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AmazonDataService
{
    /**
     * Export Amazon pricing CVR data to CSV
     */
    public function exportPricingCVRToCSV(Request $request)
    {
        try {
            // Get the controller instance to access the data method
            $controller = app(\App\Http\Controllers\MarketPlace\OverallAmazonController::class);
            $response = $controller->getViewAmazonData($request);
            $data = json_decode($response->getContent(), true);
            $amazonData = collect($data['data'] ?? []);

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="Amazon_Pricing_CVR_Export_' . date('Y-m-d_H-i-s') . '.csv"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ];

            $callback = function () use ($amazonData) {
                $file = fopen('php://output', 'w');

                // Header Row
                $headerRow = [
                    'Parent', '(Child) SKU', 'INV', 'L30', 'Price', 'Price LMPA',
                    'A L30', 'Sess30', 'CVR L30', 'NRL', 'NRA', 'SPRICE',
                    'SGPFT%', 'SROI%', 'Listed', 'Live', 'A+',
                    'PFT%', 'ROI%', 'Total Profit', 'Total Sales L30', 'Total COGS'
                ];
                fputcsv($file, $headerRow);

                // Data Rows
                foreach ($amazonData as $item) {
                    $aL30 = $item['A_L30'] ?? 0;
                    $sess30 = $item['Sess30'] ?? 0;
                    $cvr = $sess30 > 0 ? ($aL30 / $sess30) * 100 : 0;

                    $rowData = [
                        $item['Parent'] ?? '',
                        $item['(Child) sku'] ?? '',
                        $item['INV'] ?? 0,
                        $item['L30'] ?? 0,
                        $item['price'] ?? 0,
                        $item['price_lmpa'] ?? 0,
                        $aL30,
                        $sess30,
                        round($cvr, 2),
                        $item['NRL'] ?? '',
                        $item['NRA'] ?? '',
                        $item['SPRICE'] ?? '',
                        $item['Spft%'] ?? '',
                        $item['SROI'] ?? '',
                        $item['Listed'] ? 'TRUE' : 'FALSE',
                        $item['Live'] ? 'TRUE' : 'FALSE',
                        $item['APlus'] ? 'TRUE' : 'FALSE',
                        $item['PFT_percentage'] ?? 0,
                        $item['ROI_percentage'] ?? 0,
                        $item['Total_pft'] ?? 0,
                        $item['T_Sale_l30'] ?? 0,
                        $item['T_COGS'] ?? 0
                    ];

                    fputcsv($file, $rowData);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('Error exporting Amazon pricing CVR data to CSV: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Export Amazon SPRICE data for upload (only non-pushed items)
     * Only includes rows where SPRICE doesn't match current price
     */
    public function exportSpriceForUpload(Request $request)
    {
        try {
            // Get the controller instance to access the data method
            $controller = app(\App\Http\Controllers\MarketPlace\OverallAmazonController::class);
            $response = $controller->getViewAmazonData($request);
            $data = json_decode($response->getContent(), true);
            $amazonData = collect($data['data'] ?? []);

            // Filter only items where SPRICE doesn't match current price (not pushed)
            $filteredData = $amazonData->filter(function($item) {
                $price = floatval($item['price'] ?? 0);
                $sprice = floatval($item['SPRICE'] ?? 0);
                
                // Include only if:
                // 1. SPRICE exists and is > 0
                // 2. SPRICE doesn't match current price
                return $sprice > 0 && abs($price - $sprice) > 0.01;
            });

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="Amazon_SPRICE_Upload_' . date('Y-m-d_H-i-s') . '.csv"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ];

            $callback = function () use ($filteredData) {
                $file = fopen('php://output', 'w');

                // Header Row
                $headerRow = ['SKU', 'Current Price', 'SPRICE', 'SGPFT%', 'SPFT%', 'SROI%'];
                fputcsv($file, $headerRow);

                // Data Rows
                foreach ($filteredData as $item) {
                    $rowData = [
                        $item['(Child) sku'] ?? '',
                        $item['price'] ?? 0,
                        $item['SPRICE'] ?? 0,
                        $item['SGPFT'] ?? '',
                        $item['Spft%'] ?? '',
                        $item['SROI'] ?? ''
                    ];

                    fputcsv($file, $rowData);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('Error exporting Amazon SPRICE upload data to CSV: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Download sample CSV template for ratings import
     */
    public function downloadRatingsSampleTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="amazon_ratings_sample.csv"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['SKU', 'rating']);
            fputcsv($file, ['SAMPLE-SKU-001', '5']);
            fputcsv($file, ['SAMPLE-SKU-002', '4']);
            fputcsv($file, ['SAMPLE-SKU-003', '3']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}