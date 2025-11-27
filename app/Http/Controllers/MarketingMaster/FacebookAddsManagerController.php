<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use App\Models\AmazonDataView;
use App\Models\MetaAllAd;
use Illuminate\Support\Facades\Log;

class FacebookAddsManagerController extends Controller
{
    public function metaAllAds() {
        $latestUpdatedAt = MetaAllAd::latest('updated_at')->first();

        $formattedDate = $latestUpdatedAt
            ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A')
            : null;

        return view('marketing-masters.meta_ads_manager.metaAllAds', [
            'latestUpdatedAt' => $formattedDate,
        ]);
    }

    public function metaAllAdsData()
    {
        $metaAds = MetaAllAd::orderBy('campaign_name', 'asc')->get();

        $data = [];
        foreach ($metaAds as $ad) {
            $data[] = [
                'campaign_name' => $ad->campaign_name,
                'campaign_id' => $ad->campaign_id,
                'ad_type' => $ad->ad_type ?? '',
                'budget' => $ad->bgt ?? 0,
                'impressions_l60' => $ad->imp_l30 ?? 0,
                'impressions_l30' => $ad->imp_l30 ?? 0,
                'impressions_l7' => 0,
                'spend_l60' => $ad->spent_l30 ?? 0,
                'spend_l30' => $ad->spent_l30 ?? 0,
                'spend_l7' => 0,
                'clicks_l60' => $ad->clicks_l30 ?? 0,
                'clicks_l30' => $ad->clicks_l30 ?? 0,
                'clicks_l7' => 0,
                'sales_l60' => 0,
                'sales_l30' => 0,
                'sales_l7' => 0,
                'sales_delivered_l60' => 0,
                'sales_delivered_l30' => 0,
                'sales_delivered_l7' => 0,
                'acos_l60' => 0,
                'acos_l30' => 0,
                'acos_l7' => 0,
                'cvr_l60' => 0,
                'cvr_l30' => 0,
                'cvr_l7' => 0,
                'status' => strtoupper($ad->campaign_delivery)
            ];
        }

        return response()->json([
            'message' => 'Meta All Ads data fetched successfully',
            'data' => $data,
            'status' => 200,
        ]);
    }

    public function updateAdType(Request $request)
    {
        $request->validate([
            'campaign_name' => 'required|string',
            'ad_type' => 'required|string',
        ]);

        $metaAd = MetaAllAd::where('campaign_name', $request->campaign_name)->first();

        if (!$metaAd) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        $metaAd->ad_type = $request->ad_type;
        $metaAd->save();

        return response()->json([
            'success' => true,
            'message' => 'Ad Type updated successfully',
        ]);
    }

    public function importMetaAds(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlsx,xls',
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        
        // Handle Excel files
        if (in_array($extension, ['xlsx', 'xls'])) {
            return $this->importExcelMetaAds($file);
        }
        
        // Handle CSV files
        return $this->importCsvMetaAds($file);
    }

    private function importCsvMetaAds($file)
    {
        $handle = fopen($file->getPathname(), 'r');
        if ($handle === false) {
            return response()->json(['error' => 'Unable to open uploaded file'], 500);
        }

        // Skip empty lines and find first non-empty line
        $firstLine = null;
        while (($line = fgets($handle)) !== false) {
            $trim = trim($line);
            if ($trim !== '') { 
                $firstLine = $line; 
                break; 
            }
        }
        
        if ($firstLine === null) {
            fclose($handle);
            return response()->json(['error' => 'Empty file'], 422);
        }

        // Detect delimiter
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

        // Read header row - skip summary rows if present
        $headerRow = [];
        $rowCount = 0;
        while (($row = fgetcsv($handle, 0, $bestDelimiter)) !== false) {
            $rowCount++;
            $allEmpty = true;
            foreach ($row as $c) { 
                if (trim($c) !== '') { 
                    $allEmpty = false; 
                    break; 
                } 
            }
            if ($allEmpty) continue;
            
            // Check if this row contains the expected headers
            $rowStr = strtolower(implode(',', array_map('trim', $row)));
            if (strpos($rowStr, 'campaign name') !== false && strpos($rowStr, 'campaign id') !== false) {
                $headerRow = $row;
                break;
            }
            
            // If we've checked more than 5 rows without finding headers, use first non-empty
            if ($rowCount > 5 && empty($headerRow)) {
                $headerRow = $row;
                break;
            }
        }

        if (empty($headerRow)) {
            fclose($handle);
            return response()->json(['error' => 'No header row found'], 422);
        }

        $header = array_map(fn($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), $headerRow);

        $requiredHeaders = [
            'Campaign name',
            'Campaign delivery',
            'Ad set budget',
            'Impressions',
            'Amount spent (USD)',
            'Link clicks',
            'Campaign ID',
        ];

        $missing = array_diff($requiredHeaders, $header);
        if (!empty($missing)) {
            fclose($handle);
            return response()->json(['error' => 'Missing required headers: ' . implode(', ', $missing)], 422);
        }

        $processed = 0;
        $errors = [];

        while (($row = fgetcsv($handle, 0, $bestDelimiter)) !== false) {
            $allEmpty = true;
            foreach ($row as $c) { 
                if (trim($c) !== '') { 
                    $allEmpty = false; 
                    break; 
                } 
            }
            if ($allEmpty) continue;

            if (count($row) !== count($header)) continue;

            $rowData = array_combine($header, $row);
            if ($rowData === false) continue;

            $campaignName = trim($rowData['Campaign name'] ?? '');
            $campaignId = trim($rowData['Campaign ID'] ?? '');
            $campaignDelivery = strtolower(trim($rowData['Campaign delivery'] ?? 'inactive'));
            
            // Skip rows with empty campaign name or ID
            if (!$campaignName || !$campaignId) continue;
            
            // Skip rows with placeholder or invalid campaign IDs
            if ($campaignId === '-' || strlen($campaignId) < 5) continue;

            // Parse numeric values
            $bgt = $this->parseNumericValue($rowData['Ad set budget'] ?? '0');
            $impL30 = $this->parseNumericValue($rowData['Impressions'] ?? '0');
            $spentL30 = $this->parseNumericValue($rowData['Amount spent (USD)'] ?? '0');
            $clicksL30 = $this->parseNumericValue($rowData['Link clicks'] ?? '0');

            try {
                MetaAllAd::updateOrCreate(
                    ['campaign_name' => $campaignName],
                    [
                        'campaign_id' => $campaignId,
                        'campaign_delivery' => $campaignDelivery,
                        'bgt' => $bgt,
                        'imp_l30' => $impL30,
                        'spent_l30' => $spentL30,
                        'clicks_l30' => $clicksL30,
                    ]
                );
                $processed++;
            } catch (\Exception $e) {
                $errors[] = "Error processing campaign {$campaignName} (ID: {$campaignId}): " . $e->getMessage();
                Log::error('Meta Ads Import Error', ['campaign' => $campaignName, 'campaign_id' => $campaignId, 'error' => $e->getMessage()]);
            }
        }

        fclose($handle);

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
        ]);
    }

    private function importExcelMetaAds($file)
    {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                return response()->json(['error' => 'Empty file'], 422);
            }

            // Find header row - skip summary rows if present
            $header = null;
            $dataStartIndex = 0;
            foreach ($rows as $index => $row) {
                $allEmpty = true;
                foreach ($row as $cell) {
                    if (trim($cell) !== '' && $cell !== null) {
                        $allEmpty = false;
                        break;
                    }
                }
                if ($allEmpty) continue;
                
                // Check if this row contains the expected headers
                $rowStr = strtolower(implode(',', array_map(function($c) { return trim($c ?? ''); }, $row)));
                if (strpos($rowStr, 'campaign name') !== false && strpos($rowStr, 'campaign id') !== false) {
                    $header = $row;
                    $dataStartIndex = $index + 1;
                    break;
                }
                
                // If we've checked more than 5 rows, use first non-empty
                if ($index > 5 && !$header) {
                    $header = $row;
                    $dataStartIndex = $index + 1;
                    break;
                }
            }

            if (!$header) {
                return response()->json(['error' => 'No header row found'], 422);
            }

            $requiredHeaders = [
                'Campaign name',
                'Campaign delivery',
                'Ad set budget',
                'Impressions',
                'Amount spent (USD)',
                'Link clicks',
                'Campaign ID',
            ];

            $missing = array_diff($requiredHeaders, $header);
            if (!empty($missing)) {
                return response()->json(['error' => 'Missing required headers: ' . implode(', ', $missing)], 422);
            }

            $processed = 0;
            $errors = [];

            for ($i = $dataStartIndex; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                $allEmpty = true;
                foreach ($row as $cell) {
                    if (trim($cell) !== '' && $cell !== null) {
                        $allEmpty = false;
                        break;
                    }
                }
                if ($allEmpty) continue;

                if (count($row) !== count($header)) continue;

                $rowData = array_combine($header, $row);
                if ($rowData === false) continue;

                $campaignName = trim($rowData['Campaign name'] ?? '');
                $campaignId = trim($rowData['Campaign ID'] ?? '');
                $campaignDelivery = strtolower(trim($rowData['Campaign delivery'] ?? 'inactive'));
                
                // Skip rows with empty campaign name or ID
                if (!$campaignName || !$campaignId) continue;
                
                // Skip rows with placeholder or invalid campaign IDs
                if ($campaignId === '-' || strlen($campaignId) < 5) continue;

                // Parse numeric values
                $bgt = $this->parseNumericValue($rowData['Ad set budget'] ?? '0');
                $impL30 = $this->parseNumericValue($rowData['Impressions'] ?? '0');
                $spentL30 = $this->parseNumericValue($rowData['Amount spent (USD)'] ?? '0');
                $clicksL30 = $this->parseNumericValue($rowData['Link clicks'] ?? '0');

                try {
                    MetaAllAd::updateOrCreate(
                        ['campaign_name' => $campaignName],
                        [
                            'campaign_id' => $campaignId,
                            'campaign_delivery' => $campaignDelivery,
                            'bgt' => $bgt,
                            'imp_l30' => $impL30,
                            'spent_l30' => $spentL30,
                            'clicks_l30' => $clicksL30,
                        ]
                    );
                    $processed++;
                } catch (\Exception $e) {
                    $errors[] = "Error processing campaign {$campaignName} (ID: {$campaignId}): " . $e->getMessage();
                    Log::error('Meta Ads Import Error', ['campaign' => $campaignName, 'campaign_id' => $campaignId, 'error' => $e->getMessage()]);
                }
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
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error processing Excel file: ' . $e->getMessage()], 500);
        }
    }

    private function parseNumericValue($value)
    {
        if ($value === '' || $value === null) {
            return 0;
        }

        // Remove currency symbols and other non-numeric characters except comma, dot, and minus
        $numeric = preg_replace('/[^\d\.\-\,]/', '', $value);
        
        // Handle European format (comma as decimal separator)
        if (strpos($numeric, ',') !== false && strpos($numeric, '.') === false) {
            $numeric = str_replace(',', '.', str_replace('.', '', $numeric));
        } else {
            // Handle US format (comma as thousand separator)
            $numeric = str_replace(',', '', $numeric);
        }

        if (is_numeric($numeric)) {
            return $numeric + 0;
        }

        return 0;
    }

    public function index()
    {
        return view('marketing-masters.facebook_ads_manager.index');
    }

    public function getFacebookAdsData()
    {
        $data = [
            ['id' => 1, 'campaign_name' => 'Campaign 1', 'status' => 'Active', 'budget' => 100],
            ['id' => 2, 'campaign_name' => 'Campaign 2', 'status' => 'Paused', 'budget' => 200],
        ];

        return response()->json($data);
    }

    public function facebookWebToVideo()
    {
        return view('marketing-masters.facebook_web_ads.facebook-video-to-web');
    }

    public function facebookWebToVideoData()
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


    public function FbImgCaraousalToWeb()
    {
        return view('marketing-masters.facebook_web_ads.fb-img-caraousal-to-web');
    }

    public function FbImgCaraousalToWebData()
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
}
