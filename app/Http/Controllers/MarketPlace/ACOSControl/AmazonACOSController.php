<?php

namespace App\Http\Controllers\MarketPlace\ACOSControl;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonSbCampaignReport;
use App\Models\AmazonSpCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AmazonACOSController extends Controller
{
    protected $profileId;

    public function __construct()
    {
        parent::__construct();
        $this->profileId = env('AMAZON_ADS_PROFILE_IDS');
    }

    public function getAccessToken()
    {
        return cache()->remember('amazon_ads_access_token', 55 * 60, function () {
            $client = new Client();

            $response = $client->post('https://api.amazon.com/auth/o2/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => env('AMAZON_ADS_REFRESH_TOKEN'),
                    'client_id' => env('AMAZON_ADS_CLIENT_ID'),
                    'client_secret' => env('AMAZON_ADS_CLIENT_SECRET'),
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['access_token'];
        });
    }

    public function updateAutoAmazonCampaignBgt(array $campaignIds, array $newBgts)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        if (empty($campaignIds) || empty($newBgts)) {
            return response()->json([
                'message' => 'Campaign IDs and new budgets are required',
                'status' => 400
            ]);
        }

        $allCampaigns = [];

        foreach ($campaignIds as $index => $campaignId) {
            $newBgt = floatval($newBgts[$index] ?? 0);

            $allCampaigns[] = [
                'campaignId' => $campaignId,
                'budget' => [
                    'budget' => $newBgt,
                    'budgetType' => 'DAILY'
                ]
            ];
        }

        if (empty($allCampaigns)) {
            return response()->json([
                'message' => 'No campaigns found to update',
                'status' => 404,
            ]);
        }

        $accessToken = $this->getAccessToken();
        $client = new Client();
        $url = 'https://advertising-api.amazon.com/sp/campaigns';
        $results = [];

        try {
            $chunks = array_chunk($allCampaigns, 100);
            foreach ($chunks as $chunk) {
                $response = $client->put($url, [
                    'headers' => [
                        'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Amazon-Advertising-API-Scope' => $this->profileId,
                        'Content-Type' => 'application/vnd.spCampaign.v3+json',
                        'Accept' => 'application/vnd.spCampaign.v3+json',
                    ],
                    'json' => [
                        'campaigns' => $chunk
                    ],
                    'timeout' => 60,
                    'connect_timeout' => 30,
                ]);

                $results[] = json_decode($response->getBody(), true);
            }
            return [
                'message' => 'BGT updated successfully',
                'data' => $results,
                'status' => 200,
            ];

        } catch (\Exception $e) {
            return [
                'message' => 'Error updating BGT',
                'error' => $e->getMessage(),
                'status' => 500,
            ];
        }
    }

    public function updateAmazonCampaignBgt(Request $request)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        $campaignIds = $request->input('campaign_ids', []);
        $newBgts = $request->input('bgts', []);

        if (empty($campaignIds) || empty($newBgts)) {
            return response()->json([
                'message' => 'Campaign IDs and new budgets are required',
                'status' => 400
            ]);
        }

        $allCampaigns = [];

        foreach ($campaignIds as $index => $campaignId) {
            $newBgt = floatval($newBgts[$index] ?? 0);

            $allCampaigns[] = [
                'campaignId' => $campaignId,
                'budget' => [
                    'budget' => $newBgt,
                    'budgetType' => 'DAILY'
                ]
            ];
        }

        if (empty($allCampaigns)) {
            return response()->json([
                'message' => 'No campaigns found to update',
                'status' => 404,
            ]);
        }

        $accessToken = $this->getAccessToken();
        $client = new Client();
        $url = 'https://advertising-api.amazon.com/sp/campaigns';
        $results = [];

        try {
            $chunks = array_chunk($allCampaigns, 100);
            foreach ($chunks as $chunk) {
                $response = $client->put($url, [
                    'headers' => [
                        'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Amazon-Advertising-API-Scope' => $this->profileId,
                        'Content-Type' => 'application/vnd.spCampaign.v3+json',
                        'Accept' => 'application/vnd.spCampaign.v3+json',
                    ],
                    'json' => [
                        'campaigns' => $chunk
                    ],
                    'timeout' => 60,
                    'connect_timeout' => 30,
                ]);

                $results[] = json_decode($response->getBody(), true);
            }
            return response()->json([
                'message' => 'Campaign budget updated successfully',
                'data' => $results,
                'status' => 200,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating campaign budgets',
                'error' => $e->getMessage(),
                'status' => 500,
            ]);
        }
    }

    public function updateAutoAmazonSbCampaignBgt(array $campaignIds, array $newBgts)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        if (empty($campaignIds) || empty($newBgts)) {
            return response()->json([
                'message' => 'Campaign IDs and new budgets are required',
                'status' => 400
            ]);
        }

        $allCampaigns = [];

        foreach ($campaignIds as $index => $campaignId) {
            $newBgt = floatval($newBgts[$index] ?? 0);

            $allCampaigns[] = [
                'campaignId' => $campaignId,
                'budget' => $newBgt,
            ];
        }

        if (empty($allCampaigns)) {
            return response()->json([
                'message' => 'No campaigns found to update',
                'status' => 404,
            ]);
        }

        $accessToken = $this->getAccessToken();
        $client = new Client();
        $url = 'https://advertising-api.amazon.com/sb/v4/campaigns';
        $results = [];

        try {
            $chunks = array_chunk($allCampaigns, 10);
            foreach ($chunks as $chunk) {
                $response = $client->put($url, [
                    'headers' => [
                        'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Amazon-Advertising-API-Scope' => $this->profileId,
                        'Content-Type' => 'application/vnd.sbcampaignresource.v4+json',
                        'Accept' => 'application/vnd.sbcampaignresource.v4+json',
                    ],
                    'json' => [
                        'campaigns' => $chunk
                    ],
                    'timeout' => 60,
                    'connect_timeout' => 30,
                ]);

                $results[] = json_decode($response->getBody(), true);
            }
            return [
                'message' => 'Campaign bgt updated successfully',
                'data' => $results,
                'status' => 200,
            ];

        } catch (\Exception $e) {
            return [
                'message' => 'Error updating campaign bgt',
                'error' => $e->getMessage(),
                'status' => 500,
            ];
        }
    }

    public function updateAmazonSbCampaignBgt(Request $request)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');
        
        $campaignIds = $request->input('campaign_ids', []);
        $newBgts = $request->input('bgts', []);

        if (empty($campaignIds) || empty($newBgts)) {
            return response()->json([
                'message' => 'Campaign IDs and new budgets are required',
                'status' => 400
            ]);
        }

        $allCampaigns = [];

        foreach ($campaignIds as $index => $campaignId) {
            $newBgt = floatval($newBgts[$index] ?? 0);

            $allCampaigns[] = [
                'campaignId' => $campaignId,
                'budget' => $newBgt,
            ];
        }

        if (empty($allCampaigns)) {
            return response()->json([
                'message' => 'No campaigns found to update',
                'status' => 404,
            ]);
        }

        $accessToken = $this->getAccessToken();
        $client = new Client();
        $url = 'https://advertising-api.amazon.com/sb/v4/campaigns';
        $results = [];

        try {
            $chunks = array_chunk($allCampaigns, 10);
            foreach ($chunks as $chunk) {
                $response = $client->put($url, [
                    'headers' => [
                        'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Amazon-Advertising-API-Scope' => $this->profileId,
                        'Content-Type' => 'application/vnd.sbcampaignresource.v4+json',
                        'Accept' => 'application/vnd.sbcampaignresource.v4+json',
                    ],
                    'json' => [
                        'campaigns' => $chunk
                    ],
                    'timeout' => 60,
                    'connect_timeout' => 30,
                ]);

                $results[] = json_decode($response->getBody(), true);
            }
            return response()->json([
                'message' => 'Campaign budget updated successfully',
                'data' => $results,
                'status' => 200,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating campaign budgets',
                'error' => $e->getMessage(),
                'status' => 500,
            ]);
        }
    }

    public function amazonAcosKwControl(){
        return view('market-places.acos-control.amazon-acos-kw-control');
    }

    public function amazonAcosKwControlData(){

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;
            $row['A_L30']  = $amazonSheet->units_ordered_l30 ?? 0;
            $row['price']  = $amazonSheet->price ?? 0;
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            $row['campaignStatus'] = $matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '');
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['spend_l30'] = $matchedCampaignL30->spend ?? 0;
            $row['ad_sales_l30'] = $matchedCampaignL30->sales30d ?? 0;
            $row['ad_sold_l30'] = $matchedCampaignL30->unitsSoldSameSku30d ?? 0;

            $sales = $matchedCampaignL30->sales30d ?? 0;
            $spend = $matchedCampaignL30->spend ?? 0;

            if ($sales > 0) {
                $row['acos_L30'] = round(($spend / $sales) * 100, 2);
            } elseif ($spend > 0) {
                $row['acos_L30'] = 100;
            } else {
                $row['acos_L30'] = 0;
            }

            $row['clicks_L30'] = $matchedCampaignL30->clicks ?? 0;

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

            if ($row['NRA'] !== 'NRA' && $row['campaignName'] !== '') {
                $result[] = (object) $row;
            }

        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function amazonAcosHlControl(){
        return view('market-places.acos-control.amazon-acos-hl-control');
    }

    public function amazonAcosHlControlData(){

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $amazonSpCampaignReportsL30 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                $expected1 = $sku;                
                $expected2 = $sku . ' HEAD';      

                return ($cleanName === $expected1 || $cleanName === $expected2);
            });

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                $expected1 = $sku;                
                $expected2 = $sku . ' HEAD';      

                return ($cleanName === $expected1 || $cleanName === $expected2);
            });


            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;
            $row['A_L30']  = $amazonSheet->units_ordered_l30 ?? 0;
            $row['price']  = $amazonSheet->price ?? 0;
            $row['campaign_id'] = $matchedCampaignL30->campaign_id ??  '';
            $row['campaignName'] = $matchedCampaignL30->campaignName ?? '';
            $row['campaignStatus'] = $matchedCampaignL30->campaignStatus ?? '';
            $row['campaignBudgetAmount'] = $matchedCampaignL30->campaignBudgetAmount ?? '';

            $costPerClick7 = ($matchedCampaignL7 && $matchedCampaignL7->clicks > 0)
                ? ($matchedCampaignL7->cost / $matchedCampaignL7->clicks)
                : 0;
                
            $row['l7_cpc'] = $costPerClick7;

            $sales = $matchedCampaignL30->sales ?? 0;
            $cost = $matchedCampaignL30->cost ?? 0;
            
            if ($sales > 0) {
                $row['acos_L30'] = round(($cost / $sales) * 100, 2);
            } elseif ($cost > 0) {
                $row['acos_L30'] = 100;
            } else {
                $row['acos_L30'] = 0;
            }

            $row['clicks_L30'] = $matchedCampaignL30->clicks ?? 0;
            $row['spend_l30']       = $matchedCampaignL30->cost ?? 0;
            $row['ad_sales_l30']    = $matchedCampaignL30->sales ?? 0;
            $row['ad_sold_l30'] = $matchedCampaignL30->unitsSold ?? 0;

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

            if ($row['NRA'] !== 'NRA' && $row['campaignName'] !== '') {
                $result[] = (object) $row;
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function amazonAcosPtControl(){
        return view('market-places.acos-control.amazon-acos-pt-control');
    }

    public function amazonAcosPtControlData(){

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL30 = $this->matchCampaign($sku, $amazonSpCampaignReportsL30);
            $matchedCampaignL7  = $this->matchCampaign($sku, $amazonSpCampaignReportsL7);
            $matchedCampaignL1  = $this->matchCampaign($sku, $amazonSpCampaignReportsL1);

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;
            $row['A_L30']  = $amazonSheet->units_ordered_l30 ?? 0;
            $row['price']  = $amazonSheet->price ?? 0;
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            $row['campaignStatus'] = $matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '');
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['spend_l30'] = $matchedCampaignL30->spend ?? 0;
            $row['ad_sales_l30'] = $matchedCampaignL30->sales30d ?? 0;
            $row['ad_sold_l30'] = $matchedCampaignL30->unitsSoldSameSku30d ?? 0;
            
            $sales = $matchedCampaignL30->sales30d ?? 0;
            $spend = $matchedCampaignL30->spend ?? 0;

            if ($sales > 0) {
                $row['acos_L30'] = round(($spend / $sales) * 100, 2);
            } elseif ($spend > 0) {
                $row['acos_L30'] = 100;
            } else {
                $row['acos_L30'] = 0;
            }

            $row['clicks_L30'] = $matchedCampaignL30->clicks ?? 0;

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

            if ($row['NRA'] !== 'NRA' && $row['campaignName'] !== '') {
                $result[] = (object) $row;
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    function matchCampaign($sku, $campaignReports) {
        $skuClean = preg_replace('/\s+/', ' ', strtoupper(trim($sku)));

        $expected1 = $skuClean . ' PT';
        $expected2 = $skuClean . ' PT.';

        return $campaignReports->first(function ($item) use ($expected1, $expected2) {
            $campaignName = preg_replace('/\s+/', ' ', strtoupper(trim($item->campaignName)));

            return in_array($campaignName, [$expected1, $expected2], true);
        });
    }

    public function toggleAmazonSpCampaignStatus(Request $request)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        $campaignId = $request->input('campaign_id');
        $status = $request->input('status'); // ENABLED or PAUSED

        if (empty($campaignId) || empty($status)) {
            return response()->json([
                'message' => 'Campaign ID and status are required',
                'status' => 400
            ]);
        }

        if (!in_array($status, ['ENABLED', 'PAUSED'])) {
            return response()->json([
                'message' => 'Status must be ENABLED or PAUSED',
                'status' => 400
            ]);
        }

        $accessToken = $this->getAccessToken();
        $client = new Client();
        $url = 'https://advertising-api.amazon.com/sp/campaigns';

        try {
            $response = $client->put($url, [
                'headers' => [
                    'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Amazon-Advertising-API-Scope' => $this->profileId,
                    'Content-Type' => 'application/vnd.spCampaign.v3+json',
                    'Accept' => 'application/vnd.spCampaign.v3+json',
                ],
                'json' => [
                    'campaigns' => [
                        [
                            'campaignId' => $campaignId,
                            'state' => $status
                        ]
                    ]
                ],
                'timeout' => 60,
                'connect_timeout' => 30,
            ]);

            $result = json_decode($response->getBody(), true);
            
            // Update database after successful API call
            try {
                AmazonSpCampaignReport::where('campaign_id', $campaignId)
                    ->update(['campaignStatus' => $status]);
            } catch (\Exception $dbError) {
                Log::warning('Error updating SP campaign status in database: ' . $dbError->getMessage());
                // Continue even if DB update fails
            }
            
            return response()->json([
                'message' => 'Campaign status updated successfully',
                'data' => $result,
                'status' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('Error toggling SP campaign status: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating campaign status',
                'error' => $e->getMessage(),
                'status' => 500,
            ]);
        }
    }

    public function toggleAmazonSbCampaignStatus(Request $request)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        $campaignId = $request->input('campaign_id');
        $status = $request->input('status'); // ENABLED or PAUSED

        if (empty($campaignId) || empty($status)) {
            return response()->json([
                'message' => 'Campaign ID and status are required',
                'status' => 400
            ]);
        }

        if (!in_array($status, ['ENABLED', 'PAUSED'])) {
            return response()->json([
                'message' => 'Status must be ENABLED or PAUSED',
                'status' => 400
            ]);
        }

        $accessToken = $this->getAccessToken();
        $client = new Client();
        $url = 'https://advertising-api.amazon.com/sb/v4/campaigns';

        try {
            $response = $client->put($url, [
                'headers' => [
                    'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Amazon-Advertising-API-Scope' => $this->profileId,
                    'Content-Type' => 'application/vnd.sbcampaignresource.v4+json',
                    'Accept' => 'application/vnd.sbcampaignresource.v4+json',
                ],
                'json' => [
                    'campaigns' => [
                        [
                            'campaignId' => $campaignId,
                            'state' => $status
                        ]
                    ]
                ],
                'timeout' => 60,
                'connect_timeout' => 30,
            ]);

            $result = json_decode($response->getBody(), true);
            
            // Update database after successful API call
            try {
                AmazonSbCampaignReport::where('campaign_id', $campaignId)
                    ->update(['campaignStatus' => $status]);
            } catch (\Exception $dbError) {
                Log::warning('Error updating SB campaign status in database: ' . $dbError->getMessage());
                // Continue even if DB update fails
            }
            
            return response()->json([
                'message' => 'Campaign status updated successfully',
                'data' => $result,
                'status' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('Error toggling SB campaign status: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating campaign status',
                'error' => $e->getMessage(),
                'status' => 500,
            ]);
        }
    }
}
