    /**
     * Upload Temu Ad Data (Truncate then Insert)
     */
    public function uploadTemuAdData(Request $request)
    {
        try {
            $request->validate([
                'ad_data_file' => 'required|file|mimes:xlsx,xls,csv'
            ]);

            $file = $request->file('ad_data_file');
            $spreadsheet = IOFactory::load($file->getPathName());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Get headers from first row
            $headers = $rows[0];
            unset($rows[0]); // Remove header row
            // Skip second row if it's "Total..." row
            if (!empty($rows[1]) && strpos($rows[1][0], 'Total') !== false) {
                unset($rows[1]);
            }

            $imported = 0;

            DB::beginTransaction();
            try {
                // Truncate table before inserting new data
                TemuAdData::truncate();
                
                foreach ($rows as $index => $row) {
                    if (empty(array_filter($row))) {
                        continue; // Skip empty rows
                    }

                    $rowData = array_combine($headers, $row);
                    
                    // Helper function to parse currency values
                    $parseCurrency = function($value) {
                        if (empty($value) || $value === '∞') return null;
                        return floatval(str_replace(['$', ','], '', $value));
                    };
                    
                    // Helper function to parse percentage values
                    $parsePercent = function($value) {
                        if (empty($value) || $value === '∞') return null;
                        return floatval(str_replace('%', '', $value));
                    };

                    $adData = [
                        'goods_name' => $rowData['Goods name'] ?? null,
                        'goods_id' => $rowData['Goods ID'] ?? null,
                        'spend' => $parseCurrency($rowData['Spend'] ?? null),
                        'base_price_sales' => $parseCurrency($rowData['Base price sales'] ?? null),
                        'roas' => floatval($rowData['ROAS'] ?? 0),
                        'acos_ad' => $parsePercent($rowData['ACOS(AD)'] ?? null),
                        'cost_per_transaction' => $parseCurrency($rowData['Cost per transaction'] ?? null),
                        'sub_orders' => !empty($rowData['Sub-Orders']) ? (int)$rowData['Sub-Orders'] : 0,
                        'items' => !empty($rowData['Items']) ? (int)$rowData['Items'] : 0,
                        'net_total_cost' => $parseCurrency($rowData['Net total cost'] ?? null),
                        'net_declared_sales' => $parseCurrency($rowData['Net declared sales'] ?? null),
                        'net_roas' => floatval($rowData['Net advertising return on investment (ROAS)'] ?? 0),
                        'net_acos_ad' => $parsePercent($rowData['Net advertising cost ratio (advertising)'] ?? null),
                        'net_cost_per_transaction' => $parseCurrency($rowData['Net cost per transaction'] ?? null),
                        'net_orders' => !empty($rowData['Net Orders']) ? (int)$rowData['Net Orders'] : 0,
                        'net_number_pieces' => !empty($rowData['Net number of pieces']) ? (int)$rowData['Net number of pieces'] : 0,
                        'impressions' => !empty($rowData['Impressions']) ? (int)str_replace(',', '', $rowData['Impressions']) : 0,
                        'clicks' => !empty($rowData['Clicks']) ? (int)str_replace(',', '', $rowData['Clicks']) : 0,
                        'ctr' => $parsePercent($rowData['CTR'] ?? null),
                        'cvr' => $parsePercent($rowData['Conversion Rate (CVR)'] ?? null),
                        'add_to_cart_number' => !empty($rowData['Add-to-cart number']) ? (int)str_replace(',', '', $rowData['Add-to-cart number']) : 0,
                        'weekly_roas' => floatval($rowData['Weekly ROAS'] ?? 0),
                        'target' => floatval($rowData['Target'] ?? 0),
                    ];

                    TemuAdData::create($adData);
                    $imported++;
                }

                DB::commit();

                return back()->with('success', "Successfully imported $imported ad records! (All previous data was cleared)");
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error uploading Temu ad data: ' . $e->getMessage());
            return back()->with('error', 'Error uploading file: ' . $e->getMessage());
        }
    }

    /**
     * Download Temu Ad Data Sample File
     */
    public function downloadTemuAdDataSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row - matches Temu export format
        $headers = [
            'Goods name',
            'Goods ID',
            'Spend',
            'Base price sales',
            'ROAS',
            'ACOS(AD)',
            'Cost per transaction',
            'Sub-Orders',
            'Items',
            'Net total cost',
            'Net declared sales',
            'Net advertising return on investment (ROAS)',
            'Net advertising cost ratio (advertising)',
            'Net cost per transaction',
            'Net Orders',
            'Net number of pieces',
            'Impressions',
            'Clicks',
            'CTR',
            'Conversion Rate (CVR)',
            'Add-to-cart number',
            'Weekly ROAS',
            'Target'
        ];
        
        $sheet->fromArray($headers, NULL, 'A1');

        // Sample Data
        $sampleData = [
            [
                '5Core 12" Subwoofer 1200W PA DJ',
                '603186094008569',
                '$78.01',
                '$954.55',
                '12.24',
                '8.17%',
                '$3.13',
                '25',
                '25',
                '$74.74',
                '$842.98',
                '11.28',
                '8.86%',
                '$3.40',
                '22',
                '22',
                '48283',
                '1452',
                '3.00%',
                '1.72%',
                '164',
                '0.00',
                '0.00'
            ],
            [
                '5Core VGA to VGA Cable 6Ft',
                '602810552799440',
                '$13.11',
                '$136.76',
                '10.43',
                '9.58%',
                '$0.46',
                '29',
                '35',
                '$13.11',
                '$136.76',
                '10.43',
                '9.58%',
                '$0.46',
                '29',
                '35',
                '7431',
                '211',
                '2.83%',
                '13.74%',
                '72',
                '9.85',
                '10.10'
            ]
        ];

        $sheet->fromArray($sampleData, NULL, 'A2');

        // Set column widths
        foreach (range('A', 'W') as $col) {
            $sheet->getColumnDimension($col)->setWidth(20);
        }

        // Style header row
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCCCCC']]
        ];
        $sheet->getStyle('A1:W1')->applyFromArray($headerStyle);

        // Output Download
        $fileName = 'Temu_Ad_Data_Sample_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
