<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\AmazonDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Log;

class AmazonFbaInvController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;

    }
    public function amazonFbaInv(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // 5. Success Response
        return view('market-places.amazonFbaInv', [
            'mode' => $mode,
            'demo' => $demo
        ]);
    }

    public function getViewAmazonfbaInvData(Request $request)
    {
        // 1. Fetch all ProductMaster rows (base)
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        // 2. Fetch ShopifySku for those SKUs
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch AmazonDataView for all SKUs
        $amazonDataViews = AmazonDataView::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // 3. Fetch API data (Google Sheet)
        $response = $this->apiController->fetchDataFromAmazonFBAGoogleSheet();
        $apiDataArr = ($response->getStatusCode() === 200) ? ($response->getData()->data ?? []) : [];
        // Index API data by SKU (case-insensitive)
        $apiDataBySku = [];
        foreach ($apiDataArr as $item) {
            $sku = isset($item->{'(Child) sku'}) ? strtoupper(trim($item->{'(Child) sku'})) : null;
            if ($sku)
                $apiDataBySku[$sku] = $item;
        }

        // 4. Build final data
        $result = [];
        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;
            $apiItem = $apiDataBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            // Merge API data into base row if exists
            $row = [];
            $row['Parent'] = $parent;
            $row['(Child) sku'] = $pm->sku;
            $row['NRL'] = "REQ";

            // Merge API fields if available
            if ($apiItem) {
                foreach ($apiItem as $k => $v) {
                    $row[$k] = $v;
                }
            }

            // Add Shopify fields if available
            $row['INV'] = $shopify->inv ?? 0;
            $row['L30'] = $shopify->quantity ?? 0;

            // Image path (from Shopify or ProductMaster)
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
            $row['image_path'] = $shopify->image_src ?? ($values['image_path'] ?? null);

            $dataView = $amazonDataViews[$sku] ?? null;
            $value = $dataView ? $dataView->value : [];
            $row['NRL'] = $value['NR'] ?? '';


            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $result,
            'status' => 200,
        ]);
    }

    public function updateAllAmazonfbaSkus(Request $request)
    {
        try {
            // Validate CSRF token
            if (!hash_equals($request->session()->token(), $request->input('_token'))) {
                return response()->json([
                    'message' => 'CSRF token mismatch',
                    'status' => 419
                ], 419);
            }

            // 1. Fetch data from Shopify
            $shopifySkus = ShopifySku::all()->keyBy('sku');

            // 2. Fetch current data from Amazon Google Sheet
            $response = $this->apiController->fetchDataFromAmazonFBAGoogleSheet();

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Failed to fetch data from Google Sheet');
            }

            $sheetData = $response->getData()->data;

            // 3. Prepare updates - only for non-PARENT SKUs
            $updates = [];
            foreach ($sheetData as $item) {
                $childSku = $item->{'(Child) sku'} ?? '';

                // Skip if this is a PARENT SKU or empty SKU
                if (empty($childSku) || stripos($childSku, 'PARENT') !== false) {
                    continue;
                }

                // Check if SKU exists in Shopify
                if ($shopifySkus->has($childSku)) {
                    $inv = $shopifySkus[$childSku]->inv;
                    $l30 = $shopifySkus[$childSku]->quantity;
                } else {
                    // Set to 0 if SKU not found
                    $inv = 0;
                    $l30 = 0;
                }

                $updates[] = [
                    'sku' => $childSku,
                    'INV' => $inv,
                    'L30' => $l30
                ];
            }

            // 4. Send updates to Google Sheet in batches (to avoid timeout)
            $batchSize = 100;
            $totalUpdated = 0;
            $batches = array_chunk($updates, $batchSize);

            foreach ($batches as $batch) {
                $postData = [
                    'action' => 'update_inv_l30',
                    'updates' => $batch
                ];

                $url = 'https://script.google.com/macros/s/AKfycbzWwqRpTmb8eq0Vp05kP63r02smPIWGsTdcNozqIH0kERoLWuhtTcrsSv4KEub8oeoLNw/exec';
                $response = Http::timeout(120)->post($url, $postData);

                if (!$response->successful()) {
                    throw new \Exception('Failed to update batch: ' . $response->body());
                }

                $totalUpdated += count($batch);
            }

            return response()->json([
                'message' => 'Successfully updated ' . $totalUpdated . ' SKUs',
                'status' => 200,
                'total_updated' => $totalUpdated
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating all Amazon SKUs: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating SKUs: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }
}