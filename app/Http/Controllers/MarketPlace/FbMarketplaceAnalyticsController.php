<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MarketplacePercentage;
use App\Models\FbMarketplacePriceSoldData;
use App\Models\FBMarketplaceListingStatus;
use App\Models\FbMarketplaceSheetdata;
use App\Models\FacebookMarketplaceSale;
use App\Models\ProductMaster;
use App\Models\AmazonDataView;
use App\Models\ShopifySku;
use App\Services\ChannelPromoPricingService;
use Illuminate\Support\Facades\DB;
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

        // Manual price upload overrides (may be empty — sheet is the primary source)
        $priceSoldData = FbMarketplacePriceSoldData::whereIn('sku', $skus)->get()->keyBy('sku');

        // Primary price / L30 sold from synced sheet (same source as Ads / Channel Master)
        $sheetBySku = FbMarketplaceSheetdata::all()->keyBy(function ($row) {
            return strtoupper(trim((string) $row->sku));
        });

        // Fetch listing statuses (sprice / nr_req / approved / links) keyed by SKU
        $listingStatusData = FBMarketplaceListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        // Order-upload fallback for sold qty (/facebook-marketplace)
        $fbSalesBySku = FacebookMarketplaceSale::query()
            ->select('sku', DB::raw('SUM(qty_sold) as total_sold'))
            ->groupBy('sku')
            ->pluck('total_sold', 'sku')
            ->mapWithKeys(function ($total, $sku) {
                return [strtoupper(trim((string) $sku)) => (int) $total];
            });

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
            $priceSold = $priceSoldData[$sku] ?? null;
            $skuKey = strtoupper(trim((string) $sku));
            $sheet = $sheetBySku[$skuKey] ?? null;

            // Buyer/Seller links & saved fields from listing status
            $statusValue = $listingStatusData[$sku]->value ?? [];
            if (is_string($statusValue)) {
                $statusValue = json_decode($statusValue, true) ?: [];
            }

            // Price: uploaded override → sheet price (channel master source)
            $price = (float) ($priceSold->price ?? $sheet->price ?? 0);
            // L30 sold: sheet l30 → uploaded sold → order-upload qty
            $soldL30 = (int) ($sheet->l30 ?? 0);
            if ($soldL30 <= 0 && $priceSold && isset($priceSold->sold)) {
                $soldL30 = (int) $priceSold->sold;
            }
            if ($soldL30 <= 0) {
                $soldL30 = (int) ($fbSalesBySku[$skuKey] ?? 0);
            }
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

            $row = [
                'Parent' => $productMaster->parent ?? null,
                'image_path' => $shopifyItem->image_src ?? ($values['image_path'] ?? null),
                'sku' => $sku,
                'INV' => $shopifyItem->inv ?? 0,
                'L30' => $shopifyItem->quantity ?? 0,
                'price' => $price,
                'sold' => $soldL30,
                'PFT' => round($pft, 2),
                'ROI' => round($roi, 2),
                'sprice' => $sprice,
                'SPFT' => round($spft, 2),
                'SROI' => round($sroi, 2),
                'nr_req' => $nrReq,
                'lp' => $lp,
                'ship' => $ship,
                'factor' => $factor,
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

        $status->value = $value;
        $status->save();

        return response()->json(['success' => true]);
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
