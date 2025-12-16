<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TiktokShopListingStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ListingTiktokShopController extends Controller
{
    public function listingTiktokShop(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $percentage = Cache::remember('tiktokshop_marketplace_percentage', now()->addDays(30), function () {
            return 100;
        });

        return view('market-places.listing-market-places.listingTiktokShop', [
            'tiktokShopPercentage' => $percentage,
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getViewListingTiktokShopData(Request $request)
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        
        // Get status data, handling duplicates by taking the most recent non-empty record
        $statusData = TiktokShopListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->filter(function ($record) {
                // Filter out records with empty or null values
                $value = is_array($record->value) ? $record->value : (json_decode($record->value, true) ?? []);
                return !empty($value) && (isset($value['nr_req']) || isset($value['listed']) || isset($value['buyer_link']) || isset($value['seller_link']));
            })
            ->keyBy('sku');

        $processedData = $productMasters->map(function ($item) use ($shopifyData, $statusData) {
            $childSku = $item->sku;
            $item->INV = $shopifyData[$childSku]->inv ?? 0;
            $item->L30 = $shopifyData[$childSku]->quantity ?? 0;
            $item->nr_req = null;
            $item->listed = null;
            $item->buyer_link = null;
            $item->seller_link = null;
            if (isset($statusData[$childSku])) {
                $status = $statusData[$childSku]->value;
                if (is_string($status)) {
                    $status = json_decode($status, true) ?? [];
                }
                if (is_array($status) && !empty($status)) {
                    $item->nr_req = $status['nr_req'] ?? null;
                    $item->listed = $status['listed'] ?? null;
                    $item->buyer_link = $status['buyer_link'] ?? null;
                    $item->seller_link = $status['seller_link'] ?? null;
                }
            }
            return $item;
        })->values();

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
            'buyer_link' => 'nullable|string',
            'seller_link' => 'nullable|string',
        ]);

        $sku = trim($validated['sku']);
        
        // Get the most recent non-empty record, or create new
        $status = TiktokShopListingStatus::where('sku', $sku)
            ->orderBy('updated_at', 'desc')
            ->first();

        // If we have a record, use its value, otherwise start fresh
        if ($status) {
            $existing = is_array($status->value) ? $status->value : (json_decode($status->value, true) ?? []);
            
            // If existing is empty array, start fresh
            if (empty($existing)) {
                $existing = [];
            }
        } else {
            $existing = [];
        }

        // Only update the fields that are present in the request and not empty
        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field) && $request->input($field) !== null && $request->input($field) !== '') {
                $existing[$field] = $validated[$field];
            }
        }

        // Clean up: Delete any duplicate records for this SKU before creating/updating
        TiktokShopListingStatus::where('sku', $sku)->delete();

        // Create a single clean record
        TiktokShopListingStatus::create([
            'sku' => $sku,
            'value' => $existing
        ]);

        return response()->json(['status' => 'success']);
    }

    public function getNrReqCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        
        // Get status data, handling duplicates by taking the most recent non-empty record
        $statusData = TiktokShopListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->filter(function ($record) {
                // Filter out records with empty or null values
                $value = is_array($record->value) ? $record->value : (json_decode($record->value, true) ?? []);
                return !empty($value) && (isset($value['nr_req']) || isset($value['listed']) || isset($value['buyer_link']) || isset($value['seller_link']));
            })
            ->keyBy('sku');

        $reqCount = 0;
        $listedCount = 0;
        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = null;
            if (isset($statusData[$sku])) {
                $status = $statusData[$sku]->value;
                if (is_string($status)) {
                    $status = json_decode($status, true);
                }
                if (is_array($status) && empty($status)) {
                    $status = null;
                }
            }

            // NR/REQ logic
            $nrReq = ($status && isset($status['nr_req'])) ? $status['nr_req'] : (floatval($inv) > 0 ? 'REQ' : 'NR');
            if ($nrReq === 'REQ') {
                $reqCount++;
            }

            // Listed/Pending logic
            $listed = ($status && isset($status['listed'])) ? $status['listed'] : (floatval($inv) > 0 ? 'Pending' : 'Listed');
            if ($listed === 'Listed') {
                $listedCount++;
            } elseif ($listed === 'Pending') {
                $pendingCount++;
            }
        }

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

            // Get the most recent non-empty record, or start fresh
            $status = TiktokShopListingStatus::where('sku', $sku)
                ->orderBy('updated_at', 'desc')
                ->first();

            if ($status) {
                $existing = is_array($status->value) ? $status->value : (json_decode($status->value, true) ?? []);
                if (empty($existing)) {
                    $existing = [];
                }
            } else {
                $existing = [];
            }

            $fields = ['listed', 'buyer_link', 'seller_link'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                    $existing[$field] = $rowData[$field];
                }
            }

            // Clean up duplicates before creating/updating
            TiktokShopListingStatus::where('sku', $sku)->delete();

            // Create a single clean record
            TiktokShopListingStatus::create([
                'sku' => $sku,
                'value' => $existing
            ]);
        }

        return response()->json(['success' => 'CSV imported successfully']);
    }


    public function export(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="listing_status.csv"',
        ];

        $columns = ['sku', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Write header row
            fputcsv($file, $columns);

            // Fetch all SKUs from product master
            $productMasters = ProductMaster::pluck('sku');

            foreach ($productMasters as $sku) {
                $status = TiktokShopListingStatus::where('sku', $sku)->first();

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