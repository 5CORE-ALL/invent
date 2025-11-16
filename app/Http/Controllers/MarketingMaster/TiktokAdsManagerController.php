<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use App\Models\AmazonDataView;
use App\Models\TiktokGmvAd;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TiktokAdsManagerController extends Controller
{
    public function index()
    {
        return view('marketing-masters.tiktok_ads_manager.index');
    }

    public function getTiktokAdsData()
    {
        $data = [
            ['id' => 1, 'campaign_name' => 'Campaign 1', 'status' => 'Active', 'budget' => 100],
            ['id' => 2, 'campaign_name' => 'Campaign 2', 'status' => 'Paused', 'budget' => 200],
        ];

        return response()->json($data);
    }

    public function tiktokWebToVideo()
    {
        return view('marketing-masters.tiktok_web_ads.tiktok-video-to-web');
    }

    public function tiktokWebToVideoData()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;

            $row['NRL']  = '';
            $row['NRA'] = '';
            $row['FBA'] = '';

            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NRL']  = $raw['NRL'] ?? null;
                    $row['NRA'] = $raw['NRA'] ?? null;
                    $row['FBA'] = $raw['FBA'] ?? null;
                    $row['TPFT'] = $raw['TPFT'] ?? null;
                }
            }

            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }


    public function TkImgCaraousalToWeb()
    {
        return view('marketing-masters.tiktok_web_ads.tk-img-caraousal-to-web');
    }

    public function TkImgCaraousalToWebData()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;

            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function tiktokGMVAds()
    {
        return view('marketing-masters.tiktok_shop_ads.tiktok-gmv-ads');
    }

    public function tiktokGMVAdsData() {
        $productMasters = DB::table('product_master')->orderBy('id', 'asc')->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $tiktokGMVAdData = TiktokGmvAd::whereIn('sku', $skus)->get()->keyBy('sku');

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;

            $tiktokGMVAd = $tiktokGMVAdData[$pm->sku] ?? null;

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;

            $row['ad_sold'] = $tiktokGMVAd->ad_sold ?? 0;
            $row['ad_sales'] = $tiktokGMVAd->ad_sales ?? 0;
            $row['spend'] = $tiktokGMVAd->spend ?? 0;

            if ($tiktokGMVAd != NULL) {
                $row['ad_status'] = $tiktokGMVAd->status;
            } else {
                $row['ad_status'] = 'inactive';
            }

            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function import(Request $request) {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file')->getPathname();
        $handle = fopen($file, 'r');
        if ($handle === false) {
            return response()->json(['error' => 'Unable to open uploaded file'], 500);
        }

        $firstLine = null;
        while (($line = fgets($handle)) !== false) {
            $trim = trim($line);
            if ($trim !== '') { $firstLine = $line; break; }
        }
        if ($firstLine === null) {
            fclose($handle);
            return response()->json(['error' => 'Empty file'], 422);
        }

        $possibleDelimiters = [',', ';', "\t", '|'];
        $bestDelimiter = ',';
        $maxCount = 0;
        foreach ($possibleDelimiters as $del) {
            $parts = str_getcsv($firstLine, $del);
            if (count($parts) > $maxCount) {
                $maxCount = count($parts);
                $bestDelimiter = $del;
            }
        }

        rewind($handle);

        $headerRow = [];
        while (($row = fgetcsv($handle, 0, $bestDelimiter)) !== false) {
            $allEmpty = true;
            foreach ($row as $c) { if (trim($c) !== '') { $allEmpty = false; break; } }
            if ($allEmpty) continue;
            $headerRow = $row;
            break;
        }

        if (empty($headerRow)) {
            fclose($handle);
            return response()->json(['error' => 'No header row found'], 422);
        }

        $header = array_map(fn($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), $headerRow);

        $requiredHeaders = [
            'Campaign name',
            'Cost',
            'Orders (SKU)',
            'Gross revenue',
        ];

        $missing = array_diff($requiredHeaders, $header);
        if (!empty($missing)) {
            fclose($handle);
            return response()->json(['error' => 'Missing required headers: ' . implode(', ', $missing)], 422);
        }

        $mapping = [
            'Campaign name'      => 'sku',
            'Cost'               => 'spend',
            'Orders (SKU)'       => 'ad_sold',
            'Gross revenue'      => 'ad_sales',
        ];

        $rowsBySku = [];

        while (($row = fgetcsv($handle, 0, $bestDelimiter)) !== false) {
            $allEmpty = true;
            foreach ($row as $c) { if (trim($c) !== '') { $allEmpty = false; break; } }
            if ($allEmpty) continue;

            if (count($row) !== count($header)) continue;

            $rowData = array_combine($header, $row);
            if ($rowData === false) continue;

            $sku = trim($rowData['Campaign name'] ?? '');
            if (!$sku) continue;

            $rowsBySku[$sku][] = $rowData;
        }

        fclose($handle);

        $skipped = [];
        $processed = 0;
        $errors = [];

        foreach ($rowsBySku as $sku => $skuRows) {
            if (!ProductMaster::where('sku', $sku)->exists()) {
                $skipped[] = ['reason' => 'sku_not_found_in_product_master', 'sku' => $sku];
                continue;
            }

            if (count($skuRows) > 1) {
                $nonZeroRows = [];
                foreach ($skuRows as $r) {
                    $hasNonZero = false;
                    foreach (['Cost', 'Orders (SKU)', 'Gross revenue'] as $col) {
                        $val = preg_replace('/[^\d\.\-\,]/', '', $r[$col] ?? '0');
                        $val = str_replace(',', '', $val);
                        if (is_numeric($val) && floatval($val) != 0) {
                            $hasNonZero = true;
                            break;
                        }
                    }
                    if ($hasNonZero) $nonZeroRows[] = $r;
                }

                if (count($nonZeroRows) == 0) {
                    // $errors[] = "Duplicate ad id for SKU {$sku}: all zero rows.";
                    continue;
                } elseif (count($nonZeroRows) > 1) {
                    $errors[] = "Duplicate ad id for SKU {$sku}: multiple rows with non-zero values.";
                    continue;
                } else {
                    $selectedRow = $nonZeroRows[0];
                }
            } else {
                $selectedRow = $skuRows[0];
            }

            $mapped = [];
            foreach ($mapping as $csvHeader => $dbField) {
                if (isset($selectedRow[$csvHeader]) && $selectedRow[$csvHeader] !== '') {
                    $value = $selectedRow[$csvHeader];
                    $numeric = preg_replace('/[^\d\.\-\,]/', '', $value);
                    if (strpos($numeric, ',') !== false && strpos($numeric, '.') === false) {
                        $numeric = str_replace(',', '.', str_replace('.', '', $numeric));
                    } else {
                        $numeric = str_replace(',', '', $numeric);
                    }

                    if (is_numeric($numeric)) {
                        $mapped[$dbField] = $numeric + 0;
                    } else {
                        $mapped[$dbField] = trim($value);
                    }
                }
            }

            TiktokGmvAd::updateOrCreate(['sku' => $sku], $mapped);
            $processed++;
        }

        if (!empty($skipped)) {
            Log::info('Tiktok CSV import skipped rows', ['skipped' => $skipped]);
        }

        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'processed' => $processed,
                'errors' => $errors
            ], 422);
        }

        return response()->json([
            'success' => true,
            'processed' => $processed,
            'skipped_count' => count($skipped)
        ]);
    }

    public function updateGMVAdStatus(Request $request) {
        $request->validate([
            'sku' => 'required',
            'status' => 'required|in:active,inactive',
        ]);

        $ad = TiktokGmvAd::where('sku', $request->sku)->first();

        if (!$ad) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        $ad->status = $request->status;
        $ad->save();

        return response()->json(['message' => 'Status updated successfully']);
    }

}
