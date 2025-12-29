<?php

namespace App\Http\Controllers\PricingMaster;

use App\Models\ShopifySku;
use App\Models\EbayMetric;
use App\Models\ProductMaster;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ApiController;
use App\Models\AmazonDatasheet;
use App\Models\MacyProduct;
use App\Models\ReverbProduct;
use Illuminate\Support\Facades\Log;
use App\Models\PricingMaster;
use App\Models\DobaMetric;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\TemuProductSheet;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Models\NeweeggProductSheet;
use App\Models\WaifairProductSheet;
use App\Models\WalmartProductSheet;
use App\Models\EbayTwoProductSheet;
use App\Models\EbayThreeProductSheet;

class PricingMasterController extends Controller
{
    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function pricingMaster(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        $records = PricingMaster::orderBy('updated_at', 'desc')->get();

       

        return view('pricing-master.pricing_master', [
            'mode' => $mode,
            'demo' => $demo,
            'records' => $records,
        ]);
    }


    public function save(Request $request)
    {
        \Log::info('🚀 Save request received', $request->all());

        $validated = $request->validate([
            'sku' => 'required|string',
            'sprice' => 'required|numeric',
            'sprofit_percent' => 'nullable|numeric',
            'sroi_percent' => 'nullable|numeric',
        ]);

        \Log::info('✅ Validation passed', $validated);

        try {
            $pricing = PricingMaster::updateOrCreate(
                ['sku' => $validated['sku']],
                [
                    'sprice' => $validated['sprice'],
                    'sprofit_percent' => $validated['sprofit_percent'],
                    'sroi_percent' => $validated['sroi_percent'],
                ]
            );

            \Log::info('💾 Pricing saved', ['data' => $pricing]);

            // ✅ Return JSON for AJAX
            return response()->json([
                'message' => 'Pricing saved successfully ✅',
                'status' => 200,
                'data' => $pricing,
            ]);
        } catch (\Exception $e) {
            \Log::error('❌ Error saving pricing', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to save pricing ❌',
                'status' => 500,
            ], 500);
        }
    }


    public function getViewPricingAnalysisData(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 25);
        $dilFilter = $request->input('dil_filter', 'all');
        $dataType = $request->input('data_type', 'all');
        $searchTerm = $request->input('search', '');
        $parentFilter = $request->input('parent', '');
        $skuFilter = $request->input('sku', '');
        $distinctOnly = $request->input('distinct_only', false);

        if ($perPage === 'all') {
            $perPage = 1000000;
        } else {
            $perPage = (int) $perPage;
        }

        $processedData = $this->processPricingData($searchTerm);

        $filteredData = $this->applyFilters($processedData, $dilFilter, $dataType, $parentFilter, $skuFilter);

        if ($distinctOnly) {
            return response()->json([
                'distinct_values' => $this->getDistinctValues($processedData),
                'status' => 200,
            ]);
        }

        $total = count($filteredData);
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedData = array_slice($filteredData, $offset, $perPage);


        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $paginatedData,
            'distinct_values' => $this->getDistinctValues($processedData),
            'pagination' => [
                'current_page' => (int) $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'status' => 200,
        ]);
    }


    protected function processPricingData($searchTerm = '')
    {
        $productData = ProductMaster::whereNull('deleted_at')->get();

        $skus = $productData
            ->pluck('sku')
            ->filter(function ($sku) {
                return stripos($sku, 'PARENT') === false;
            })
            ->unique()
            ->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return trim(strtoupper($item->sku));
        });
        $amazonData = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebayData = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku');
        $macyData = MacyProduct::whereIn('sku', $skus)->get()->keyBy('sku');
        $reverbData = ReverbProduct::whereIn('sku', $skus)->get()->keyBy('sku');
        $dobaData = DobaMetric::whereIn('sku', $skus)->get()->keyBy('sku');
        $pricingData = PricingMaster::whereIn('sku', $skus)->get()->keyBy('sku');
        $temuLookup = TemuProductSheet::all()->keyBy('sku');
        $neweggLookup = NeweeggProductSheet::all()->keyBy('sku');
        $wayfairLookup = WaifairProductSheet::all()->keyBy('sku');
        $walmartLookup = WalmartProductSheet::all()->keyBy('sku');
        $ebay2Lookup = Ebay2Metric::all()->keyBy('sku');
        $ebay3Lookup = Ebay3Metric::all()->keyBy('sku');





        $processedData = [];

        foreach ($productData as $product) {
            $sku = $product->sku;

            if (!empty($searchTerm) && stripos($sku, $searchTerm) === false && stripos($product->parent, $searchTerm) === false) {
                continue;
            }

            $isParent = stripos($sku, 'PARENT') !== false;
            $values = is_string($product->Values) ? json_decode($product->Values, true) : $product->Values;
            if (!is_array($values)) {
                $values = [];
            }

            $msrp = (float) ($values['msrp'] ?? 0);
            $map = (float) ($values['map'] ?? 0);  // Here's the MAP processing
            $lp = (float) ($values['lp'] ?? 0);
            $ship = (float) ($values['ship'] ?? 0);

            $amazon = $amazonData[$sku] ?? null;
            $ebay = $ebayData[$sku] ?? null;
            $macy = $macyData[$sku] ?? null;
            $reverb = $reverbData[$sku] ?? null;
            $doba = $dobaData[$sku] ?? null;
            $newegg = $neweggLookup[$sku] ?? null;
            $temu = $temuLookup[$sku] ?? null;
            $wayfair = $wayfairLookup[$sku] ?? null;
            $ebay3 = $ebay3Lookup[$sku] ?? null;
            $ebay2 = $ebay2Lookup[$sku] ?? null;


            $item = (object) [
                'SKU' => $sku,
                'Parent' => $product->parent,
                'Values' => $product->Values,
                'is_parent' => $isParent,
                'MSRP' => $msrp,
                 'MAP' => $map,
                'LP' => $lp,
                'SHIP' => $ship,
            ];

            $pricing = $pricingData[$sku] ?? null;
            $item->sprice = $pricing->sprice ?? null;
            $item->sprofit_percent = $pricing->sprofit_percent ?? null;
            $item->sroi_percent = $pricing->sroi_percent ?? null;

            if (!$isParent) {
                $skuKey = trim(strtoupper($sku));
                $shopify = $shopifyData[$skuKey] ?? null;

                $item->INV = $shopify->inv ?? 0;
                $item->L30 = $shopify->quantity ?? 0;
                $inv = $item->INV;
                $l30 = $item->L30;
                $item->{'Dil%'} = $inv > 0 ? round($l30 / $inv, 2) : 0;

                // Amazon
                $item->amz_price = $amazon->price ?? 0;
                $item->amz_l30 = $amazon->units_ordered_l30 ?? 0;
                $item->amz_l60 = $amazon->units_ordered_l60 ?? 0;
                $item->amz_pft = $item->amz_price > 0 ? (($item->amz_price * 0.71 - $lp - $ship) / $item->amz_price) : 0;
                $item->amz_roi = ($lp > 0 && $item->amz_price > 0) ? (($item->amz_price * 0.71 - $lp - $ship) / $lp) : 0;

                // eBay
                $item->ebay_price = $ebay->ebay_price ?? 0;
                $item->ebay_l30 = $ebay->ebay_l30 ?? 0;
                $item->ebay_pft = $item->ebay_price > 0 ? (($item->ebay_price * 0.85 - $lp - $ship) / $item->ebay_price) : 0;
                $item->ebay_roi = ($lp > 0 && $item->ebay_price > 0) ? (($item->ebay_price * 0.85 - $lp - $ship) / $lp) : 0;

                // Macy
                $item->macy_price = $macy->price ?? 0;
                $item->macy_l30 = $macy->m_l30 ?? 0;
                $item->macy_pft = $item->macy_price > 0 ? (($item->macy_price * 0.77 - $lp - $ship) / $item->macy_price) : 0;
                $item->macy_roi = ($lp > 0 && $item->macy_price > 0) ? (($item->macy_price * 0.77 - $lp - $ship) / $lp) : 0;
                // Reverb

                $reverb = $reverbData[$sku] ?? null;
                $item->reverb_price = $reverb->price ?? 0;
                $item->reverb_l30 = $reverb->r_l30 ?? 0;
                $item->reverb_l60 = $reverb->r_l60 ?? 0;
                $item->reverb_pft = $item->reverb_price > 0 ? (($item->reverb_price * 0.77 - $lp - $ship) / $item->reverb_price) : 0;
                $item->reverb_roi = ($lp > 0 && $item->reverb_price > 0) ? (($item->reverb_price * 0.77 - $lp - $ship) / $lp) : 0;

                // Doba
                $doba = $dobaData[$sku] ?? null;
                $item->doba_price = $doba->anticipated_income ?? 0;
                $item->doba_l30 = $doba->quantity_l30 ?? 0;
                $item->doba_l60 = $doba->quantity_l60 ?? 0;
                $item->doba_pft = $item->doba_price > 0 ? (($item->doba_price * 0.95 - $lp - $ship) / $item->doba_price) : 0;
                $item->doba_roi = ($lp > 0 && $item->doba_price > 0) ? (($item->doba_price * 0.95 - $lp - $ship) / $lp) : 0;


                // Shopify B2C
                $item->shopifyb2c_price = $shopify->price ?? 0;
                $item->shopifyb2c_l30 = $shopify->shopify_l30 ?? 0;
                $item->shopifyb2c_image = $shopify->image_src ?? null;
                $item->shopifyb2c_pft = $item->shopifyb2c_price > 0 ? (($item->shopifyb2c_price * 0.75 - $lp - $ship) / $item->shopifyb2c_price) : 0;
                $item->shopifyb2c_roi = ($lp > 0 && $item->shopifyb2c_price > 0) ? (($item->shopifyb2c_price * 0.75 - $lp - $ship) / $lp) : 0;




                // ✅ Newegg from Sheet
                $newegg = $neweggLookup[$sku] ?? null;
                if ($newegg) {
                    $item->neweegb2c_price = (float) ($newegg->{'price'} ?? 0);
                    $item->neweegb2c_pft = (float) ($newegg->{'pft'} ?? 0);
                    $item->neweegb2c_roi = (float) ($newegg->{'roi'} ?? 0);
                    $item->neweegb2c_l30 = (float) ($newegg->{'l30'} ?? 0);
                    $item->neweegb2c_dil = (float) ($newegg->{'dil'} ?? 0);
                    // $item->neweegb2c_buy_link = $newegg->{'Buyer Link'} ?? '';
                }

                // ✅ Temu from Sheet
                $temu = $temuLookup[$sku] ?? null;
                if ($temu) {
                    $item->temu_price = (float) ($temu->{'price'} ?? 0);
                    $item->temu_pft = (float) ($temu->{'pft'} ?? 0);
                    $item->temu_roi = (float) ($temu->{'roi'} ?? 0);
                    $item->temu_l30 = (float) ($temu->{'l30'} ?? 0);
                    $item->temu_dil = (float) ($temu->{'dil'} ?? 0);
                    // $item->temu_buy_link = $temu->{'Buyer Link'} ?? '';
                }

                $wayfair = $wayfairLookup[$sku] ?? null;
                if ($wayfair) {
                    $item->wayfair_price = (float) ($wayfair->{'price'} ?? 0);
                    $item->wayfair_pft = (float) ($wayfair->{'pft'} ?? 0);
                    $item->wayfair_roi = (float) ($wayfair->{'roi'} ?? 0);
                    $item->wayfair_l30 = (float) ($wayfair->{'l30'} ?? 0);
                    $item->wayfair_dil = (float) ($wayfair->{'dil'} ?? 0);
                    // $item->wayfair_buy_link = $wayfair->{'Buyer Link'} ?? '';
                }

                // ✅ Ebay3 from Sheet
                $ebay3 = $ebay3Lookup[$sku] ?? null;
                if ($ebay3) {
                    $item->ebay3_price = (float) ($ebay3->{'price'} ?? 0);
                    $item->ebay3_pft = (float) ($ebay3->{'pft'} ?? 0);
                    $item->ebay3_roi = (float) ($ebay3->{'roi'} ?? 0);
                    $item->ebay3_l30 = (float) ($ebay3->{'l30'} ?? 0);
                    $item->ebay3_dil = (float) ($ebay3->{'dil'} ?? 0);
                    // $item->ebay3_buy_link = $ebay3->{'buyer_link'} ?? '';
                }


                $ebay2 = $ebay2Lookup[$sku] ?? null;
                if ($ebay2) {
                    $item->ebay2_price = (float) ($ebay2->{'price'} ?? 0);
                    $item->ebay2_l30 = (float) ($ebay2->{'l30'} ?? 0);
                    $item->ebay2_pft = (float) ($ebay2->{'pft'} ?? 0);
                    $item->ebay2_roi = (float) ($ebay2->{'roi'} ?? 0);
                    $item->ebay2_dil = (float) ($ebay2->{'dil'} ?? 0);
                    // $item->ebay2_buy_link = $ebay2->{'buyer_link'} ?? '';
                }

                // ✅ Walmart from Sheet
                $walmart = $walmartLookup[$sku] ?? null;
                if ($walmart) {
                    $item->walmart_price = (float) ($walmart->{'price'} ?? 0);
                    $item->walmart_pft = (float) ($walmart->{'pft'} ?? 0);
                    $item->walmart_roi = (float) ($walmart->{'roi'} ?? 0);
                    $item->walmart_l30 = (float) ($walmart->{'l30'} ?? 0);
                    $item->walmart_dil = (float) ($walmart->{'dil'} ?? 0);
                    // $item->walmart_buy_link = $walmart->{'buyer_link'} ?? '';
                }


                $pricing = $pricingData[$sku] ?? null;
                $item->sprice = $pricing->sprice ?? null;
                $item->sprofit_percent = $pricing->sprofit_percent ?? null;
                $item->sroi_percent = $pricing->sroi_percent ?? null;
            }


            $processedData[] = $item;
        }

        // 🟢 Group by parent and calculate total INV/L30
        $groupedByParent = [];

        foreach ($processedData as $item) {
            if (!$item->is_parent) {
                $groupedByParent[$item->Parent][] = $item;
            }
        }

        foreach ($processedData as $item) {
            if ($item->is_parent && isset($groupedByParent[$item->Parent])) {
                $children = $groupedByParent[$item->Parent];
                $invTotal = 0;
                $l30Total = 0;

                foreach ($children as $child) {
                    $invTotal += $child->INV ?? 0;
                    $l30Total += $child->L30 ?? 0;
                }

                $item->INV = $invTotal;
                $item->L30 = $l30Total;
                $item->{'Dil%'} = $invTotal > 0 ? round($l30Total / $invTotal, 2) : 0;
            }
        }

        return $processedData;
    }

    protected function getDistinctValues($data)
    {
        $parents = [];
        $skus = [];

        foreach ($data as $item) {
            if (!empty($item->Parent)) {
                $parents[$item->Parent] = true;
            }
            if (!empty($item->SKU)) {
                $skus[$item->SKU] = true;
            }
        }

        return [
            'parents' => array_keys($parents),
            'skus' => array_keys($skus),
        ];
    }

    protected function applyFilters($data, $dilFilter, $dataType, $parentFilter, $skuFilter)
    {
        return array_filter($data, function ($item) use ($dilFilter, $dataType, $parentFilter, $skuFilter) {
            if ($dilFilter !== 'all') {
                $dilPercent = ($item->{'Dil%'} ?? 0) * 100;
                switch ($dilFilter) {
                    case 'red':
                        if ($dilPercent >= 16.66) {
                            return false;
                        }
                        break;
                    case 'yellow':
                        if ($dilPercent < 16.66 || $dilPercent >= 25) {
                            return false;
                        }
                        break;
                    case 'green':
                        if ($dilPercent < 25 || $dilPercent >= 50) {
                            return false;
                        }
                        break;
                    case 'pink':
                        if ($dilPercent < 50) {
                            return false;
                        }
                        break;
                }
            }

            if ($dataType !== 'all') {
                $isParent = stripos($item->SKU ?? '', 'PARENT') !== false;
                if ($dataType === 'parent' && !$isParent) {
                    return false;
                }
                if ($dataType === 'sku' && $isParent) {
                    return false;
                }
            }

            if ($parentFilter && $item->Parent !== $parentFilter) {
                return false;
            }
            if ($skuFilter && $item->SKU !== $skuFilter) {
                return false;
            }

            return true;
        });
    }
}
