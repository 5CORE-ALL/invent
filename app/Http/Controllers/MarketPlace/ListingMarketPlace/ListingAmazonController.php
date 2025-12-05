<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDataView;
use App\Models\AmazonListingStatus;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ProductStockMapping;
use Illuminate\Http\Request;
use App\Models\AmazonDatasheet;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;

class ListingAmazonController extends Controller
{
    public function listingAmazon(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingAmazon', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage
        ]);
    }

    public function getViewListingAmazonData(Request $request)
    {
        $productMasters = ProductMaster::whereNull('deleted_at')
            ->select('id', 'sku', 'parent')
            ->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        // Load all data in one go with proper indexing
        $shopifyData = ShopifySku::whereIn('sku', $skus)
            ->select('sku', 'inv', 'quantity')
            ->get()
            ->keyBy('sku');
        
        $statusData = AmazonDataView::whereIn('sku', $skus)
            ->select('sku', 'value')
            ->get()
            ->keyBy('sku');
        
        $listingStatusData = AmazonDatasheet::whereIn('sku', $skus)
            ->select('sku', 'listing_status')
            ->get()
            ->keyBy('sku');

        $processedData = [];
        foreach ($productMasters as $item) {
            $childSku = $item->sku;
            
            // Default values
            $nr_req = 'REQ'; // Default to REQ (shows as RL)
            $listed = null;
            $buyer_link = null;
            $seller_link = null;
            $listing_status = $listingStatusData[$childSku]->listing_status ?? null;
            
            if (isset($statusData[$childSku])) {
                $status = $statusData[$childSku]->value;
                // Read NRL field - "REQ" means RL, "NRL" means NRL
                $nrlValue = $status['NRL'] ?? null;
                if ($nrlValue === 'NRL') {
                    $nr_req = 'NR';
                } else if ($nrlValue === 'REQ') {
                    $nr_req = 'REQ';
                }
                // If NRL field is null or any other value, keep default 'REQ'
                
                $listedValue = $status['Listed'] ?? $status['listed'] ?? null;
                if (is_bool($listedValue)) {
                    $listed = $listedValue ? 'Listed' : 'Pending';
                } else {
                    $listed = $listedValue;
                }
                $buyer_link = $status['buyer_link'] ?? null;
                $seller_link = $status['seller_link'] ?? null;
            }
            
            $row = [
                'id' => $item->id,
                'sku' => $childSku,
                'parent' => $item->parent,
                'INV' => $shopifyData[$childSku]->inv ?? 0,
                'L30' => $shopifyData[$childSku]->quantity ?? 0,
                'nr_req' => $nr_req,
                'listed' => $listed,
                'buyer_link' => $buyer_link,
                'seller_link' => $seller_link,
                'listing_status' => $listing_status
            ];
            
            $processedData[] = $row;
        }

        return response()->json([
            'status' => 200,
            'data' => $processedData
        ]);
    }

    public function saveStatus(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'nullable|string',
            'listed' => 'nullable|string',
            'buyer_link' => 'nullable|url',
            'seller_link' => 'nullable|url',
        ]);

        $sku = $validated['sku'];
        $status = AmazonDataView::where('sku', $sku)->first();

        $existing = $status ? $status->value : [];

        // Handle nr_req - save as NRL field in amazon_data_view
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

        AmazonDataView::updateOrCreate(
            ['sku' => $validated['sku']],
            ['value' => $existing]
        );

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = AmazonDataView::whereIn('sku', $skus)->get()->keyBy('sku');
        $amazonListed = ProductStockMapping::pluck('inventory_amazon_product', 'sku');

        $reqCount = 0;
        $listedCount = 0;
        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $mappingValue = $amazonListed[$sku] ?? null;
            $listed = null;
            if ($mappingValue !== null) {
                $normalized = strtolower(trim($mappingValue));
                $listed = $normalized !== '' && $normalized !== 'not listed' ? 'Listed' : 'Not Listed';
            } else {
                // Check both 'Listed' (boolean) and 'listed' (string) fields
                $listedValue = $status['Listed'] ?? $status['listed'] ?? null;
                if (is_bool($listedValue)) {
                    $listed = $listedValue ? 'Listed' : 'Not Listed';
                } else {
                    $listed = $listedValue;
                }
            }

            // Read NRL field from amazon_data_view - "REQ" means RL, "NRL" means NRL
            $nrlValue = $status['NRL'] ?? 'REQ';
            $nrReq = ($nrlValue === 'NRL') ? 'NR' : 'REQ';
            
            if ($nrReq === 'REQ') {
                $reqCount++;
            }

            if ($listed === 'Listed') {
                $listedCount++;
            }

            // Count as pending if nr_req is not NR AND listed is not Listed
            if ($nrReq !== 'NR' && $listed !== 'Listed') {
                $pendingCount++;
            }
        }

        Log::info("Amazon getNrReqCount: " . json_encode([
            'REQ' => $reqCount,
            'Listed' => $listedCount,
            'Pending' => $pendingCount,
        ]));

        return [
            'REQ' => $reqCount,
            'Listed' => $listedCount,
            'Pending' => $pendingCount,
        ];
    }


    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $rows = array_map('str_getcsv', file($file));
        // $header = array_map('trim', $rows[0]); // first row = header
        $header = array_map(function ($h) {
            return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)); // remove BOM if present
        }, $rows[0]);

        unset($rows[0]);

        $allowedHeaders = ['sku','listed', 'buyer_link', 'seller_link'];
        foreach ($header as $h) {
            if (!in_array($h, $allowedHeaders)) {
                return response()->json([
                    'error' => "Invalid header '$h'. Allowed headers: " . implode(', ', $allowedHeaders)
                ], 422);
            }
        }

        foreach ($rows as $row) {
            if (count($row) < 1) {
                continue; // skip empty
            }

            $rowData = array_combine($header, $row);
            $sku = trim($rowData['sku'] ?? '');

            if (!$sku) {
                continue;
            }

            // Only import SKUs that exist in product_masters
            if (!ProductMaster::where('sku', $sku)->exists()) {
                continue;
            }

            $status = AmazonDataView::where('sku', $sku)->first();
            $existing = $status ? $status->value : [];

            $fields = ['listed', 'buyer_link', 'seller_link'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                    $existing[$field] = $rowData[$field];
                }
            }

            AmazonDataView::updateOrCreate(
                ['sku' => $sku],
                ['value' => $existing]
            );
        }

        return response()->json(['success' => 'CSV imported successfully']);
    }


    public function export(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="amazon_listing_status.csv"',
        ];

        $columns = ['sku', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Write header row
            fputcsv($file, $columns);

            // Fetch all SKUs from product master
            $productMasters = ProductMaster::pluck('sku');

            foreach ($productMasters as $sku) {
                $status = AmazonDataView::where('sku', $sku)->first();

                $row = [
                    'sku'         => $sku,
                    'listed'      => $status->value['listed'] ?? '',
                    'buyer_link'  => $status->value['buyer_link'] ?? '',
                    'seller_link' => $status->value['seller_link'] ?? '',
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }


}