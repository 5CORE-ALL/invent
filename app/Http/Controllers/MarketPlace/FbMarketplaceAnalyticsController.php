<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MarketplacePercentage;
use App\Models\FbMarketplacePriceSoldData;
use App\Models\FBMarketplaceListingStatus;
use App\Http\Controllers\Sales\FacebookMarketplaceController;
use App\Models\ProductMaster;
use App\Models\AmazonDataView;
use App\Models\ShopifySku;
use App\Services\ChannelPromoPricingService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FbMarketplaceAnalyticsController extends Controller
{
    public function fbMarketplaceTabulatorView(Request $request)
    {
        return view('market-places.fb_marketplace_tabulator_view');
    }

    public function getFbMarketplaceTabulatorData(Request $request)
    {
        $productMasterRows = ProductMaster::all();
        $skus = $productMasterRows->pluck('sku')->toArray();

        // Fetch Shopify data (inventory + image) for these SKUs
        $shopifyData = ShopifySku::mapByProductSkus($skus);

        // Price upload on this page (sku / price) — never the synced sheet
        $priceSoldBySku = [];
        foreach (FbMarketplacePriceSoldData::all() as $row) {
            $key = strtoupper(trim((string) $row->sku));
            if ($key === '') {
                continue;
            }
            $priceSoldBySku[$key] = $row;
            $priceSoldBySku[str_replace(' ', '', $key)] = $row;
        }

        // Fetch listing statuses (sprice / nr_req / approved / links) keyed by SKU
        $listingStatusData = FBMarketplaceListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        // FB L30 sold + avg sold_price — same last-30 Pacific-day window as /facebook-marketplace
        $fbL30BySku = FacebookMarketplaceController::l30MetricsBySku();
        $fbLatestPriceBySku = FacebookMarketplaceController::latestSoldPriceBySku();

        // Margin from marketplace_percentages (same source as /facebook-marketplace)
        $mpRow = MarketplacePercentage::where('marketplace', 'FB Marketplace')->first()
            ?: MarketplacePercentage::where('marketplace', 'FBMarketplace')->first();
        $percentage = $mpRow && $mpRow->percentage !== null ? (float) $mpRow->percentage : null;
        $factor = ($percentage !== null ? $percentage : 100) / 100;

        // Std Prc — amazon_data_view.STANDARD_PRICE (same shared store as /amazon-tabulator-view)
        $amazonStandardPrices = [];
        foreach (AmazonDataView::whereIn('sku', $skus)->get(['sku', 'value']) as $adv) {
            $val = is_array($adv->value)
                ? $adv->value
                : (json_decode((string) ($adv->value ?? ''), true) ?: []);
            $std = $val['STANDARD_PRICE'] ?? null;
            if (is_numeric($std) && (float) $std > 0) {
                $amazonStandardPrices[strtoupper(trim((string) $adv->sku))] = round((float) $std, 2);
            }
        }

        $promoMap = app(ChannelPromoPricingService::class)->mapForSkus('fb_marketplace', $skus);

        $data = [];
        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;

            // Skip parent rows
            if (stripos($sku, 'PARENT') !== false) {
                continue;
            }

            $values = is_array($productMaster->Values)
                ? $productMaster->Values
                : (json_decode($productMaster->Values, true) ?: []);
            $shopifyItem = $shopifyData[$sku] ?? null;
            $skuKey = strtoupper(trim((string) $sku));
            $skuKeyNs = str_replace(' ', '', $skuKey);
            $priceSold = $priceSoldBySku[$skuKey] ?? $priceSoldBySku[$skuKeyNs] ?? null;
            $l30 = $fbL30BySku[$skuKey] ?? $fbL30BySku[$skuKeyNs] ?? null;

            // Buyer/Seller links & saved fields from listing status
            $statusValue = $listingStatusData[$sku]->value ?? [];
            if (is_string($statusValue)) {
                $statusValue = json_decode($statusValue, true) ?: [];
            }

            // Price + L30: uploaded FB Sales / price-sold only — never sheet
            $uploadedPrice = $priceSold && $priceSold->price !== null && $priceSold->price !== ''
                ? (float) $priceSold->price
                : 0.0;
            $price = $uploadedPrice > 0
                ? $uploadedPrice
                : (float) ($l30['price'] ?? $fbLatestPriceBySku[$skuKey] ?? $fbLatestPriceBySku[$skuKeyNs] ?? 0);
            $soldL30 = (int) ($l30['qty'] ?? 0);
            $lp = (float) ($values['lp'] ?? 0);
            $ship = (float) ($values['ship'] ?? 0);
            $inv = (float) ($shopifyItem->inv ?? 0);

            // NR/REQ: default to REQ when INV > 0, else NR (same as listing page)
            $nrReq = $statusValue['nr_req'] ?? ($inv > 0 ? 'REQ' : 'NR');

            // PFT% and ROI% (no shipping, mirrors Mercari w/o Ship)
            $pft = $price > 0 ? (($price * $factor - $lp) / $price) * 100 : 0;
            $roi = $lp > 0 ? (($price * $factor - $lp) / $lp) * 100 : 0;

            // S Price (manual, saved in listing status) and its SPFT/SROI
            $sprice = isset($statusValue['sprice']) && $statusValue['sprice'] !== '' && $statusValue['sprice'] !== null
                ? (float) $statusValue['sprice']
                : null;
            $spft = ($sprice !== null && $sprice > 0) ? (($sprice * $factor - $lp) / $sprice) * 100 : 0;
            $sroi = ($sprice !== null && $lp > 0) ? (($sprice * $factor - $lp) / $lp) * 100 : 0;

            $ovL30 = (float) ($shopifyItem->quantity ?? 0);
            $dil = $inv > 0 ? ($ovL30 / $inv) * 100 : 0;
            $views = 0;
            $cvr = $views > 0 ? ($soldL30 / $views) * 100 : 0;

            $row = [
                'Parent' => $productMaster->parent ?? null,
                'image_path' => $shopifyItem->image_src ?? ($values['image_path'] ?? null),
                'sku' => $sku,
                '(Child) sku' => $sku,
                'INV' => $shopifyItem->inv ?? 0,
                'L30' => $ovL30,
                'Dil' => round($dil, 2),
                'price' => $price,
                'sold' => $soldL30,
                'views' => $views,
                'CVR%' => round($cvr, 2),
                'PFT' => round($pft, 2),
                'ROI' => round($roi, 2),
                'sprice' => $sprice,
                'SPRICE' => $sprice,
                'SPFT' => round($spft, 2),
                'SROI' => round($sroi, 2),
                'nr_req' => $nrReq,
                'lp' => $lp,
                'ship' => $ship,
                'LP_productmaster' => $lp,
                'Ship_productmaster' => $ship,
                'factor' => $factor,
                'percentage' => $factor,
                'buyer_link' => $statusValue['buyer_link'] ?? null,
                'seller_link' => $statusValue['seller_link'] ?? null,
                'approved' => $statusValue['approved'] ?? null,
                'STANDARD_PRICE' => $amazonStandardPrices[strtoupper(trim((string) $sku))] ?? null,
            ];
            $data[] = app(ChannelPromoPricingService::class)->applyToRow($row, $promoMap, (string) $sku);
        }

        return response()->json(['data' => $data]);
    }

    public function saveFbMarketplaceStatus(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
        ]);

        $sku = $request->input('sku');

        $status = FBMarketplaceListingStatus::firstOrNew(['sku' => $sku]);
        $value = is_array($status->value)
            ? $status->value
            : (json_decode($status->value, true) ?: []);

        // Only update fields present in the request
        foreach (['sprice', 'nr_req', 'approved'] as $field) {
            if ($request->has($field)) {
                $value[$field] = $request->input($field);
            }
        }
        if ($request->has('SPRICE') && ! $request->has('sprice')) {
            $value['sprice'] = $request->input('SPRICE');
        }

        $status->value = $value;
        $status->save();

        return response()->json(['success' => true]);
    }

    /**
     * Save S PRC from Sprc Dil / CVR% rules (same persist shape as TikTok: sku+sprice or updates[]).
     */
    public function saveFbMarketplaceSprice(Request $request)
    {
        $updates = [];
        if ($request->has('updates')) {
            $updates = $request->input('updates', []);
        } elseif ($request->filled('sku') && $request->exists('sprice')) {
            $updates = [[
                'sku' => $request->input('sku'),
                'sprice' => $request->input('sprice'),
            ]];
        } elseif ($request->filled('sku') && $request->exists('SPRICE')) {
            $updates = [[
                'sku' => $request->input('sku'),
                'sprice' => $request->input('SPRICE'),
            ]];
        }

        if (! is_array($updates) || $updates === []) {
            return response()->json(['success' => false, 'error' => 'No S PRC updates'], 422);
        }

        $updated = 0;
        $errors = [];
        foreach ($updates as $update) {
            $sku = trim((string) ($update['sku'] ?? ''));
            if ($sku === '') {
                $errors[] = 'Missing SKU';
                continue;
            }
            if (! array_key_exists('sprice', $update) && ! array_key_exists('SPRICE', $update)) {
                $errors[] = 'Missing S PRC for '.$sku;
                continue;
            }
            $sprice = $update['sprice'] ?? $update['SPRICE'];
            $sprice = is_numeric($sprice) ? (float) round((float) $sprice) : 0;

            $status = FBMarketplaceListingStatus::firstOrNew(['sku' => $sku]);
            $value = is_array($status->value)
                ? $status->value
                : (json_decode((string) $status->value, true) ?: []);
            $value['sprice'] = $sprice;
            $status->value = $value;
            $status->save();
            $updated++;
        }

        return response()->json([
            'success' => $errors === [],
            'updated' => $updated,
            'errors' => $errors,
        ], $errors === [] ? 200 : 422);
    }

    public function importFbMarketplacePriceSold(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        try {
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathName());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Clean headers
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            unset($rows[0]);

            $allSkus = [];
            foreach ($rows as $row) {
                if (!empty($row[0])) {
                    $allSkus[] = $row[0];
                }
            }

            $existingSkus = array_flip(
                ProductMaster::whereIn('sku', $allSkus)->pluck('sku')->toArray()
            );

            $importCount = 0;
            foreach ($rows as $row) {
                if (empty($row[0])) {
                    continue;
                }

                $rowData = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
                $data = array_combine($headers, $rowData);

                if (empty($data['sku']) || !isset($existingSkus[$data['sku']])) {
                    continue;
                }

                FbMarketplacePriceSoldData::updateOrCreate(
                    ['sku' => $data['sku']],
                    [
                        'price' => isset($data['price']) && $data['price'] !== null && $data['price'] !== ''
                            ? (float) preg_replace('/[^0-9.\-]/', '', (string) $data['price'])
                            : null,
                    ]
                );

                $importCount++;
            }

            return back()->with('success', "Successfully imported $importCount price records!");
        } catch (\Exception $e) {
            return back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    public function downloadFbMarketplacePriceSoldSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['SKU', 'Price'];
        $sheet->fromArray($headers, NULL, 'A1');

        $sampleData = [
            ['SKU001', 19.99],
            ['SKU002', 24.50],
            ['SKU003', 9.99],
        ];
        $sheet->fromArray($sampleData, NULL, 'A2');

        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(12);

        $fileName = 'FbMarketplace_Price_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
