<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;



use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\TechnicalSpecificationsController;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ListingEbayController extends Controller
{
    use HandlesListingPublishActions;

    protected function listingPublishChannel(): string
    {
        return 'ebay';
    }

    public function listingEbay(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('ebay_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingEbay', [
            'mode' => $mode,
            'demo' => $demo,
            'ebayPercentage' => $percentage
        ]);
    }

    public function getViewListingEbayData(Request $request)
    {
        return response()->json([
            'status' => 200,
            'data' => AutomatedListingPage::rows('ebay'),
        ]);
    }

    /**
     * GET /listing_ebay/field-counts
     * Per SKU, from the data in our app (Technical Specifications and the eBay category of Category Master):
     *   m = [fields with data, mandatory fields]  (the eBay required fields of the SKU's category)
     *   a = [fields with data, all fields]        (required fields, every field saved in the category, UPC)
     * Fields crossed out for a category on Technical Specifications are left out of both counts.
     */
    public function getFieldCounts(Request $request)
    {
        try {
            @set_time_limit(300);
            @ini_set('memory_limit', '512M');

            $json = app(TechnicalSpecificationsController::class)->getData($request)->getData(true);
            if (($json['status'] ?? 500) !== 200) {
                return response()->json(['status' => 500, 'message' => (string) ($json['error'] ?? $json['message'] ?? 'Could not load the fields.')], 500);
            }

            $hidden = [];
            foreach ((array) ($json['hidden_fields'] ?? []) as $scope => $keys) {
                $hidden[(string) $scope] = array_flip(array_map('strval', (array) $keys));
            }
            $sources = [];
            foreach ((array) ($json['column_sources'] ?? []) as $map) {
                $column = $this->specKey((string) ($map['column'] ?? ''));
                if ($column !== '') {
                    $sources[$column] = (string) ($map['source'] ?? '');
                }
            }

            $out = [];
            foreach ((array) ($json['data'] ?? []) as $row) {
                $sku = trim((string) ($row['SKU'] ?? ''));
                if ($sku === '' || stripos($sku, 'PARENT ') === 0) {
                    continue;
                }
                $skipped = $hidden[(string) ($row['hide_scope'] ?? '')] ?? [];
                $mandatory = $this->countFields((array) ($row['ebay_required_only'] ?? []), $row, $skipped, $sources);
                $all = $this->countFields((array) ($row['ebay_required'] ?? []), $row, $skipped, $sources);
                $out[strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)))] = ['m' => $mandatory, 'a' => $all];
            }

            return response()->json(['status' => 200, 'data' => $out]);
        } catch (\Throwable $e) {
            Log::error('ListingEbay: field counts failed', ['error' => $e->getMessage()]);

            return response()->json(['status' => 500, 'message' => 'Could not count the fields: '.$e->getMessage()], 500);
        }
    }

    /**
     * @param  array<int, mixed>  $names
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $skipped
     * @param  array<string, string>  $sources
     * @return array{0: int, 1: int}  [with data, total]
     */
    private function countFields(array $names, array $row, array $skipped, array $sources): array
    {
        $seen = [];
        $have = 0;
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '' || strlen($name) > 120 || preg_match('/[{};]|text-decoration/', $name)) {
                continue;
            }
            $identity = $this->specIdentity($name);
            if (isset($seen[$identity]) || isset($skipped[$identity])) {
                continue;
            }
            $seen[$identity] = true;
            if ($this->fieldHasData($row, $name, $sources)) {
                $have++;
            }
        }

        return [$have, count($seen)];
    }

    /**
     * Same rules as the Technical Specifications grid: fixed values, MPN and Model = SKU, UPC from CP Master/Amazon,
     * columns that follow a source column, then what is saved for the SKU.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $sources
     */
    private function fieldHasData(array $row, string $name, array $sources): bool
    {
        $fixed = $this->fixedFieldValue($row, $name);
        if ($fixed !== null) {
            return $fixed !== '';
        }
        $source = $sources[$this->specKey($name)] ?? '';
        if ($source !== '') {
            $own = $this->fixedFieldValue($row, $source);

            return trim((string) ($own ?? $this->savedFieldValue($row, $source))) !== '';
        }
        if ($this->savedFieldValue($row, $name) !== '') {
            return true;
        }
        $values = (array) ($row['ebay_values'] ?? []);

        return trim((string) ($values[$name] ?? '')) !== '';
    }

    private function fixedFieldValue(array $row, string $name): ?string
    {
        $key = $this->specKey($name);
        $fixed = ['brand' => '5 Core', 'manufacturer' => '5 Core', 'condition' => 'New', 'countryoforigin' => 'China'];
        if (isset($fixed[$key])) {
            return $fixed[$key];
        }
        if ($key === 'mpn' || $key === 'model') {
            $sku = trim((string) ($row['SKU'] ?? ''));

            return $sku === '' || stripos($sku, 'PARENT ') === 0 ? null : $sku;
        }
        if ($key === 'upc') {
            $upc = trim((string) ($row['upc'] ?? ''));

            return $upc !== '' ? $upc : null;
        }

        return null;
    }

    private function savedFieldValue(array $row, string $name): string
    {
        $want = $this->specIdentity($name);
        foreach ((array) ($row['description_v2_specifications'] ?? []) as $spec) {
            if (is_array($spec) && $this->specIdentity((string) ($spec['key'] ?? '')) === $want && trim((string) ($spec['value'] ?? '')) !== '') {
                return trim((string) $spec['value']);
            }
        }

        return '';
    }

    private function specKey(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $name) ?? $name);
    }

    private function specIdentity(string $name): string
    {
        $key = $this->specKey(trim($name));
        if ($key === '') {
            $key = strtolower(trim($name));
        }
        $canon = [
            'maxpower' => 'maxpower', 'pmpo' => 'maxpower', 'pmpopower' => 'maxpower', 'peakpower' => 'maxpower', 'powercapacity' => 'maxpower',
            'rmspower' => 'rmspower', 'rms' => 'rmspower',
        ];

        return $canon[$key] ?? $key;
    }

    public function updateStatus(Request $request)
    {
        if ($response = $this->listingPublishResponse($request)) {
            return $response;
        }

        $validated = $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'nullable|string',
            'listed' => 'nullable|string',
            'buyer_link' => 'nullable|string',
            'seller_link' => 'nullable|string',
        ]);

        $sku = $validated['sku'];
        $ebayData = EbayDataView::where('sku', $sku)->first();

        $existing = $ebayData ? $ebayData->value : [];

        // Handle nr_req - save as NRL field in ebay_data_view
        if ($request->has('nr_req')) {
            // Map: 'NR' -> 'NRL', 'REQ' -> 'REQ'
            $existing['NRL'] = ($validated['nr_req'] === 'NR') ? 'NRL' : 'REQ';
        }

        // Handle listed field - save as Listed (capitalized) to match the JSON structure
        if ($request->has('listed')) {
            // Save to both 'Listed' (for boolean conversion) and 'listed' (for string)
            $existing['Listed'] = ($validated['listed'] === 'Listed') ? true : false;
            $existing['listed'] = $validated['listed'];
        }

        // Only update other fields that are present in the request
        $fields = ['buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field];
            }
        }

        EbayDataView::updateOrCreate(
            ['sku' => $validated['sku']],
            ['value' => $existing]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Status updated successfully'
        ]);
    }

    public function getNrReqCount()
    {
        return ChannelListingRegistry::nrReqCountArray('ebay');
    }

    public function import(Request $request)
    {
        try {
            Log::info('=== Ebay CSV Import Started ===');
            
            $request->validate([
                'file' => 'required|mimes:csv,txt',
            ]);

            $file = $request->file('file');
        
            $fileContent = file($file);
        
            
            // Detect delimiter (comma or tab)
            $firstLine = $fileContent[0];
        
            
            $delimiter = (strpos($firstLine, "\t") !== false) ? "\t" : ",";
         
            
            // Parse CSV with detected delimiter
            $rows = array_map(function($line) use ($delimiter) {
                return str_getcsv($line, $delimiter);
            }, $fileContent);
            
            // Process header - remove BOM if present
            $header = array_map(function ($h) {
                return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)); // remove BOM if present
            }, $rows[0]);
            
        

            unset($rows[0]);

            $allowedHeaders = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];
            foreach ($header as $h) {
                if (!in_array($h, $allowedHeaders)) {
                
                    return response()->json([
                        'error' => "Invalid header '$h'. Allowed headers: " . implode(', ', $allowedHeaders)
                    ], 422);
                }
            }

            $processedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            foreach ($rows as $rowIndex => $row) {
                if (count($row) < 1) {
                    Log::info("Row $rowIndex: Skipped (empty row)");
                    $skippedCount++;
                    continue; // skip empty
                }

                Log::info("Row $rowIndex data: " . json_encode($row));

                // Pad row with empty strings if it has fewer columns than header
                $headerCount = count($header);
                $rowCount = count($row);
                if ($rowCount < $headerCount) {
                    $row = array_pad($row, $headerCount, '');
                   
                }
                
                // Trim row if it has more columns than header
                if ($rowCount > $headerCount) {
                    $row = array_slice($row, 0, $headerCount);
                 
                }

                $rowData = array_combine($header, $row);
              
                
                $sku = trim($rowData['sku'] ?? '');

                if (!$sku) {
                    $skippedCount++;
                    continue;
                }

                try {
                    // Only import SKUs that exist in product_masters
                    if (!ProductMaster::where('sku', $sku)->exists()) {
                     
                        $skippedCount++;
                        continue;
                    }

                    $ebayData = EbayDataView::where('sku', $sku)->first();
                    $existing = $ebayData ? $ebayData->value : [];

                    // Handle nr_req - save as NRL field
                    if (array_key_exists('nr_req', $rowData) && $rowData['nr_req'] !== '') {
                        $existing['NRL'] = (trim($rowData['nr_req']) === 'NRL') ? 'NRL' : 'REQ';
                    }

                    // Handle listed field
                    if (array_key_exists('listed', $rowData) && $rowData['listed'] !== '') {
                        $listedVal = trim($rowData['listed']);
                        $existing['Listed'] = ($listedVal === 'Listed') ? true : false;
                        $existing['listed'] = $listedVal;
                    }

                    // Handle other fields
                    $otherFields = ['buyer_link', 'seller_link'];
                    foreach ($otherFields as $field) {
                        if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                            $existing[$field] = trim($rowData[$field]);
                        }
                    }

                    EbayDataView::updateOrCreate(
                        ['sku' => $sku],
                        ['value' => $existing]
                    );
                    
               
                    $processedCount++;
                    
                } catch (\Exception $rowError) {
               
                    $errorCount++;
                }
            }

       

            $message = 'CSV imported successfully';
            if ($errorCount > 0) {
                $message .= " (Processed: $processedCount, Skipped: $skippedCount, Errors: $errorCount)";
            }

            return response()->json([
                'success' => $message,
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Ebay CSV Import Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'error' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }


    public function export(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="listing_status.csv"',
        ];

        $columns = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Write header row
            fputcsv($file, $columns);

            // Fetch all SKUs from product master
            $productMasters = ProductMaster::pluck('sku');

            foreach ($productMasters as $sku) {
                $ebayData = EbayDataView::where('sku', $sku)->first();

                // Read NRL field
                $nrlValue = $ebayData->value['NRL'] ?? null;
                $nr_req = '';
                if ($nrlValue === 'NRL') {
                    $nr_req = 'NRL';
                } else if ($nrlValue === 'REQ') {
                    $nr_req = 'REQ';
                }

                // Read listed field
                $listedValue = $ebayData->value['Listed'] ?? $ebayData->value['listed'] ?? null;
                $listed = '';
                if (is_bool($listedValue)) {
                    $listed = $listedValue ? 'Listed' : 'Pending';
                } else if (is_string($listedValue)) {
                    $listed = $listedValue;
                }

                $row = [
                    'sku'         => $sku,
                    'nr_req'      => $nr_req,
                    'listed'      => $listed,
                    'buyer_link'  => $ebayData->value['buyer_link'] ?? '',
                    'seller_link' => $ebayData->value['seller_link'] ?? '',
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}