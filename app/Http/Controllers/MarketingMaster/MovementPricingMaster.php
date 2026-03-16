<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\UpdatePriceApiController;
use App\Jobs\EbayTwoPriceJob;
use App\Jobs\UpdateEbayOnePriceJob;
use App\Jobs\UpdateEbayPriceJob;
use App\Jobs\UpdateEbaySPriceJob;
use App\Jobs\UpdateEbayThreePriceJob;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonListingStatus;
use App\Models\EbayListingStatus;
use App\Models\EbayTwoListingStatus;
use App\Models\EbayThreeListingStatus;
use App\Models\ShopifyB2CListingStatus;
use App\Models\DobaListingStatus;
use App\Models\DobaDataView;
use App\Models\DobaMetric;
use App\Models\EbayMetric;
use App\Models\PricingMaster;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\MacyProduct;
use App\Models\ReverbProduct;
use App\Models\TemuProductSheet;
use App\Models\WalmartDataView;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayDataView;
use App\Models\EbayThreeDataView;
use App\Models\EbayTwoDataView;
use App\Models\MacyDataView;
use App\Models\MacysListingStatus;
use App\Models\ReverbListingStatus;
use App\Models\ReverbViewData;
use App\Models\Shopifyb2cDataView;
use App\Models\TemuDataView;
use App\Models\TemuListingStatus;
use App\Models\WalmartListingStatus;
use App\Services\AmazonSpApiService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session as FacadesSession;
use PhpParser\Node\Stmt\Else_;
use SebastianBergmann\CodeCoverage\Report\Xml\Totals;

class MovementPricingMaster extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }


    public function MovementPricingMaster(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $processedData = $this->processPricingData();

        return view('marketing-masters.movement_pricing_master', [
            'mode' => $mode,
            'demo' => $demo,
            'records' => $processedData, 
        ]);
    }

    protected function processPricingData($searchTerm = '')
    {
        $productData = ProductMaster::whereNull('deleted_at')

            ->orderBy('id', 'asc')
            ->get();

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
        $amazonData  = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy('sku');
        $amazonListingData = AmazonListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebayData    = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebayListingData = EbayListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
        $temuListingData = TemuListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebayTwoListingData = EbayTwoListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebayThreeListingData = EbayThreeListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
        $shopifyb2cListingData = Shopifyb2cListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
        $dobaListingData = DobaListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
        $macysListingStatus = MacysListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
        $reverbListingData = ReverbListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
        $walmartListingData = WalmartListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
        $dobaListingData = DobaListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');



        $dobaData    = DobaMetric::whereIn('sku', $skus)->get()->keyBy('sku');
        $pricingData = PricingMaster::whereIn('sku', $skus)->get()->keyBy('sku');
        $macyData    = MacyProduct::whereIn('sku', $skus)->get()->keyBy('sku');
        $reverbData  = ReverbProduct::whereIn('sku', $skus)->get()->keyBy('sku');
        $temuLookup  = TemuProductSheet::all()->keyBy('sku');
        $walmartLookup = WalmartDataView::all()->keyBy('sku');
        $ebay2Lookup = Ebay2Metric::all()->keyBy('sku');
        $ebay3Lookup = Ebay3Metric::all()->keyBy('sku');
        $amazonDataView = AmazonDataView::all()->keyBy('sku');
        $ebayDataView = EbayDataView::all()->keyBy('sku');
        $shopifyb2cDataView = Shopifyb2cDataView::all()->keyBy('sku');
        $dobaDataView = DobaDataView::all()->keyBy('sku');
        $temuDataView = TemuDataView::all()->keyBy('sku');
        $reverbDataView = ReverbViewData::all()->keyBy('sku');
        $macyDataView = MacyDataView::all()->keyBy('sku');





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
            $map  = (float) ($values['map'] ?? 0);
            $lp   = (float) ($values['lp'] ?? 0);
            $ship = (float) ($values['ship'] ?? 0);
            $temuship = (float) ($values['temu_ship'] ?? 0);

            $amazon  = $amazonData[$sku] ?? null;
            $ebay    = $ebayData[$sku] ?? null;
            $doba    = $dobaData[$sku] ?? null;
            $pricing = $pricingData[$sku] ?? null;
            $macy    = $macyData[$sku] ?? null;
            $reverb  = $reverbData[$sku] ?? null;
            $temu    = $temuLookup[$sku] ?? null;
            $walmart = $walmartLookup[$sku] ?? null;
            $ebay2   = $ebay2Lookup[$sku] ?? null;
            $ebay3   = $ebay3Lookup[$sku] ?? null;

            // Get Shopify data for L30 and INV
            $shopifyItem = $shopifyData[trim(strtoupper($sku))] ?? null;
            $inv = $shopifyItem ? ($shopifyItem->inv ?? 0) : 0;
            $l30 = $shopifyItem ? ($shopifyItem->quantity ?? 0) : 0;
            $shopify_l30 = $shopifyItem ? ($shopifyItem->shopify_l30 ?? 0) : 0;

            $total_views = ($amazon ? ($amazon->sessions_l30 ?? 0) : 0) +
                ($ebay ? ($ebay->views ?? 0) : 0) +
                ($ebay2 ? ($ebay2->views ?? 0) : 0) +
                ($ebay3 ? ($ebay3->views ?? 0) : 0);


            $avgCvr = $total_views > 0
                ? round(($l30 / $total_views) * 1000, 0) . ' %'
                : 'V';

            $item = (object) [


                'SKU'     => $sku,
                'Parent'  => $product->parent,
                'L30'     => $l30,
                'shopify_l30' => $shopify_l30,
                'total_views' => $total_views,
                'INV'     => $inv,
                'Dil%'    => $inv > 0 ? round(($l30 / $inv) * 100) : 0,
                //  'Dil%'    => $inv > 0 ? round(($l30 / $inv) * 1) : 0,
                'MSRP'    => $msrp,
                'MAP'     => $map,
                'LP'      => $lp,
                'SHIP'    => $ship,
                'temu_ship' => $temuship,
                'is_parent' => $isParent,
                'inv' => $shopifyData[trim(strtoupper($sku))]->inv ?? 0,
                'avgCvr' => $avgCvr,


                // Amazon
                'amz_price' => $amazon ? ($amazon->price ?? 0) : 0,
                'amz_l30'   => $amazon ? ($amazon->units_ordered_l30 ?? 0) : 0,
                'amz_l60'   => $amazon ? ($amazon->units_ordered_l60 ?? 0) : 0,
                'sessions_l30' => $amazon ? ($amazon->sessions_l30 ?? 0) : 0,
                'amz_cvr'   => $amazon ? $this->calculateCVR($amazon->units_ordered_l30 ?? 0, $amazon->sessions_l30 ?? 0) : null,
                'amz_buyer_link' => isset($amazonListingData[$sku]) ? ($amazonListingData[$sku]->value['buyer_link'] ?? null) : null,
                'amz_seller_link' => isset($amazonListingData[$sku]) ? ($amazonListingData[$sku]->value['seller_link'] ?? null) : null,

                'price_lmpa' => $amazon ? ($amazon->price_lmpa ?? 0) : 0,
                'amz_pft'   => $amazon && ($amazon->price ?? 0) > 0 ? (($amazon->price * 0.68 - $lp - $ship) / $amazon->price) : 0,
                'amz_roi'   => $amazon && $lp > 0 && ($amazon->price ?? 0) > 0 ? (($amazon->price * 0.68 - $lp - $ship) / $lp) : 0,
                'amz_req_view' => $amazon && $amazon->sessions_l30 > 0 && $amazon->units_ordered_l30 > 0
                    ? (($inv / 90) * 30) / (($amazon->units_ordered_l30 / $amazon->sessions_l30))
                    : 0,

                // eBay
                'ebay_price' => $ebay ? ($ebay->ebay_price ?? 0) : 0,
                'ebay_l30'   => $ebay ? ($ebay->ebay_l30 ?? 0) : 0,
                'ebay_l60'   => $ebay ? ($ebay->ebay_l60 ?? 0) : 0,
                'price_lmpa'   => $ebay ? ($ebay->price_lmpa ?? 0) : 0,
                'ebay_views' => $ebay ? ($ebay->views ?? 0) : 0,
                'ebay_cvr'   => $ebay ? $this->calculateCVR($ebay->ebay_l30 ?? 0, $ebay->views ?? 0) : null,
                'ebay_pft'   => $ebay && ($ebay->ebay_price ?? 0) > 0 ? (($ebay->ebay_price * 0.71 - $lp - $ship) / $ebay->ebay_price) : 0,
                'ebay_roi'   => $ebay && $lp > 0 && ($ebay->ebay_price ?? 0) > 0 ? (($ebay->ebay_price * 0.71 - $lp - $ship) / $lp) : 0,

                'ebay_req_view' => $ebay && $ebay->views > 0 && $ebay->ebay_l30 > 0
                    ? (($inv / 90) * 30) / (($ebay->ebay_l30 / $ebay->views))
                    : 0,
                'ebay_buyer_link' => isset($ebayListingData[$sku]) ? ($ebayListingData[$sku]->value['buyer_link'] ?? null) : null,
                'ebay_seller_link' => isset($ebayListingData[$sku]) ? ($ebayListingData[$sku]->value['seller_link'] ?? null) : null,
                // 'ebay_buyer_link' --- IGNORE ---

                // Doba
                'doba_price' => $doba ? ($doba->anticipated_income ?? 0) : 0,
                'doba_l30'   => $doba ? ($doba->quantity_l30 ?? 0) : 0,
                'doba_pft'   => $doba && ($doba->anticipated_income ?? 0) > 0 ? (($doba->anticipated_income * 0.95 - $lp - $ship) / $doba->anticipated_income) : 0,
                'doba_roi'   => $doba && $lp > 0 && ($doba->anticipated_income ?? 0) > 0 ? (($doba->anticipated_income * 0.95 - $lp - $ship) / $lp) : 0,
                'doba_buyer_link' => isset($dobaListingData[$sku]) ? ($dobaListingData[$sku]->value['buyer_link'] ?? null) : null,
                'doba_seller_link' => isset($dobaListingData[$sku]) ? ($dobaListingData[$sku]->value['seller_link'] ?? null) : null,


                // Macy
                'macy_price' => $macy ? ($macy->price ?? 0) : 0,
                'macy_l30'   => $macy ? ($macy->m_l30 ?? 0) : 0,
                'macy_l60'   => $macy ? ($macy->m_l60 ?? 0) : 0,
                'macy_pft'   => $macy && $macy->price > 0 ? (($macy->price * 0.77 - $lp - $ship) / $macy->price) : 0,
                'macy_roi'   => $macy && $lp > 0 && $macy->price > 0 ? (($macy->price * 0.77 - $lp - $ship) / $lp) : 0,
                'macy_buyer_link' => isset($macysListingStatus[$sku]) ? ($macysListingStatus[$sku]->value['buyer_link'] ?? null) : null,
                'macy_seller_link' => isset($macysListingStatus[$sku]) ? ($macysListingStatus[$sku]->value['seller_link'] ?? null) : null,

                // Reverb
                'reverb_price' => $reverb ? ($reverb->price ?? 0) : 0,
                'reverb_l30'   => $reverb ? ($reverb->r_l30 ?? 0) : 0,
                'reverb_l60'   => $reverb ? ($reverb->r_l60 ?? 0) : 0,
                'reverb_pft'   => $reverb && $reverb->price > 0 ? (($reverb->price * 0.77 - $lp - $ship) / $reverb->price) : 0,
                'reverb_roi'   => $reverb && $lp > 0 && $reverb->price > 0 ? (($reverb->price * 0.77 - $lp - $ship) / $lp) : 0,

                'reverb_buyer_link' => isset($reverbListingData[$sku]) ? ($reverbListingData[$sku]->value['buyer_link'] ?? null) : null,
                'reverb_seller_link' => isset($reverbListingData[$sku]) ? ($reverbListingData[$sku]->value['seller_link'] ?? null) : null,

                // Temu
                'temu_price' => $temu ? (float) ($temu->{'price'} ?? 0) : 0,
                'temu_l30'   => $temu ? (float) ($temu->{'l30'} ?? 0) : 0,
                'temu_dil'   => $temu ? (float) ($temu->{'dil'} ?? 0) : 0,
                'temu_pft'   => $temu && ($temu->price ?? 0) > 0 ? (($temu->price * 0.87 - $lp - $temuship) / $temu->price) : 0,
                'temu_roi'   => $temu && $lp > 0 && ($temu->price ?? 0) > 0 ? (($temu->price * 0.87 - $lp - $temuship) / $lp) : 0,
                'temu_buyer_link' => isset($temuListingData[$sku]) ? ($temuListingData[$sku]->value['buyer_link'] ?? null) : null,
                'temu_seller_link' => isset($temuListingData[$sku]) ? ($temuListingData[$sku]->value['seller_link'] ?? null) : null,

                // Walmart
                'walmart_price' => $walmart ? (float) ($walmart->{'walmart_price'} ?? 0) : 0,
                'walmart_l30'   => $walmart ? (float) ($walmart->{'walmart_l30'} ?? 0) : 0,
                'walmart_dil'   => $walmart ? (float) ($walmart->{'walmart_dil'} ?? 0) : 0,
                'walmart_pft'   => $walmart && ($walmart->walmart_price ?? 0) > 0 ? (($walmart->walmart_price * 0.85 - $lp - $ship) / $walmart->walmart_price) : 0,
                'walmart_roi'   => $walmart && $lp > 0 && ($walmart->walmart_price ?? 0) > 0 ? (($walmart->walmart_price * 0.85 - $lp - $ship) / $lp) : 0,
                'walmart_buyer_link' => isset($walmartListingData[$sku]) ? ($walmartListingData[$sku]->value['buyer_link'] ?? null) : null,
                'walmart_seller_link' => isset($walmartListingData[$sku]) ? ($walmartListingData[$sku]->value['seller_link'] ?? null) : null,

                // eBay2
                'ebay2_price' => $ebay2 ? ($ebay2->ebay_price ?? 0) : 0,
                'ebay2_l30'   => $ebay2 ? ($ebay2->ebay_l30 ?? 0) : 0,
                'ebay2_l60'   => $ebay2 ? ($ebay2->ebay_l60 ?? 0) : 0,
                'ebay2_views' => $ebay2 ? ($ebay2->views ?? 0) : 0,
                'ebay2_dil'   => $ebay2 ? (float) ($ebay2->{'dil'} ?? 0) : 0,
                'ebay2_pft'   => $ebay2 && ($ebay2->ebay_price ?? 0) > 0 ? (($ebay2->ebay_price * 0.80 - $lp - $ship) / $ebay2->ebay_price) : 0,
                'ebay2_roi'   => $ebay2 && $lp > 0 && ($ebay2->ebay_price ?? 0) > 0 ? (($ebay2->ebay_price * 0.80 - $lp - $ship) / $lp) : 0,
                'ebay2_req_view' => $ebay2 && $ebay2->views > 0 && $ebay2->ebay_l30 > 0
                    ? (($inv / 90) * 30) / (($ebay2->ebay_l30 / $ebay2->views))
                    : 0,


        
                'ebay2_buyer_link' => isset($ebayTwoListingData[$sku]) ? ($ebayTwoListingData[$sku]->value['buyer_link'] ?? null) : null,
                'ebay2_seller_link' => isset($ebayTwoListingData[$sku]) ? ($ebayTwoListingData[$sku]->value['seller_link'] ?? null) : null,

                // eBay3
                'ebay3_price' => $ebay3 ? ($ebay3->ebay_price ?? 0) : 0,
                'ebay3_l30'   => $ebay3 ? ($ebay3->ebay_l30 ?? 0) : 0,
                'ebay3_l60'   => $ebay3 ? ($ebay3->ebay_l60 ?? 0) : 0,
                'ebay3_views' => $ebay3 ? ($ebay3->views ?? 0) : 0,
                'ebay3_dil'   => $ebay3 ? (float) ($ebay3->{'dil'} ?? 0) : 0,
                'ebay3_cvr'   => $ebay3 ? $this->calculateCVR($ebay3->ebay_l30 ?? 0, $ebay3->views ?? 0) : null,
                'ebay3_pft'   => $ebay3 && ($ebay3->ebay_price ?? 0) > 0 ? (($ebay3->ebay_price * 0.71 - $lp - $ship) / $ebay3->ebay_price) : 0,
                'ebay3_roi'   => $ebay3 && $lp > 0 && ($ebay3->ebay_price ?? 0) > 0 ? (($ebay3->ebay_price * 0.71 - $lp - $ship) / $lp) : 0,
                'ebay3_req_view' => $ebay3 && $ebay3->views > 0 && $ebay3->ebay_l30 > 0
                    ? (($inv / 90) * 30) / (($ebay3->ebay_l30 / $ebay3->views))
                    : 0,

                'ebay3_buyer_link' => isset($ebayThreeListingData[$sku]) ? ($ebayThreeListingData[$sku]->value['buyer_link'] ?? null) : null,
                'ebay3_seller_link' => isset($ebayThreeListingData[$sku]) ? ($ebayThreeListingData[$sku]->value['seller_link'] ?? null) : null,

                'shopifyb2c_buyer_link' => isset($shopifyb2cListingData[$sku]) ? ($shopifyb2cListingData[$sku]->value['buyer_link'] ?? null) : null,
                'shopifyb2c_seller_link' => isset($shopifyb2cListingData[$sku]) ? ($shopifyb2cListingData[$sku]->value['seller_link'] ?? null) : null,

                // Total required views from all channels
                'total_req_view' => (
                    ($ebay && $ebay->views > 0 && $ebay->ebay_l30 > 0 ? (($inv / 90) * 30) / (($ebay->ebay_l30 / $ebay->views)) : 0) +
                    ($ebay2 && $ebay2->views > 0 && $ebay2->ebay_l30 > 0 ? (($inv / 90) * 30) / (($ebay2->ebay_l30 / $ebay2->views)) : 0) +
                    ($ebay3 && $ebay3->views > 0 && $ebay3->ebay_l30 > 0 ? (($inv / 90) * 30) / (($ebay3->ebay_l30 / $ebay3->views)) : 0) +
                    ($amazon && $amazon->sessions_l30 > 0 && $amazon->units_ordered_l30 > 0 ? (($inv / 90) * 30) / (($amazon->units_ordered_l30 / $amazon->sessions_l30)) : 0)
                ),

                // Amazon DataView values
                'amz_sprice' => isset($amazonDataView[$sku]) ?
                    (is_array($amazonDataView[$sku]->value) ?
                        ($amazonDataView[$sku]->value['SPRICE'] ?? null) : (json_decode($amazonDataView[$sku]->value, true)['SPRICE'] ?? null)) : null,
                'amz_spft' => isset($amazonDataView[$sku]) ?
                    (is_array($amazonDataView[$sku]->value) ?
                        ($amazonDataView[$sku]->value['SPFT'] ?? null) : (json_decode($amazonDataView[$sku]->value, true)['SPFT'] ?? null)) : null,
                'amz_sroi' => isset($amazonDataView[$sku]) ?
                    (is_array($amazonDataView[$sku]->value) ?
                        ($amazonDataView[$sku]->value['SROI'] ?? null) : (json_decode($amazonDataView[$sku]->value, true)['SROI'] ?? null)) : null,

                'shopifyb2c_sprice' => isset($shopifyb2cDataView[$sku]) ?
                    (is_array($shopifyb2cDataView[$sku]->value) ?
                        ($shopifyb2cDataView[$sku]->value['SPRICE'] ?? null) : (json_decode($shopifyb2cDataView[$sku]->value, true)['SPRICE'] ?? null)) : null,
                'shopifyb2c_spft' => isset($shopifyb2cDataView[$sku]) ?
                    (is_array($shopifyb2cDataView[$sku]->value) ?
                        ($shopifyb2cDataView[$sku]->value['SPFT'] ?? null) : (json_decode($shopifyb2cDataView[$sku]->value, true)['SPFT'] ?? null)) : null,
                'shopifyb2c_sroi' => isset($shopifyb2cDataView[$sku]) ?
                    (is_array($shopifyb2cDataView[$sku]->value) ?
                        ($shopifyb2cDataView[$sku]->value['SROI'] ?? null) : (json_decode($shopifyb2cDataView[$sku]->value, true)['SROI'] ?? null)) : null,

                // eBay DataView values
                'ebay_sprice' => isset($ebayDataView[$sku]) ?
                    (is_array($ebayDataView[$sku]->value) ?
                        ($ebayDataView[$sku]->value['SPRICE'] ?? null) : (json_decode($ebayDataView[$sku]->value, true)['SPRICE'] ?? null)) : null,
                'ebay_spft' => isset($ebayDataView[$sku]) ?
                    (is_array($ebayDataView[$sku]->value) ?
                        ($ebayDataView[$sku]->value['SPFT'] ?? null) : (json_decode($ebayDataView[$sku]->value, true)['SPFT'] ?? null)) : null,
                'ebay_sroi' => isset($ebayDataView[$sku]) ?
                    (is_array($ebayDataView[$sku]->value) ?
                        ($ebayDataView[$sku]->value['SROI'] ?? null) : (json_decode($ebayDataView[$sku]->value, true)['SROI'] ?? null)) : null,

                'ebay2_sprice' => isset($ebayDataView[$sku]) ?
                    (is_array($ebayDataView[$sku]->value) ?
                        ($ebayDataView[$sku]->value['SPRICE'] ?? null) : (json_decode($ebayDataView[$sku]->value, true)['SPRICE'] ?? null)) : null,
                'ebay2_spft' => isset($ebayDataView[$sku]) ?
                    (is_array($ebayDataView[$sku]->value) ?
                        ($ebayDataView[$sku]->value['SPFT'] ?? null) : (json_decode($ebayDataView[$sku]->value, true)['SPFT'] ?? null)) : null,
                'ebay2_sroi' => isset($ebayDataView[$sku]) ?
                    (is_array($ebayDataView[$sku]->value) ?
                        ($ebayDataView[$sku]->value['SROI'] ?? null) : (json_decode($ebayDataView[$sku]->value, true)['SROI'] ?? null)) : null,

                'ebay3_sprice' => isset($ebayDataView[$sku]) ?
                    (is_array($ebayDataView[$sku]->value) ?
                        ($ebayDataView[$sku]->value['SPRICE'] ?? null) : (json_decode($ebayDataView[$sku]->value, true)['SPRICE'] ?? null)) : null,
                'ebay3_spft' => isset($ebayDataView[$sku]) ?
                    (is_array($ebayDataView[$sku]->value) ?
                        ($ebayDataView[$sku]->value['SPFT'] ?? null) : (json_decode($ebayDataView[$sku]->value, true)['SPFT'] ?? null)) : null,
                'ebay3_sroi' => isset($ebayDataView[$sku]) ?
                    (is_array($ebayDataView[$sku]->value) ?
                        ($ebayDataView[$sku]->value['SROI'] ?? null) : (json_decode($ebayDataView[$sku]->value, true)['SROI'] ?? null)) : null,


                'doba_sprice' => isset($dobaDataView[$sku]) ?
                    (is_array($dobaDataView[$sku]->value) ?
                        ($dobaDataView[$sku]->value['SPRICE'] ?? null) : (json_decode($dobaDataView[$sku]->value, true)['SPRICE'] ?? null)) : null,
                'doba_spft' => isset($dobaDataView[$sku]) ? (is_array($dobaDataView[$sku]->value) ?
                    ($dobaDataView[$sku]->value['SPFT'] ?? null) : (json_decode($dobaDataView[$sku]->value, true)['SPFT'] ?? null)) : null,
                'doba_sroi' => isset($dobaDataView[$sku]) ?
                    (is_array($dobaDataView[$sku]->value) ?
                        ($dobaDataView[$sku]->value['SROI'] ?? null) : (json_decode($dobaDataView[$sku]->value, true)['SROI'] ?? null)) : null,


                'temu_sprice' => isset($temuDataView[$sku]) ?
                    (is_array($temuDataView[$sku]->value) ?
                        ($temuDataView[$sku]->value['SPRICE'] ?? null) : (json_decode($temuDataView[$sku]->value, true)['SPRICE'] ?? null)) : null,
                'temu_spft' => isset($temuDataView[$sku]) ? (is_array($temuDataView[$sku]->value) ?
                    ($temuDataView[$sku]->value['SPFT'] ?? null) : (json_decode($temuDataView[$sku]->value, true)['SPFT'] ?? null)) : null,
                'temu_sroi' => isset($temuDataView[$sku]) ?
                    (is_array($temuDataView[$sku]->value) ?
                        ($temuDataView[$sku]->value['SROI'] ?? null) : (json_decode($temuDataView[$sku]->value, true)['SROI'] ?? null)) : null,

                'reverb_sprice' => isset($reverbDataView[$sku]) ?
                    (is_array($reverbDataView[$sku]->value) ?
                        ($reverbDataView[$sku]->value['SPRICE'] ?? null) : (json_decode($reverbDataView[$sku]->value, true)['SPRICE'] ?? null)) : null,
                'reverb_spft' => isset($reverbDataView[$sku]) ? (is_array($reverbDataView[$sku]->value) ?
                    ($reverbDataView[$sku]->value['SPFT'] ?? null) : (json_decode($reverbDataView[$sku]->value, true)['SPFT'] ?? null)) : null,
                'reverb_sroi' => isset($reverbDataView[$sku]) ? (is_array($reverbDataView[$sku]->value) ?
                    ($reverbDataView[$sku]->value['SROI'] ?? null) : (json_decode($reverbDataView[$sku]->value, true)['SROI'] ?? null)) : null,

                'macy_sprice' => isset($macyDataView[$sku]) ?
                    (is_array($macyDataView[$sku]->value) ?
                        ($macyDataView[$sku]->value['SPRICE'] ?? null) : (json_decode($macyDataView[$sku]->value, true)['SPRICE'] ?? null)) : null,
                'macy_spft' => isset($macyDataView[$sku]) ? (is_array($macyDataView[$sku]->value) ?
                    ($macyDataView[$sku]->value['SPFT'] ?? null) : (json_decode($macyDataView[$sku]->value, true)['SPFT'] ?? null)) : null,
                'macy_sroi' => isset($macyDataView[$sku]) ?
                    (is_array($macyDataView[$sku]->value) ?
                        ($macyDataView[$sku]->value['SROI'] ?? null) : (json_decode($macyDataView[$sku]->value, true)['SROI'] ?? null)) : null,


              

            ];

            
           
     


            // Add shopifyb2c fields after $item is created
            $shopify = $shopifyData[trim(strtoupper($sku))] ?? null;
            $item->shopifyb2c_price = $shopify ? $shopify->price : 0;
            $item->shopifyb2c_l30 = $shopify ? $shopify->quantity : 0;
            $item->shopifyb2c_l30_data = $shopify ? $shopify->shopify_l30 : 0;
            $item->shopifyb2c_image = $shopify ? $shopify->image_src : null;
            $item->shopifyb2c_pft = $item->shopifyb2c_price > 0 ? (($item->shopifyb2c_price * 0.75 - $lp - $ship) / $item->shopifyb2c_price) : 0;
            $item->shopifyb2c_roi = ($lp > 0 && $item->shopifyb2c_price > 0) ? (($item->shopifyb2c_price * 0.75 - $lp - $ship) / $lp) : 0;

          
            // Add analysis action buttons
            $item->l30_analysis = '<button class="btn btn-sm btn-info" onclick="showL30Modal(this)" data-sku="' . $item->SKU . '">L30</button>';


            $processedData[] = $item;
        }

        return $processedData;
    }

    public function getViewPricingAnalysisData(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 'all');
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


    protected function applyFilters($data, $dilFilter, $dataType, $parentFilter, $skuFilter)
    {
        return array_filter($data, function ($item) use ($dilFilter, $dataType, $parentFilter, $skuFilter) {
            if ($dilFilter !== 'all') {
                $dilPercent = ($item->{'Dil%'} ?? 0) * 100;
                switch ($dilFilter) {
                    case 'yellow':
                        if ($dilPercent >= 16.66) {
                            return false;
                        }
                        break;
                    case 'yellow':
                        if ($dilPercent < 0 || $dilPercent >= 0) {
                            return false;
                        }
                        break;
                    case 'green':
                        if ($dilPercent < 0 || $dilPercent >= 0) {
                            return false;
                        }
                        break;
                    case 'blue':
                        if ($dilPercent < 0) {
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


    protected function calculateCVR($l30, $views)
    {
        if (!$views) return null;
        $cvr = ($l30 / $views) * 100;
        return [
            'value' => number_format($cvr, 2),
            'color' => $cvr <= 7 ? 'blue' : ($cvr <= 13 ? 'green' : 'red')
        ];
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


    public function updatePrice(Request $request)
    {
        $sku = $request["sku"];
        $price = $request["price"];

        $price = app(AmazonSpApiService::class)->updateAmazonPriceUS($sku, $price);

        return response()->json(['status' => 200, 'data' => $price]);
    }

    public function saveSprice(Request $request)
    {
        $data = $request->validate([
            'sku' => 'required|string',
            'type' => 'required|string',
            'sprice' => 'required|numeric',
            'LP' => 'required|numeric',    // cost price
            'SHIP' => 'required|numeric',
            'temu_ship' => 'required|numeric',  // Temu shipping cost
        ]);

        $sku = $data['sku'];
        $type = $data['type'];
        $sprice = $data['sprice'];
        $lp = $data['LP'];
        $ship = $data['SHIP'];
        $temuship = $data['temu_ship'];

        switch ($type) {
            case 'amz':
                // Amazon logic
                $amazonDataView = AmazonDataView::firstOrNew(['sku' => $sku]);
                $existing = is_array($amazonDataView->value) ? $amazonDataView->value : (json_decode($amazonDataView->value, true) ?: []);

                $spft = $sprice > 0 ? ((($sprice * 0.68) - $lp - $ship) / $sprice) * 100 : 0;
                $sroi = $lp > 0 ? ((($sprice * 0.68) - $lp - $ship) / $lp) * 100 : 0;
                $existing['SPRICE'] = number_format($sprice, 2, '.', '');
                $existing['SPFT'] = number_format($spft, 2, '.', '');
                $existing['SROI'] = number_format($sroi, 2, '.', '');


                $amazonDataView->value = $existing;
                $amazonDataView->save();
                break;

            case 'ebay':
                // eBay logic
                $ebayDataView = EbayDataView::firstOrNew(['sku' => $sku]);
                $existing = is_array($ebayDataView->value) ? $ebayDataView->value : (json_decode($ebayDataView->value, true) ?: []);

                $spft = $sprice > 0 ? round(((($sprice * 0.71) - $lp - $ship) / $sprice) * 100, 2) : 0;
                $sroi = $lp > 0 ? ((($sprice * 0.71) - $lp - $ship) / $lp) * 100 : 0;

                // Round and store as string
                $existing['SPRICE'] = number_format($sprice, 2, '.', '');
                $existing['SPFT'] = number_format($spft, 2, '.', '');
                $existing['SROI'] = number_format($sroi, 2, '.', '');



                $ebayDataView->value = $existing;
                $ebayDataView->save();
                break;


            case 'shopifyb2c':
                try {
                    $shopifyDataView = Shopifyb2cDataView::firstOrNew(['sku' => $sku]);
                    $existing = is_array($shopifyDataView->value) ? $shopifyDataView->value : (json_decode($shopifyDataView->value, true) ?: []);

                    // Calculate values
                    $spft = $sprice > 0 ? ((($sprice * 0.75) - $lp - $ship) / $sprice) * 100 : 0;
                    $sroi = $lp > 0 ? ((($sprice * 0.75) - $lp - $ship) / $lp) * 100 : 0;

                    // Format and store values
                    $existing['SPRICE'] = number_format($sprice, 2, '.', '');
                    $existing['SPFT'] = number_format($spft, 2, '.', '');
                    $existing['SROI'] = number_format($sroi, 2, '.', '');

                    // Convert to JSON if needed
                    $shopifyDataView->value = json_encode($existing);

                    // Save with error logging
                    if (!$shopifyDataView->save()) {
                        Log::error("Failed to save ShopifyB2C data for SKU: $sku");
                        throw new \Exception("Save failed");
                    }

                    // Update Shopify price
                    $request = new Request();
                    $request->merge(['sku' => $sku, 'price' => $sprice]);
                    $this->pushShopifyPriceBySku($request);
                } catch (\Exception $e) {
                    Log::error("Error saving ShopifyB2C price: " . $e->getMessage());
                    return response()->json([
                        'message' => 'Error saving ShopifyB2C price',
                        'error' => $e->getMessage(),
                        'status' => 500
                    ]);
                }
                break;


            case 'ebay2':
                // eBay2 logic
                $ebay2DataView = EbayTwoDataView::firstOrNew(['sku' => $sku]);
                $existing = is_array($ebay2DataView->value) ? $ebay2DataView->value : (json_decode($ebay2DataView->value, true) ?: []);

                $spft = $sprice > 0 ? round(((($sprice * 0.80) - $lp - $ship) / $sprice) * 100, 2) : 0;
                $sroi = $lp > 0 ? ((($sprice * 0.80) - $lp - $ship) / $lp) * 100 : 0;

                $existing['SPRICE'] = number_format($sprice, 2, '.', '');
                $existing['SPFT'] = number_format($spft, 2, '.', '');
                $existing['SROI'] = number_format($sroi, 2, '.', '');

                $ebay2DataView->value = $existing;
                $ebay2DataView->save();
                break;


            case 'ebay3':
                // eBay3 logic
                $ebay3DataView = EbayThreeDataView::firstOrNew(['sku' => $sku]);
                $existing = is_array($ebay3DataView->value) ? $ebay3DataView->value : (json_decode($ebay3DataView->value, true) ?: []);

                $spft = $sprice > 0 ? round(((($sprice * 0.71) - $lp - $ship) / $sprice) * 100, 2) : 0;
                $sroi = $lp > 0 ? ((($sprice * 0.71) - $lp - $ship) / $lp) * 100 : 0;

                $existing['SPRICE'] = number_format($sprice, 2, '.', '');
                $existing['SPFT'] = number_format($spft, 2, '.', '');
                $existing['SROI'] = number_format($sroi, 2, '.', '');

                $ebay3DataView->value = $existing;
                $ebay3DataView->save();
                break;

            case 'doba':
                // Doba logic
                $dobaDataView = DobaDataView::firstOrNew(['sku' => $sku]);
                $existing = is_array($dobaDataView->value) ? $dobaDataView->value : (json_decode($dobaDataView->value, true) ?: []);

                $spft = $sprice > 0 ? round(((($sprice * 0.95) - $lp - $ship) / $sprice) * 100, 2) : 0;
                $sroi = $lp > 0 ? ((($sprice * 0.95) - $lp - $ship) / $lp) * 100 : 0;

                $existing['SPRICE'] = number_format($sprice, 2, '.', '');
                $existing['SPFT'] = number_format($spft, 2, '.', '');
                $existing['SROI'] = number_format($sroi, 2, '.', '');

                $dobaDataView->value = $existing;
                $dobaDataView->save();
                break;

            case 'temu':
                // Temu logic
                $temuDataView = TemuDataView::firstOrNew(['sku' => $sku]);
                $existing = is_array($temuDataView->value) ? $temuDataView->value : (json_decode($temuDataView->value, true) ?: []);

                $spft = $sprice > 0 ? round(((($sprice * 0.87) - $lp - $temuship) / $sprice) * 100, 2) : 0;
                $sroi = $lp > 0 ? ((($sprice * 0.87) - $lp - $temuship) / $lp) * 100 : 0;

                $existing['SPRICE'] = number_format($sprice, 2, '.', '');
                $existing['SPFT'] = number_format($spft, 2, '.', '');
                $existing['SROI'] = number_format($sroi, 2, '.', '');

                $temuDataView->value = $existing;
                $temuDataView->save();
                break;


            case 'reverb':
                // Reverb logic
                $reverbDataView = ReverbViewData::firstOrNew(['sku' => $sku]);
                $existing = is_array($reverbDataView->values) ? $reverbDataView->values : (json_decode($reverbDataView->values, true) ?: []);

                $spft = $sprice > 0 ? round(((($sprice * 0.84) - $lp - $ship) / $sprice) * 100, 2) : 0;
                $sroi = $lp > 0 ? ((($sprice * 0.84) - $lp - $ship) / $lp) * 100 : 0;


                $existing['SPRICE'] = number_format($sprice, 2, '.', '');
                $existing['SPFT'] = number_format($spft, 2, '.', '');
                $existing['SROI'] = number_format($sroi, 2, '.', '');

                $reverbDataView->values = $existing;
                $reverbDataView->save();
                break;


            case 'macy':
                // Macy logic
                $macyDataView = MacyDataView::firstOrNew(['sku' => $sku]);
                $existing = is_array($macyDataView->value) ? $macyDataView->value : (json_decode($macyDataView->value, true) ?: []);
                $spft = $sprice > 0 ? round(((($sprice * 0.76) - $lp - $ship) / $sprice) * 100, 2) : 0;
                $sroi = $lp > 0 ? ((($sprice * 0.76) - $lp - $ship) / $lp) * 100 : 0;


                $existing['SPRICE'] = number_format($sprice, 2, '.', '');
                $existing['SPFT'] = number_format($spft, 2, '.', '');
                $existing['SROI'] = number_format($sroi, 2, '.', '');

                $macyDataView->value = $existing;
                $macyDataView->save();
                break;


            default:
                return response()->json([
                    'message' => 'Unknown marketplace type',
                    'status' => 400
                ]);
        }

        return response()->json([
            'message' => "$type S Price, SPFT & SROI saved successfully",
            'data' => [
                'SPRICE' => $sprice,
                'SPFT' => $spft,
                'SROI' => $sroi
            ],
            'status' => 200
        ]);
    }


    public function pushShopifyPriceBySku(Request $request)
    {

        $sku = $request->input('sku');
        $price = $request->input('price');

        $variantId = ShopifySku::where('sku', $sku)->value('variant_id');

        if (!$variantId) {
            return response()->json([
                'status' => 'error',
                'message' => "Variant ID not found for SKU: {$sku}"
            ], 404);
        }

        $result = UpdatePriceApiController::updateShopifyVariantPrice($variantId, $price);

        if ($result['status'] === 'success') {
            return response()->json([
                'status' => 'success',
                'message' => "Price updated successfully for SKU {$sku}",
                'data' => $result['data']
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'] ?? 'Unknown error occurred',
                'code' => $result['code'] ?? 500,
            ], 500);
        }
    }

    public function pushEbayPriceBySku(Request $request)
    {
        $sku = $request->input('sku');
        $price = $request->input('price');

        $itemId = EbayMetric::where('sku', $sku)->value('item_id');
        UpdateEbayOnePriceJob::dispatch($itemId, $price);
    }

    public function pushEbayTwoPriceBySku(Request $request)
    {
        $sku = $request->input('sku');
        $price = $request->input('price');

        $itemId = Ebay2Metric::where('sku', $sku)->value('item_id');
        EbayTwoPriceJob::dispatch($itemId, $price);
    }

    public function pushEbayThreePriceBySku(Request $request)
    {
        $sku = $request->input('sku');
        $price = $request->input('price');

        $itemId = Ebay3Metric::where('sku', $sku)->value('item_id');
        UpdateEbayThreePriceJob::dispatch($itemId, $price);
    }
}
