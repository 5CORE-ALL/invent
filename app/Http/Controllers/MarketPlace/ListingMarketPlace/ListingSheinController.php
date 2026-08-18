<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;



use App\Http\Controllers\Controller;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\SheinListingStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ListingSheinController extends Controller
{
    use HandlesListingPublishActions;

    public function listingShein(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('shein_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingShein', [
            'sheinPercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingSheinData(Request $request)
    {
        return response()->json([
            'status' => 200,
            'data' => AutomatedListingPage::rows('shein'),
        ]);
    }

    public function saveStatus(Request $request)
    {
        if ($response = $this->listingPublishResponse($request)) {
            return $response;
        }

        $validated = $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'nullable|string',
            'listed' => 'nullable|string',
            'buyer_link' => 'nullable|url',
            'seller_link' => 'nullable|url',
        ]);

        $sku = $validated['sku'];
        $status = SheinListingStatus::where('sku', $sku)->first();

        $existing = $status ? $status->value : [];

        // Only update the fields that are present in the request
        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $existing[$field] = $validated[$field];
            }
        }

        SheinListingStatus::updateOrCreate(
            ['sku' => $validated['sku']],
            ['value' => $existing]
        );

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        return ChannelListingRegistry::nrReqCountArray('shein');
    }

    public function import(Request $request)
    {
        try {
            Log::info('=== Shein CSV Import Started ===');
            
            $request->validate([
                'file' => 'required|mimes:csv,txt',
            ]);

            $file = $request->file('file');
            Log::info('File uploaded: ' . $file->getClientOriginalName());
            
            $fileContent = file($file);
            Log::info('Total lines in file: ' . count($fileContent));
            
            // Detect delimiter (comma or tab)
            $firstLine = $fileContent[0];
            Log::info('First line (raw): ' . json_encode($firstLine));
            
            $delimiter = (strpos($firstLine, "\t") !== false) ? "\t" : ",";
            Log::info('Detected delimiter: ' . ($delimiter === "\t" ? 'TAB' : 'COMMA'));
            
            // Parse CSV with detected delimiter
            $rows = array_map(function($line) use ($delimiter) {
                return str_getcsv($line, $delimiter);
            }, $fileContent);
            
            // Process header - remove BOM if present
            $header = array_map(function ($h) {
                return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)); // remove BOM if present
            }, $rows[0]);
            
            Log::info('Headers detected: ' . json_encode($header));

            unset($rows[0]);

            $allowedHeaders = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];
            foreach ($header as $h) {
                if (!in_array($h, $allowedHeaders)) {
                    Log::error("Invalid header found: '$h'");
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

                $rowData = array_combine($header, $row);
                Log::info("Row $rowIndex combined: " . json_encode($rowData));
                
                $sku = trim($rowData['sku'] ?? '');

                if (!$sku) {
                    Log::info("Row $rowIndex: Skipped (no SKU)");
                    $skippedCount++;
                    continue;
                }

                // Only import SKUs that exist in product_masters
                if (!ProductMaster::where('sku', $sku)->exists()) {
                    Log::warning("Row $rowIndex: SKU '$sku' not found in product_masters");
                    $skippedCount++;
                    continue;
                }

                $status = SheinListingStatus::where('sku', $sku)->first();
                $existing = $status ? $status->value : [];

                $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
                foreach ($fields as $field) {
                    if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                        $existing[$field] = $rowData[$field];
                    }
                }

                SheinListingStatus::updateOrCreate(
                    ['sku' => $sku],
                    ['value' => $existing]
                );
                
                Log::info("Row $rowIndex: SKU '$sku' processed successfully");
                $processedCount++;
            }

            Log::info("=== Shein CSV Import Completed ===");
            Log::info("Processed: $processedCount, Skipped: $skippedCount, Errors: $errorCount");

            return response()->json([
                'success' => 'CSV imported successfully',
                'processed' => $processedCount,
                'skipped' => $skippedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Shein CSV Import Error: ' . $e->getMessage());
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
            'Content-Disposition' => 'attachment; filename="shein_listing_status.csv"',
        ];

        $columns = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $skus = ProductMaster::query()->orderBy('sku')->pluck('sku');
            $statusBySku = SheinListingStatus::query()
                ->get(['sku', 'value'])
                ->keyBy(function ($row) {
                    return (string) $row->sku;
                });

            foreach ($skus as $sku) {
                $status = $statusBySku->get((string) $sku);
                $value = is_array($status?->value) ? $status->value : [];

                fputcsv($file, [
                    $sku,
                    $value['nr_req'] ?? '',
                    $value['listed'] ?? '',
                    $value['buyer_link'] ?? '',
                    $value['seller_link'] ?? '',
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}