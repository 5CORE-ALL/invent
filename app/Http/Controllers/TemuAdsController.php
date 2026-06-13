<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TemuMetric;
use App\Models\TemuDataView;
use App\Models\TemuListingStatus;
use App\Models\TemuDailyData;
use App\Models\TemuAdData;
use App\Models\TemuCampaignReport;
use App\Models\TemuPricing;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use App\Support\TemuGoodsIdHelper;

class TemuAdsController extends Controller
{
    public function index()
    {
        $marketplaceData = MarketplacePercentage::where("marketplace", "Temu")->first();
        $temuPercentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $temuAdPercentage = $marketplaceData ? $marketplaceData->ad_updates : 100;

        // Get chart data for last 30 days
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);

        // Create array for all 30 days with data or zeros
        $dates = [];
        $clicks = [];
        $spend = [];
        $adSales = [];
        $adSold = [];
        $acos = [];
        $cvr = [];

        for ($i = 30; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;
            
            // Placeholder values - can be populated with actual Temu metrics
            $clicks[] = 0;
            $spend[] = 0;
            $adSales[] = 0;
            $adSold[] = 0;
            $acos[] = 0;
            $cvr[] = 0;
        }

        return view('campaign.temu.temu-utilized', compact('temuPercentage', 'temuAdPercentage', 'dates', 'clicks', 'spend', 'adSales', 'adSold', 'acos', 'cvr'));
    }

    public function getTemuAdsData()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')
            ->orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get();

        $skus = $productMasters->pluck("sku")->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        
        // Fetch Temu L30 from TemuDailyData (sum of quantity_purchased grouped by contribution_sku)
        $temuSalesData = TemuDailyData::whereIn('contribution_sku', $skus)
            ->selectRaw('contribution_sku as sku, SUM(quantity_purchased) as temu_l30')
            ->groupBy('contribution_sku')
            ->get()
            ->keyBy('sku');

        // Fetch NRA from TemuDataView (where it's actually stored)
        $temuDataViews = TemuDataView::whereIn("sku", $skus)->get()->keyBy("sku");

        // Fetch goods_id mapping from TemuPricing (same query as temu-decrease for consistency)
        $temuPricing = TemuPricing::whereIn("sku", $skus)
            ->get()
            ->keyBy('sku');

        // Get all goods_ids
        $goodsIds = $temuPricing->pluck('goods_id')->filter()->unique()->values()->all();

        // Fetch L30 ad data from temu_campaign_reports
        $adDataL30 = TemuCampaignReport::whereIn('goods_id', $goodsIds)
            ->where('report_range', 'L30')
            ->selectRaw('goods_id, 
                SUM(spend) as spend_l30,
                SUM(clicks) as clicks_l30,
                SUM(base_price_sales) as base_price_sales_l30,
                SUM(COALESCE(sub_orders,0)) as ad_sold_l30,
                AVG(acos_ad) as acos_l30,
                AVG(roas) as roas_l30,
                AVG(in_roas) as in_roas_l30,
                MAX(status) as status_l30')
            ->groupBy('goods_id')
            ->get()
            ->keyBy('goods_id');

        // Fetch L7 ad data from temu_campaign_reports
        $adDataL7 = TemuCampaignReport::whereIn('goods_id', $goodsIds)
            ->where('report_range', 'L7')
            ->selectRaw('goods_id, 
                SUM(spend) as spend_l7,
                SUM(clicks) as clicks_l7,
                SUM(base_price_sales) as base_price_sales_l7,
                AVG(acos_ad) as acos_l7,
                AVG(roas) as roas_l7,
                AVG(in_roas) as in_roas_l7,
                MAX(status) as status_l7')
            ->groupBy('goods_id')
            ->get()
            ->keyBy('goods_id');

        // Calculate total SKU count (excluding PARENT SKUs and deleted_at)
        $totalSkuCount = ProductMaster::whereNull('deleted_at')
            ->whereRaw("UPPER(sku) NOT LIKE 'PARENT %'")
            ->count();

        // Calculate zero INV count (excluding PARENT SKUs)
        $zeroInvCount = 0;
        $processedZeroInvSkus = [];
        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $isParentSku = strpos($sku, 'PARENT') !== false;
            
            if (!$isParentSku && !in_array($sku, $processedZeroInvSkus)) {
                $processedZeroInvSkus[] = $sku;
                $shopify = $shopifyData->get($pm->sku);
                $inv = ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0;
                if ($inv <= 0) {
                    $zeroInvCount++;
                }
            }
        }

        $data = [];

        foreach ($productMasters as $product) {
            $sku = $product->sku;
            $shopify = $shopifyData->get($sku);
            $temuSales = $temuSalesData->get($sku);
            $temuPricingItem = $temuPricing->get($sku);
            
            // Get INV from Shopify
            $inv = ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0;
            
            // Get OV L30 from Shopify quantity column (as per getTemuDecreaseData)
            $ovL30 = ($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0;
            
            // Get Temu L30 from TemuDailyData
            $temuL30 = ($temuSales && isset($temuSales->temu_l30)) ? (int)$temuSales->temu_l30 : 0;
            
            // Get NRA from TemuDataView (where it's actually stored)
            $nraValue = 'RA'; // Default to RA
            if (isset($temuDataViews[$sku])) {
                $viewData = $temuDataViews[$sku];
                $valuesArr = is_array($viewData->value) 
                    ? $viewData->value 
                    : (json_decode($viewData->value, true) ?: []);
                if (isset($valuesArr['NR'])) {
                    $nraValue = $valuesArr['NR'] ?? 'RA';
                }
            }
            
            // Calculate DIL % (OV L30 / INV * 100)
            $dilPercent = 0;
            if ($inv > 0) {
                $dilPercent = round(($ovL30 / $inv) * 100, 2);
            }

            // Get ad data from TemuCampaignReport using goods_id
            $goodsId = $temuPricingItem ? $temuPricingItem->goods_id : null;
            $adL30 = $goodsId ? $adDataL30->get($goodsId) : null;
            $adL7 = $goodsId ? $adDataL7->get($goodsId) : null;
            
            // Check if campaign exists (has data in temu_campaign_reports)
            // Campaign exists if there's any record in temu_campaign_reports for this goods_id
            $hasCampaign = ($adL30 !== null) || ($adL7 !== null);

            // Determine status - If campaign exists, default to "Active", otherwise use status from database or "Not Created" (like TikTok)
            $status = null;
            // Get status from database (prioritize L30, fallback to L7)
            if ($adL30 && isset($adL30->status_l30) && !empty($adL30->status_l30) && $adL30->status_l30 !== 'NULL') {
                $status = $adL30->status_l30;
            } elseif ($adL7 && isset($adL7->status_l7) && !empty($adL7->status_l7) && $adL7->status_l7 !== 'NULL') {
                $status = $adL7->status_l7;
            }
            
            // If campaign exists and status is null/empty, default to "Active" (like TikTok)
            if ($hasCampaign && (empty($status) || $status === null)) {
                $status = 'Active';
            } elseif (empty($status) || $status === null) {
                $status = 'Not Created';
            }

            $row = [
                'sku' => $sku,
                'parent' => $product->parent,
                'INV' => $inv,
                'L30' => $ovL30,
                'temu_l30' => $temuL30,
                'DIL %' => $dilPercent,
                'NR' => $nraValue,
                'hasCampaign' => $hasCampaign,
                // L30 ad metrics
                'spend_l30' => $adL30 ? round((float)$adL30->spend_l30, 2) : 0,
                'clicks_l30' => $adL30 ? (int)$adL30->clicks_l30 : 0,
                'base_price_sales_l30' => $adL30 ? round((float)$adL30->base_price_sales_l30, 2) : 0,
                'ad_sold_l30' => $adL30 ? (int)$adL30->ad_sold_l30 : 0,
                'acos_l30' => $adL30 && $adL30->roas_l30 > 0 ? round((100 / (float)$adL30->roas_l30), 2) : 0,
                'in_roas_l30' => $adL30 ? round((float)$adL30->in_roas_l30, 2) : 0,
                'out_roas_l30' => $adL30 ? round((float)$adL30->roas_l30, 2) : 0,
                // L7 ad metrics
                'spend_l7' => $adL7 ? round((float)$adL7->spend_l7, 2) : 0,
                'clicks_l7' => $adL7 ? (int)$adL7->clicks_l7 : 0,
                'base_price_sales_l7' => $adL7 ? round((float)$adL7->base_price_sales_l7, 2) : 0,
                'acos_l7' => $adL7 && $adL7->roas_l7 > 0 ? round((100 / (float)$adL7->roas_l7), 2) : 0,
                'in_roas_l7' => $adL7 ? round((float)$adL7->in_roas_l7, 2) : 0,
                'out_roas_l7' => $adL7 ? round((float)$adL7->roas_l7, 2) : 0,
                // Status
                'status' => $status,
            ];

            $data[] = $row;
        }

        // Calculate total campaign count (unique goods_id that have campaigns)
        $totalCampaignCount = TemuCampaignReport::distinct('goods_id')
            ->pluck('goods_id')
            ->filter()
            ->unique()
            ->count();

        // Compute authoritative L30 totals from temu-decrease endpoint (uses normalized SKU matching)
        $temuCtrl = app(\App\Http\Controllers\MarketPlace\TemuController::class);
        $temuResponse = $temuCtrl->getTemuDecreaseData();
        $temuRows = json_decode($temuResponse->getContent(), true)['data'] ?? [];
        $l30Spend = 0; $l30Sales = 0; $l30Sold = 0; $l30Clicks = 0;
        foreach ($temuRows as $tr) {
            if (empty($tr['sku'])) continue;
            $l30Spend += round((float) ($tr['spend_l30'] ?? 0), 2);
            $l30Sales += round((float) ($tr['ad_sales_l30'] ?? 0), 2);
            $l30Sold += (int) ($tr['ad_sold_l30'] ?? 0);
            $l30Clicks += (int) ($tr['clicks_l30'] ?? 0);
        }

        return response()->json([
            'data' => $data,
            'total_sku_count' => $totalSkuCount,
            'zero_inv_count' => $zeroInvCount,
            'total_campaign_count' => $totalCampaignCount,
            'l30_totals' => [
                'spend' => round($l30Spend, 2),
                'ad_sales' => round($l30Sales, 2),
                'ad_sold' => $l30Sold,
                'clicks' => $l30Clicks,
            ],
        ]);
    }

    public function updateTemuAds(Request $request)
    {
        $sku = $request->input('sku');
        $field = $request->input('field');
        $value = $request->input('value');

        if (!$sku || !$field || $value === null) {
            return response()->json([
                'success' => false,
                'message' => 'SKU, field, and value are required'
            ], 400);
        }

        // Save NRA to TemuDataView (same as saveNrToDatabase in TemuController)
        if ($field === 'NR') {
            $dataView = TemuDataView::firstOrNew(['sku' => $sku]);
            $existingValue = is_array($dataView->value) 
                ? $dataView->value 
                : (json_decode($dataView->value, true) ?: []);
            
            $existingValue['NR'] = $value;
            $dataView->value = $existingValue;
            $dataView->save();

            return response()->json([
                'success' => true,
                'message' => 'NRA updated successfully',
                'data' => $dataView
            ]);
        }

        // Save IN ROAS L30 or L7 to TemuCampaignReport
        if ($field === 'in_roas_l30' || $field === 'in_roas_l7') {
            // Get goods_id from TemuPricing
            $temuPricing = TemuPricing::where('sku', $sku)
                ->whereNotNull('goods_id')
                ->first();

            if (!$temuPricing || !$temuPricing->goods_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU does not have a goods_id mapping'
                ], 400);
            }

            $goodsId = $temuPricing->goods_id;
            $inRoasValue = (float) $value;
            $reportRange = ($field === 'in_roas_l30') ? 'L30' : 'L7';

            // Check if any records exist for this goods_id and report_range
            $existingRecords = TemuCampaignReport::where('goods_id', $goodsId)
                ->where('report_range', $reportRange)
                ->get();

            if ($existingRecords->count() > 0) {
                // Update all existing records
                $updated = TemuCampaignReport::where('goods_id', $goodsId)
                    ->where('report_range', $reportRange)
                    ->update(['in_roas' => $inRoasValue]);

                return response()->json([
                    'success' => true,
                    'message' => 'IN ROAS ' . $reportRange . ' updated successfully',
                    'updated_count' => $updated
                ]);
            } else {
                // No records exist, create a new one
                // Try to get goods_name from the other report_range record or use a default
                $otherRecord = TemuCampaignReport::where('goods_id', $goodsId)
                    ->where('report_range', $reportRange === 'L30' ? 'L7' : 'L30')
                    ->first();
                
                $goodsName = $otherRecord ? $otherRecord->goods_name : null;

                // Create a new record with minimal required fields
                TemuCampaignReport::create([
                    'goods_id' => $goodsId,
                    'goods_name' => $goodsName,
                    'report_range' => $reportRange,
                    'in_roas' => $inRoasValue,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'IN ROAS ' . $reportRange . ' created and saved successfully',
                    'created' => true
                ]);
            }
        }

        // Save Status to TemuCampaignReport
        if ($field === 'status') {
            // Get goods_id from TemuPricing
            $temuPricing = TemuPricing::where('sku', $sku)
                ->whereNotNull('goods_id')
                ->first();

            if (!$temuPricing || !$temuPricing->goods_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU does not have a goods_id mapping'
                ], 400);
            }

            // Validate status value
            $validStatuses = ['Active', 'Inactive', 'Not Created'];
            if (!in_array($value, $validStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status value. Must be one of: ' . implode(', ', $validStatuses)
                ], 400);
            }

            $goodsId = $temuPricing->goods_id;

            // Update status for all records with this goods_id (both L30 and L7)
            $updated = TemuCampaignReport::where('goods_id', $goodsId)
                ->update(['status' => $value]);

            // If no records exist, create records for both L30 and L7
            if ($updated === 0) {
                // Try to get goods_name from an existing TemuCampaignReport record (any report_range)
                $existingRecord = TemuCampaignReport::where('goods_id', $goodsId)->first();
                $goodsName = $existingRecord ? $existingRecord->goods_name : null;

                // Create records for both report ranges
                TemuCampaignReport::create([
                    'goods_id' => $goodsId,
                    'goods_name' => $goodsName,
                    'report_range' => 'L30',
                    'status' => $value,
                ]);

                TemuCampaignReport::create([
                    'goods_id' => $goodsId,
                    'goods_name' => $goodsName,
                    'report_range' => 'L7',
                    'status' => $value,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Status created and saved successfully',
                    'created' => true
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'updated_count' => $updated
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid field'
        ], 400);
    }

    public function uploadCampaignReport(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file',
                'report_range' => 'required|in:L7,L30,L60'
            ]);

            $file = $request->file('file');
            $reportRange = $request->input('report_range');
            $ext = strtolower($file->getClientOriginalExtension());

            // ── Parse rows ────────────────────────────────────────────────────────
            // Accept Excel (.xlsx/.xls), CSV, AND tab-separated text (.txt/.tsv).
            // Temu exports its ads report as a tab-delimited .txt file; PhpSpreadsheet
            // treats the whole row as one cell for that format, so we parse it manually.
            $isTsv = in_array($ext, ['txt', 'tsv', ''])
                || $this->detectTsv($file->getPathname());

            if ($isTsv) {
                [$headers, $dataRows] = $this->parseTsvFile($file->getPathname());
            } else {
                $spreadsheet = IOFactory::load($file->getPathname());
                $sheet = $spreadsheet->getActiveSheet();
                $rawHeaders = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1', null, true, false)[0] ?? [];
                $headers = array_map(fn ($h) => is_string($h) ? trim($h) : $h, $rawHeaders);
                $dataRows = null; // will iterate via $sheet
            }

            $goodsIdColIdx = array_search('Goods ID', $headers, true);
            if ($goodsIdColIdx === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'File must contain a column named exactly "Goods ID".',
                ], 422);
            }
            $skuColIdx = array_search('SKU', $headers, true);

            $normalizeCellValue = function ($value) {
                if ($value instanceof RichText) {
                    return trim($value->getPlainText());
                }
                if (is_object($value) && method_exists($value, '__toString')) {
                    return trim((string) $value);
                }
                if (is_string($value)) {
                    return trim($value);
                }

                return $value;
            };
            $parseCurrency = function ($value) use ($normalizeCellValue) {
                $value = $normalizeCellValue($value);
                if (empty($value) || $value === '∞') {
                    return null;
                }

                return floatval(str_replace(['$', ','], '', $value));
            };
            $parsePercent = function ($value) use ($normalizeCellValue) {
                $value = $normalizeCellValue($value);
                if (empty($value) || $value === '∞') {
                    return null;
                }

                return floatval(str_replace('%', '', $value));
            };
            $parseNumber = function ($value) use ($normalizeCellValue) {
                $value = $normalizeCellValue($value);
                if ($value === null || $value === '' || $value === '∞') {
                    return 0;
                }

                return floatval(str_replace([',', '%', '$'], '', (string) $value));
            };

            // Read a value by trying multiple header aliases. The new Temu export uses
            // suffixed column names ("(Ad)" / "(Overall)"); older exports used the bare
            // names. Prefer (Ad), fall back to (Overall), then to the legacy bare name.
            $col = function (array $rowData, array $aliases) {
                foreach ($aliases as $a) {
                    if (array_key_exists($a, $rowData) && $rowData[$a] !== null && $rowData[$a] !== '') {
                        return $rowData[$a];
                    }
                }
                return null;
            };

            $imported = 0;
            $skipped = 0;
            $rowErrors = 0;
            $firstRowError = null;
            $numCols = count($headers);

            // Build the iterable list of raw rows regardless of source format
            $highestRow = 0;
            if ($isTsv) {
                $allRows = $dataRows; // already an array of string arrays (0-indexed, no header row)
            } else {
                $highestRow = (int) $sheet->getHighestDataRow();
                $allRows = null; // will iterate $sheet directly
            }

            DB::beginTransaction();
            try {
                TemuCampaignReport::where('report_range', $reportRange)->delete();

                $iterateFn = function () use ($isTsv, $allRows, &$sheet, $highestRow, $normalizeCellValue, $numCols) {
                    if ($isTsv) {
                        foreach ($allRows as $row) {
                            yield $row;
                        }
                    } else {
                        for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
                            $raw = [];
                            for ($c = 1; $c <= $numCols; $c++) {
                                $raw[] = $normalizeCellValue($sheet->getCell(Coordinate::stringFromColumnIndex($c).$rowNum)->getValue());
                            }
                            yield ['_rowNum' => $rowNum, '_raw' => $raw];
                        }
                    }
                };

                foreach ($iterateFn() as $entry) {
                    // Normalise to a flat string array
                    if ($isTsv) {
                        $row = $entry;
                        // Skip "Total …" summary rows
                        if (stripos((string) ($row[0] ?? ''), 'Total') !== false) {
                            $skipped++;
                            continue;
                        }
                    } else {
                        $rowNum = $entry['_rowNum'];
                        $row = $entry['_raw'];
                        $firstCell = $row[0] ?? null;
                        if ($firstCell !== null && $firstCell !== '' && stripos((string) $firstCell, 'Total') !== false) {
                            $skipped++;
                            continue;
                        }
                    }

                    if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                        $skipped++;
                        continue;
                    }

                    $rowData = @array_combine($headers, array_pad(array_slice($row, 0, $numCols), $numCols, null));
                    if (! is_array($rowData)) {
                        $skipped++;
                        continue;
                    }

                    // Extract Goods ID — for TSV it's plain text, for Excel use the cell helper
                    if ($isTsv) {
                        $rawGoodsId = trim((string) ($row[$goodsIdColIdx] ?? ''));
                        $goodsIdNormalized = $rawGoodsId !== '' ? TemuGoodsIdHelper::normalizeKey($rawGoodsId) : null;
                    } else {
                        $goodsCell = $sheet->getCell(Coordinate::stringFromColumnIndex($goodsIdColIdx + 1).$rowNum);
                        $goodsIdNormalized = TemuGoodsIdHelper::fromSpreadsheetCell($goodsCell);
                    }

                    if (! $goodsIdNormalized) {
                        $skipped++;
                        Log::warning("Temu campaign report upload ({$reportRange}): skipped row — missing Goods ID");
                        continue;
                    }

                    // SKU from column "SKU" (col index 2 in the Temu export, col name "SKU")
                    $skuValue = $skuColIdx !== false
                        ? strtoupper(trim((string) ($row[$skuColIdx] ?? '')))
                        : null;

                    try {
                        $campaignData = [
                            'goods_name'   => $rowData['Goods name'] ?? null,
                            'goods_id'     => $goodsIdNormalized,
                            'sku'          => $skuValue ?: null,
                            'report_range' => $reportRange,
                            'spend'        => $parseCurrency($col($rowData, ['Spend'])),
                            'base_price_sales' => $parseCurrency($col($rowData, ['Base Price Sales (Ad)', 'Base Price Sales (Overall)', 'Base price sales'])),
                            'roas'         => $parseNumber($col($rowData, ['ROAS (Ad)', 'ROAS (Overall)', 'ROAS']) ?? 0),
                            'acos_ad'      => $parsePercent($col($rowData, ['ACOS (Ad)', 'ACOS (Overall)', 'ACOS(AD)'])),
                            'cost_per_transaction' => $parseCurrency($col($rowData, ['Cost Per Order (Ad)', 'Cost Per Order (Overall)', 'Cost per transaction'])),
                            'sub_orders'   => (int) str_replace(',', '', (string) ($col($rowData, ['Sub Order Count (Ad)', 'Sub Order Count (Overall)', 'Sub-Orders']) ?? 0)),
                            'items'        => (int) str_replace(',', '', (string) ($col($rowData, ['Item Quantity (Ad)', 'Items (Overall)', 'Items']) ?? 0)),
                            'net_total_cost' => $parseCurrency($col($rowData, ['Net total cost'])),
                            'net_declared_sales' => $parseCurrency($col($rowData, ['Net Base Price Sales (Ad)', 'Net declared sales'])),
                            'net_roas'     => $parseNumber($col($rowData, ['Net ROAS (Ad)', 'Net advertising return on investment (ROAS)']) ?? 0),
                            'net_acos_ad'  => $parsePercent($col($rowData, ['Net ACOS (Ad)', 'Net advertising cost ratio (advertising)'])),
                            'net_cost_per_transaction' => $parseCurrency($col($rowData, ['Net Cost Per Order (Ad)', 'Net cost per transaction'])),
                            'net_orders'   => (int) str_replace(',', '', (string) ($col($rowData, ['Net Sub Order Count (Ad)', 'Net Orders']) ?? 0)),
                            'net_number_pieces' => (int) str_replace(',', '', (string) ($col($rowData, ['Net Item Quantity (Ad)', 'Net number of pieces']) ?? 0)),
                            'impressions'  => (int) str_replace(',', '', (string) ($col($rowData, ['Impressions (Ad)', 'Impressions (Overall)', 'Impressions']) ?? 0)),
                            'clicks'       => (int) str_replace(',', '', (string) ($col($rowData, ['Clicks (Ad)', 'Clicks (Overall)', 'Clicks']) ?? 0)),
                            'ctr'          => $parsePercent($col($rowData, ['Click Through Rate (Ad)', 'CTR (Overall)', 'CTR'])),
                            'cvr'          => $parsePercent($col($rowData, ['Conversion Rate (Ad)', 'CVR (Overall)', 'Conversion Rate (CVR)'])),
                            'add_to_cart_number' => (int) str_replace(',', '', (string) ($col($rowData, ['Add To Cart (Ad)', 'Add to cart count (Overall)', 'Add-to-cart number']) ?? 0)),
                            'weekly_roas'  => $parseNumber($col($rowData, ['Natural Week ROAS (Ad)', 'Weekly ROAS']) ?? 0),
                            'target'       => $parseNumber($col($rowData, ['Natural Week Target ROAS (Ad)', 'Target']) ?? 0),
                        ];

                        TemuCampaignReport::create($campaignData);
                        $imported++;
                    } catch (\Exception $e) {
                        $skipped++;
                        $rowErrors++;
                        if ($firstRowError === null) {
                            $firstRowError = $e->getMessage();
                        }
                        Log::warning("Failed to import campaign row: ".$e->getMessage());
                        continue;
                    }
                }

                // Guard: never wipe this range with a zero-import commit.
                if ($imported === 0) {
                    DB::rollBack();
                    $msg = "Imported 0 rows for {$reportRange}. Existing {$reportRange} campaign data was kept.";
                    if ($firstRowError) {
                        $msg .= " First row error: {$firstRowError}";
                    } else {
                        $msg .= " All rows were skipped (check file format/headers).";
                    }

                    return response()->json([
                        'success' => false,
                        'message' => $msg,
                        'imported' => 0,
                        'skipped' => $skipped,
                        'row_errors' => $rowErrors,
                    ], 422);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported $imported records for $reportRange",
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'row_errors' => $rowErrors,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error uploading Temu campaign report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error uploading file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return true if the file looks like a tab-delimited text file.
     * We check: extension is .txt/.tsv OR the first line contains tabs.
     */
    private function detectTsv(string $path): bool
    {
        $handle = fopen($path, 'r');
        if (!$handle) return false;
        $line = fgets($handle);
        fclose($handle);
        return $line !== false && substr_count($line, "\t") >= 3;
    }

    /**
     * Parse a tab-delimited text file into [$headers, $dataRows].
     * Skips the first "Total …" summary row that Temu includes as row 2.
     */
    private function parseTsvFile(string $path): array
    {
        $headers  = [];
        $dataRows = [];
        $handle   = fopen($path, 'r');
        if (!$handle) return [[], []];

        $lineNum = 0;
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') continue;

            $cols = explode("\t", $line);
            $cols = array_map('trim', $cols);

            if ($lineNum === 0) {
                $headers = $cols;
            } else {
                // Skip the "Total N item(s)" summary row Temu adds as row 2
                if (stripos($cols[0] ?? '', 'Total') !== false && $lineNum === 1) {
                    $lineNum++;
                    continue;
                }
                $dataRows[] = $cols;
            }
            $lineNum++;
        }
        fclose($handle);
        return [$headers, $dataRows];
    }
}
