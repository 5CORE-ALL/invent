<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonSbCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AmazonSbBudgetController extends Controller
{
    protected $profileId;

    /** HL (Sponsored Brands) API is slower; use higher timeouts and batching */
    public const HL_API_TIMEOUT = 120;
    public const HL_CONNECT_TIMEOUT = 60;
    public const HL_MAX_RETRIES = 3;
    public const HL_BATCH_SIZE = 5;
    public const HL_KEYWORD_CHUNK_SIZE = 50;
    public const HL_BATCH_DELAY_SECONDS = 2;

    public function __construct()
    {
        $this->profileId = config('services.amazon_ads.profile_ids');
    }

    public function getAccessToken()
    {
        return cache()->remember('amazon_ads_access_token', 55 * 60, function () {
            $client = new Client();

            $response = $client->post('https://api.amazon.com/auth/o2/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => config('services.amazon_ads.refresh_token'),
                    'client_id' => config('services.amazon_ads.client_id'),
                    'client_secret' => config('services.amazon_ads.client_secret'),
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['access_token'];
        });
    }

    public function getAdGroupsByCampaigns(array $campaignIds)
    {
        $accessToken = $this->getAccessToken();
        $client = new Client();

        // Normalize campaign IDs to strings (Amazon API requires strings, not numbers)
        $normalizedCampaignIds = array_filter(
            array_map(function($id) {
                $strId = trim((string)$id);
                return !empty($strId) ? $strId : null;
            }, $campaignIds),
            function($id) { return $id !== null; }
        );

        if (empty($normalizedCampaignIds)) {
            return [];
        }

        $url = 'https://advertising-api.amazon.com/sb/v4/adGroups/list';
        $payload = [
            'campaignIdFilter' => ['include' => array_values($normalizedCampaignIds)],
            'stateFilter' => ['include' => ['ENABLED']],
        ];

        $response = $client->post($url, [
            'headers' => [
                'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                'Authorization' => 'Bearer ' . $accessToken,
                'Amazon-Advertising-API-Scope' => $this->profileId,
                'Content-Type' => 'application/vnd.sbadgroupresource.v4+json',
                'Accept' => 'application/vnd.sbadgroupresource.v4+json',
            ],
            'json' => $payload,
            'timeout' => self::HL_API_TIMEOUT,
            'connect_timeout' => self::HL_CONNECT_TIMEOUT,
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['adGroups'] ?? [];
    }

    public function getKeywordsByAdGroup($adGroupId)
    {
        $accessToken = $this->getAccessToken();
        $client = new Client();

        $url = 'https://advertising-api.amazon.com/sb/keywords';

        $response = $client->get($url, [
            'headers' => [
                'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                'Authorization' => 'Bearer ' . $accessToken,
                'Amazon-Advertising-API-Scope' => $this->profileId,
                'Accept' => 'application/vnd.sbkeyword.v3.2+json',
            ],
            'query' => [
                'adGroupIdFilter' => $adGroupId,
            ],
            'timeout' => self::HL_API_TIMEOUT,
            'connect_timeout' => self::HL_CONNECT_TIMEOUT,
        ]);

        $data = json_decode($response->getBody(), true);
        return $data ?? [];
    }

    /**
     * Count successful keyword updates from Amazon SB API response(s).
     * Handles: chunked array of arrays with objects containing "code":"SUCCESS", flat arrays, or single chunk.
     */
    public static function countSuccessfulKeywords($responseData): int
    {
        if (!is_array($responseData)) {
            return 0;
        }
        $count = 0;
        foreach ($responseData as $chunk) {
            if (!is_array($chunk)) {
                continue;
            }
            foreach ($chunk as $item) {
                if (is_array($item) && isset($item['code']) && strtoupper((string) $item['code']) === 'SUCCESS') {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Detect response format and whether it indicates success (for logging).
     */
    private static function describeResponseFormat($raw): array
    {
        if (!is_array($raw)) {
            return ['format' => 'non_array', 'success_count' => 0];
        }
        if (isset($raw['status']) && array_key_exists('data', $raw)) {
            $successCount = self::countSuccessfulKeywords($raw['data'] ?? []);
            return ['format' => 'wrapped_with_status', 'status' => $raw['status'], 'success_count' => $successCount];
        }
        $successCount = self::countSuccessfulKeywords($raw);
        if ($successCount > 0) {
            return ['format' => 'chunked_or_flat_results', 'success_count' => $successCount];
        }
        if (isset($raw['success']) && $raw['success']) {
            return ['format' => 'object_with_success', 'success_count' => 0];
        }
        return ['format' => 'unknown_array', 'success_count' => 0];
    }

    public function updateAutoCampaignSbKeywordsBid(array $campaignIds, array $newBids)
    {
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '512M');

        if (empty($campaignIds) || empty($newBids)) {
            return [
                'message' => 'Campaign IDs and new bids are required',
                'status' => 400,
            ];
        }

        Log::info('updateAutoCampaignSbKeywordsBid: start', [
            'campaigns_count' => count($campaignIds),
            'batch_size' => self::HL_BATCH_SIZE,
        ]);

        $accessToken = $this->getAccessToken();
        $client = new Client();
        $url = 'https://advertising-api.amazon.com/sb/keywords';
        $allResults = [];
        $failedBatches = [];
        $campaignBatches = array_chunk(array_combine($campaignIds, $newBids) ?: [], self::HL_BATCH_SIZE, true);

        foreach ($campaignBatches as $batchIndex => $batch) {
            if ($batchIndex > 0) {
                Log::info('updateAutoCampaignSbKeywordsBid: batch delay', [
                    'batch_index' => $batchIndex + 1,
                    'delay_sec' => self::HL_BATCH_DELAY_SECONDS,
                ]);
                sleep(self::HL_BATCH_DELAY_SECONDS);
            }

            $allKeywords = [];
            foreach ($batch as $campaignId => $newBid) {
                $newBid = floatval($newBid);
                if ($newBid <= 0) {
                    continue;
                }

                AmazonSbCampaignReport::where('campaign_id', $campaignId)
                    ->where('ad_type', 'SPONSORED_BRANDS')
                    ->whereIn('report_date_range', ['L7', 'L1'])
                    ->update(['apprSbid' => 'approved']);

                $adGroups = $this->getAdGroupsByCampaigns([$campaignId]);
                if (empty($adGroups)) {
                    continue;
                }

                foreach ($adGroups as $adGroup) {
                    $keywords = $this->getKeywordsByAdGroup($adGroup['adGroupId']);
                    foreach ($keywords as $kw) {
                        $allKeywords[] = [
                            'keywordId' => $kw['keywordId'],
                            'campaignId' => $campaignId,
                            'adGroupId' => $adGroup['adGroupId'],
                            'bid' => $newBid,
                            'state' => $kw['state'] ?? 'enabled',
                        ];
                    }
                }
            }

            if (empty($allKeywords)) {
                Log::warning('updateAutoCampaignSbKeywordsBid: no keywords in batch', [
                    'batch_index' => $batchIndex + 1,
                    'campaign_ids' => array_keys($batch),
                ]);
                continue;
            }

            $allKeywords = collect($allKeywords)->unique('keywordId')->values()->toArray();
            $chunks = array_chunk($allKeywords, self::HL_KEYWORD_CHUNK_SIZE);

            Log::info('updateAutoCampaignSbKeywordsBid: batch processing', [
                'batch_index' => $batchIndex + 1,
                'keywords_count' => count($allKeywords),
                'chunks_count' => count($chunks),
            ]);

            foreach ($chunks as $chunkIndex => $chunk) {
                $chunkSucceeded = false;
                $lastError = null;

                for ($attempt = 1; $attempt <= self::HL_MAX_RETRIES; $attempt++) {
                    try {
                        if ($attempt > 1) {
                            $delay = (int) pow(2, $attempt - 1);
                            Log::info('updateAutoCampaignSbKeywordsBid: retry', [
                                'batch_index' => $batchIndex + 1,
                                'chunk_index' => $chunkIndex + 1,
                                'attempt' => $attempt,
                                'delay_sec' => $delay,
                            ]);
                            sleep($delay);
                        }

                        $start = microtime(true);
                        $response = $client->put($url, [
                            'headers' => [
                                'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                                'Authorization' => 'Bearer ' . $accessToken,
                                'Amazon-Advertising-API-Scope' => $this->profileId,
                                'Content-Type' => 'application/json',
                            ],
                            'json' => $chunk,
                            'timeout' => self::HL_API_TIMEOUT,
                            'connect_timeout' => self::HL_CONNECT_TIMEOUT,
                        ]);
                        $durationMs = round((microtime(true) - $start) * 1000);
                        $allResults[] = json_decode($response->getBody(), true);
                        $chunkSucceeded = true;
                        Log::info('updateAutoCampaignSbKeywordsBid: chunk success', [
                            'batch_index' => $batchIndex + 1,
                            'chunk_index' => $chunkIndex + 1,
                            'keywords_count' => count($chunk),
                            'duration_ms' => $durationMs,
                        ]);
                        break;
                    } catch (\Exception $e) {
                        $lastError = $e;
                        Log::warning('updateAutoCampaignSbKeywordsBid: chunk attempt failed', [
                            'batch_index' => $batchIndex + 1,
                            'chunk_index' => $chunkIndex + 1,
                            'attempt' => $attempt,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if (!$chunkSucceeded && $lastError) {
                    $failedBatches[] = [
                        'batch_index' => $batchIndex + 1,
                        'chunk_index' => $chunkIndex + 1,
                        'error' => $lastError->getMessage(),
                    ];
                    Log::error('updateAutoCampaignSbKeywordsBid: chunk failed after retries (continuing)', [
                        'batch_index' => $batchIndex + 1,
                        'chunk_index' => $chunkIndex + 1,
                        'error' => $lastError->getMessage(),
                    ]);
                }
            }
        }

        $successCount = self::countSuccessfulKeywords($allResults);
        $summary = self::describeResponseFormat(['status' => 200, 'data' => $allResults]);

        if (!empty($failedBatches)) {
            Log::warning('updateAutoCampaignSbKeywordsBid: completed with failures', [
                'failed_count' => count($failedBatches),
                'failed' => $failedBatches,
                'success_count' => $successCount,
                'response_format' => $summary['format'],
            ]);
            return [
                'message' => 'HL bid update completed; some chunks failed after retries.',
                'data' => $allResults,
                'failed_batches' => $failedBatches,
                'status' => 207,
                'success_count' => $successCount,
            ];
        }

        Log::info('updateAutoCampaignSbKeywordsBid: success', [
            'total_chunks' => count($allResults),
            'success_count' => $successCount,
            'response_format' => $summary['format'],
        ]);
        return [
            'message' => 'HL keywords bid updated successfully',
            'data' => $allResults,
            'status' => 200,
            'success_count' => $successCount,
        ];
    }

    public function updateCampaignKeywordsBid(Request $request)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        $campaignIds = $request->input('campaign_ids', []);
        $newBids = $request->input('bids', []);

        if (empty($campaignIds) || empty($newBids)) {
            return response()->json([
                'message' => 'Campaign IDs and new bids are required',
                'status' => 400
            ]);
        }

        $allKeywords = [];

        foreach ($campaignIds as $index => $campaignId) {
            $newBid = floatval($newBids[$index] ?? 0);

            AmazonSbCampaignReport::where('campaign_id', $campaignId)
                ->where('ad_type', 'SPONSORED_BRANDS')
                ->whereIn('report_date_range', ['L7', 'L1'])
                ->update([
                    'apprSbid' => "approved"
                ]);

            $adGroups = $this->getAdGroupsByCampaigns([$campaignId]);
            if (empty($adGroups)) continue;

            foreach ($adGroups as $adGroup) {
                $keywords = $this->getKeywordsByAdGroup($adGroup['adGroupId']);
                foreach ($keywords as $kw) {
                    $allKeywords[] = [
                        'keywordId' => $kw['keywordId'],
                        'campaignId' => $campaignId,
                        'adGroupId' => $adGroup['adGroupId'],
                        'bid' => $newBid,
                        'state' => $kw['state'] ?? 'enabled'
                    ];
                }
            }
        }

        if (empty($allKeywords)) {
            return response()->json([
                'message' => 'No keywords found to update',
                'status' => 404,
            ]);
        }

        $allKeywords = collect($allKeywords)
            ->unique('keywordId')
            ->values()
            ->toArray();


        $accessToken = $this->getAccessToken();
        $client = new Client();
        $url = 'https://advertising-api.amazon.com/sb/keywords';
        $results = [];

        try {
            $chunks = array_chunk($allKeywords, 100);
            foreach ($chunks as $chunk) {
                $response = $client->put($url, [
                    'headers' => [
                        'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Amazon-Advertising-API-Scope' => $this->profileId,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $chunk,
                    'timeout' => 60,
                    'connect_timeout' => 30,
                ]);

                $results[] = json_decode($response->getBody(), true);
            }

            return response()->json([
                'message' => 'Keywords bid updated successfully',
                'data' => $results,
                'status' => 200,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating keywords bid',
                'error' => $e->getMessage(),
                'status' => 500,
            ]);
        }
    }


    public function amzUtilizedBgtHl()
    {   
        return view('campaign.amz-utilized-bgt-hl');
    }

    public function amazonUtilizedHlView()
    {
        return view('campaign.amazon.amazon-utilized-hl');
    }

    public function getAmazonUtilizedHlAdsData(Request $request)
    {
        $spBudgetController = new \App\Http\Controllers\Campaigns\AmazonSpBudgetController();
        $request->merge(['type' => 'HL']);
        return $spBudgetController->getAmazonUtilizedAdsData($request);
    }

    function getAmzUtilizedBgtHl()
    {
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

        $amazonSpCampaignReportsL15 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L15')
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

        $amazonSpCampaignReportsL1 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L1')
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

            $matchedCampaignL15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sku) {
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

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
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
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? '';
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? '';
            $row['campaignStatus'] = $matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? '');
            $row['pink_dil_paused_at'] = $matchedCampaignL7->pink_dil_paused_at ?? ($matchedCampaignL1->pink_dil_paused_at ?? null);
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '');
            $row['sbid'] = $matchedCampaignL7->sbid ?? ($matchedCampaignL1->sbid ?? '');
            $row['crnt_bid'] = $matchedCampaignL7->currentSbBidPrice ?? ($matchedCampaignL1->currentSbBidPrice ?? '');
            $row['l7_spend'] = $matchedCampaignL7->cost ?? 0;

            $costPerClick7 = ($matchedCampaignL7 && $matchedCampaignL7->clicks > 0)
                ? ($matchedCampaignL7->cost / $matchedCampaignL7->clicks)
                : 0;

            $costPerClick1 = ($matchedCampaignL1 && $matchedCampaignL1->clicks > 0)
                ? ($matchedCampaignL1->cost / $matchedCampaignL1->clicks)
                : 0;

            $row['l7_cpc']   = $costPerClick7;
            $row['l1_spend'] = $matchedCampaignL1->cost ?? 0;
            $row['l1_cpc']   = $costPerClick1;

            $sales30 = $matchedCampaignL30->sales ?? 0;
            $spend30 = $matchedCampaignL30->cost ?? 0;
            $sales15 = $matchedCampaignL15->sales ?? 0;
            $spend15 = $matchedCampaignL15->cost ?? 0;
            $sales7 = $matchedCampaignL7->sales ?? 0;
            $spend7 = $matchedCampaignL7->cost ?? 0;

            // ACOS L30
            if ($sales30 > 0) {
                $row['acos_L30'] = round(($spend30 / $sales30) * 100, 2);
            } elseif ($spend30 > 0) {
                $row['acos_L30'] = 100;
            } else {
                $row['acos_L30'] = 0;
            }

            // ACOS L15
            if ($sales15 > 0) {
                $row['acos_L15'] = round(($spend15 / $sales15) * 100, 2);
            } elseif ($spend15 > 0) {
                $row['acos_L15'] = 100;
            } else {
                $row['acos_L15'] = 0;
            }

            // ACOS L7
            if ($sales7 > 0) {
                $row['acos_L7'] = round(($spend7 / $sales7) * 100, 2);
            } elseif ($spend7 > 0) {
                $row['acos_L7'] = 100;
            } else {
                $row['acos_L7'] = 0;
            }

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
            
            if($row['campaignName'] !== '' ){
                $result[] = (object) $row;
            }
        }

        $uniqueResult = collect($result)->unique('sku')->values()->all();

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $uniqueResult,
            'status'  => 200,
        ]);
    }

    public function amzUnderUtilizedBgtHl()
    {   
        return view('campaign.amz-under-utilized-bgt-hl');
    }

    function getAmzUnderUtilizedBgtHl()
    {
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

        $amazonSpCampaignReportsL15 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L15')
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

        $amazonSpCampaignReportsL1 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L1')
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

            $matchedCampaignL15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sku) {
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

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
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
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? '';
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? '';
            $row['campaignStatus'] = $matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? '');
            $row['pink_dil_paused_at'] = $matchedCampaignL7->pink_dil_paused_at ?? ($matchedCampaignL1->pink_dil_paused_at ?? null);
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '');
            $row['sbid'] = $matchedCampaignL7->sbid ?? ($matchedCampaignL1->sbid ?? '');
            $row['crnt_bid'] = $matchedCampaignL7->currentUnderSbBidPrice ?? ($matchedCampaignL1->currentUnderSbBidPrice ?? '');
            $row['l7_spend'] = $matchedCampaignL7->cost ?? 0;

            $costPerClick7 = ($matchedCampaignL7 && $matchedCampaignL7->clicks > 0)
                ? ($matchedCampaignL7->cost / $matchedCampaignL7->clicks)
                : 0;

            $costPerClick1 = ($matchedCampaignL1 && $matchedCampaignL1->clicks > 0)
                ? ($matchedCampaignL1->cost / $matchedCampaignL1->clicks)
                : 0;

            $row['l7_cpc']   = $costPerClick7;
            $row['l1_spend'] = $matchedCampaignL1->cost ?? 0;
            $row['l1_cpc']   = $costPerClick1;


            $sales30 = $matchedCampaignL30->sales ?? 0;
            $spend30 = $matchedCampaignL30->cost ?? 0;
            $sales15 = $matchedCampaignL15->sales ?? 0;
            $spend15 = $matchedCampaignL15->cost ?? 0;
            $sales7 = $matchedCampaignL7->sales ?? 0;
            $spend7 = $matchedCampaignL7->cost ?? 0;

            // ACOS L30
            if ($sales30 > 0) {
                $row['acos_L30'] = round(($spend30 / $sales30) * 100, 2);
            } elseif ($spend30 > 0) {
                $row['acos_L30'] = 100;
            } else {
                $row['acos_L30'] = 0;
            }

            // ACOS L15
            if ($sales15 > 0) {
                $row['acos_L15'] = round(($spend15 / $sales15) * 100, 2);
            } elseif ($spend15 > 0) {
                $row['acos_L15'] = 100;
            } else {
                $row['acos_L15'] = 0;
            }

            // ACOS L7
            if ($sales7 > 0) {
                $row['acos_L7'] = round(($spend7 / $sales7) * 100, 2);
            } elseif ($spend7 > 0) {
                $row['acos_L7'] = 100;
            } else {
                $row['acos_L7'] = 0;
            }

            $row['clicks_L30'] = $matchedCampaignL30->clicks ?? 0;
            $row['clicks_L15'] = $matchedCampaignL15->clicks ?? 0;
            $row['clicks_L7'] = $matchedCampaignL7->clicks ?? 0;

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
            
            // Calculate UB7 for filtering (same as frontend filter at line 789)
            $budget = $row['campaignBudgetAmount'] ?? 0;
            $l7_spend = $row['l7_spend'] ?? 0;
            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            
            // Only include campaigns where UB7 < 70 (under-utilized) - same as frontend filter
            if($row['campaignName'] !== '' && $ub7 < 70){
                $result[] = (object) $row;
            }
        }

        $uniqueResult = collect($result)->unique('sku')->values()->all();

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $uniqueResult,
            'status'  => 200,
        ]);
    }

    public function updateAmazonSbBidPrice(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string',  
            'crnt_bid' => 'required|numeric',
            '_token' => 'required|string',
        ]);

        $updated = AmazonSbCampaignReport::where('campaign_id', $validated['id'])
            ->where('ad_type', 'SPONSORED_BRANDS')
            ->whereIn('report_date_range', ['L7', 'L1'])
            ->update([
                'currentSbBidPrice' => $validated['crnt_bid'],
                'sbid' => $validated['crnt_bid'] * 0.9
            ]);

        if($updated){
            return response()->json([
                'message' => 'CRNT BID updated successfully for all matching campaigns',
                'status' => 200,
            ]);
        }

        return response()->json([
            'message' => 'No matching campaigns found',
            'status' => 404,
        ]);
    }

    public function updateUnderAmazonSbBidPrice(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string',  
            'crnt_bid' => 'required|numeric',
            '_token' => 'required|string',
        ]);

        $updated = AmazonSbCampaignReport::where('campaign_id', $validated['id'])
            ->where('ad_type', 'SPONSORED_BRANDS')
            ->whereIn('report_date_range', ['L7', 'L1'])
            ->update([
                'currentUnderSbBidPrice' => $validated['crnt_bid'],
                'sbid' => $validated['crnt_bid'] * 1.1
            ]);

        if($updated){
            return response()->json([
                'message' => 'CRNT BID updated successfully for all matching campaigns',
                'status' => 200,
            ]);
        }

        return response()->json([
            'message' => 'No matching campaigns found',
            'status' => 404,
        ]);
    }
}
