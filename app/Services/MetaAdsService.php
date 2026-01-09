<?php

namespace App\Services;

use App\Models\MetaAdAccount;
use App\Models\MetaCampaign;
use App\Models\MetaAdSet;
use App\Models\MetaAd;
use App\Models\MetaInsightDaily;
use App\Models\MetaActionLog;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MetaAdsService
{
    protected $metaApiService;

    public function __construct(MetaApiService $metaApiService)
    {
        $this->metaApiService = $metaApiService;
    }

    /**
     * Fetch ad accounts from Meta API
     * 
     * @return array
     */
    public function fetchAdAccounts(): array
    {
        try {
            return $this->metaApiService->fetchAdAccounts();
        } catch (\Exception $e) {
            Log::error('MetaAdsService: Failed to fetch ad accounts', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Fetch campaigns for an ad account
     * 
     * @param string $adAccountMetaId
     * @param array $fields
     * @return array
     */
    public function fetchCampaigns(string $adAccountMetaId, array $fields = []): array
    {
        try {
            if (empty($adAccountMetaId)) {
                // If no account specified, use the configured account from MetaApiService
                return $this->metaApiService->fetchRawCampaignsData($fields);
            }
            
            // Ensure account ID has 'act_' prefix
            $accountId = $adAccountMetaId;
            if (!str_starts_with($accountId, 'act_')) {
                $accountId = 'act_' . $accountId;
            }
            
            $baseUrl = config('services.meta.base_url', 'https://graph.facebook.com/v21.0');
            $accessToken = config('services.meta.access_token');
            
            $url = "{$baseUrl}/{$accountId}/campaigns";
            
            // Default fields if none specified
            if (empty($fields)) {
                $fields = [
                    'id',
                    'name',
                    'status',
                    'effective_status',
                    'objective',
                    'daily_budget',
                    'lifetime_budget',
                    'budget_remaining',
                    'created_time',
                    'updated_time',
                    'start_time',
                    'stop_time',
                    'special_ad_categories',
                    'buying_type',
                    'bid_strategy',
                ];
            }
            
            $params = [
                'access_token' => $accessToken,
                'fields' => implode(',', $fields),
                'limit' => 500,
            ];
            
            $campaigns = [];
            do {
                $response = \Illuminate\Support\Facades\Http::timeout(120)->get($url, $params);
                
                if (!$response->successful()) {
                    $errorBody = $response->json();
                    $errorMessage = $response->body();
                    
                    if (isset($errorBody['error']['message'])) {
                        $errorMessage = $errorBody['error']['message'];
                        if (isset($errorBody['error']['code'])) {
                            $errorMessage .= ' (Code: ' . $errorBody['error']['code'] . ')';
                        }
                    }
                    
                    Log::error('MetaAdsService: Failed to fetch campaigns', [
                        'ad_account_id' => $adAccountMetaId,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    
                    throw new \Exception('Failed to fetch campaigns: ' . $errorMessage);
                }
                
                $data = $response->json();
                
                if (isset($data['error'])) {
                    $errorMessage = $data['error']['message'] ?? 'Unknown error';
                    if (isset($data['error']['code'])) {
                        $errorMessage .= ' (Code: ' . $data['error']['code'] . ')';
                    }
                    throw new \Exception('Meta API Error: ' . $errorMessage);
                }
                
                $campaigns = array_merge($campaigns, $data['data'] ?? []);
                
                $url = $data['paging']['next'] ?? null;
                $params = [];
            } while ($url);
            
            return $campaigns;
        } catch (\Exception $e) {
            Log::error('MetaAdsService: Failed to fetch campaigns', [
                'ad_account_id' => $adAccountMetaId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Fetch ad sets for a campaign
     * 
     * @param string $campaignMetaId
     * @param array $fields
     * @return array
     */
    public function fetchAdSets(string $campaignMetaId, array $fields = []): array
    {
        try {
            $baseUrl = config('services.meta.base_url', 'https://graph.facebook.com/v21.0');
            $accessToken = config('services.meta.access_token');
            
            $url = "{$baseUrl}/{$campaignMetaId}/adsets";
            $params = [
                'access_token' => $accessToken,
                'fields' => empty($fields) ? implode(',', [
                    'id',
                    'name',
                    'status',
                    'effective_status',
                    'optimization_goal',
                    'daily_budget',
                    'lifetime_budget',
                    'budget_remaining',
                    'start_time',
                    'end_time',
                    'billing_event',
                    'bid_amount',
                    'targeting',
                    'updated_time',
                ]) : implode(',', $fields),
                'limit' => 500,
            ];

            $adsets = [];
            do {
                $response = \Illuminate\Support\Facades\Http::timeout(120)->get($url, $params);
                
                if (!$response->successful()) {
                    Log::error('MetaAdsService: Failed to fetch ad sets', [
                        'campaign_id' => $campaignMetaId,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    throw new \Exception('Failed to fetch ad sets: ' . $response->body());
                }

                $data = $response->json();
                $adsets = array_merge($adsets, $data['data'] ?? []);

                $url = $data['paging']['next'] ?? null;
                $params = [];
            } while ($url);

            return $adsets;
        } catch (\Exception $e) {
            Log::error('MetaAdsService: Failed to fetch ad sets', [
                'campaign_id' => $campaignMetaId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Fetch ads for an ad set
     * 
     * @param string $adSetMetaId
     * @param array $fields
     * @return array
     */
    public function fetchAds(string $adSetMetaId, array $fields = []): array
    {
        try {
            $baseUrl = config('services.meta.base_url', 'https://graph.facebook.com/v21.0');
            $accessToken = config('services.meta.access_token');
            
            $url = "{$baseUrl}/{$adSetMetaId}/ads";
            $params = [
                'access_token' => $accessToken,
                'fields' => empty($fields) ? implode(',', [
                    'id',
                    'name',
                    'status',
                    'effective_status',
                    'adset_id',
                    'campaign_id',
                    'creative',
                    'preview_shareable_link',
                    'updated_time',
                ]) : implode(',', $fields),
                'limit' => 500,
            ];

            $ads = [];
            
            do {
                $retries = 0;
                $maxRetries = 3;
                $response = null;
                $success = false;
                
                // Retry loop for rate limiting
                while (!$success && $retries < $maxRetries) {
                    $response = \Illuminate\Support\Facades\Http::timeout(120)->get($url, $params);
                    
                    if (!$response->successful()) {
                        $errorBody = $response->json();
                        $errorCode = $errorBody['error']['code'] ?? null;
                        $errorMessage = $errorBody['error']['message'] ?? $response->body();
                        
                        // Handle rate limiting - retry with exponential backoff
                        if ($errorCode == 17 || $errorCode == 613 || strpos($errorMessage, 'rate limit') !== false || strpos($errorMessage, 'too many') !== false) {
                            $retries++;
                            $waitTime = pow(2, $retries); // Exponential backoff: 2, 4, 8 seconds
                            Log::warning('MetaAdsService: Rate limited, retrying', [
                                'adset_id' => $adSetMetaId,
                                'retry' => $retries,
                                'wait_seconds' => $waitTime,
                            ]);
                            sleep($waitTime);
                            continue; // Retry the request
                        }
                        
                        // Non-rate-limit error - throw immediately
                        Log::error('MetaAdsService: Failed to fetch ads', [
                            'adset_id' => $adSetMetaId,
                            'status' => $response->status(),
                            'error_code' => $errorCode,
                            'body' => $response->body(),
                        ]);
                        throw new \Exception('Failed to fetch ads: ' . $errorMessage);
                    }
                    
                    $success = true;
                }
                
                if (!$success || !$response) {
                    throw new \Exception('Failed to fetch ads after ' . $maxRetries . ' retries due to rate limiting');
                }

                $data = $response->json();
                
                // Check for errors in response
                if (isset($data['error'])) {
                    $errorMessage = $data['error']['message'] ?? 'Unknown error';
                    if (isset($data['error']['code'])) {
                        $errorMessage .= ' (Code: ' . $data['error']['code'] . ')';
                    }
                    throw new \Exception('Meta API Error: ' . $errorMessage);
                }
                
                $ads = array_merge($ads, $data['data'] ?? []);

                $url = $data['paging']['next'] ?? null;
                $params = [];
                
                // Small delay between pagination requests to avoid rate limits
                if ($url) {
                    usleep(200000); // 200ms delay
                }
            } while ($url);

            return $ads;
        } catch (\Exception $e) {
            Log::error('MetaAdsService: Failed to fetch ads', [
                'adset_id' => $adSetMetaId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Fetch daily insights for an entity
     * 
     * @param string $entityType (account, campaign, adset, ad)
     * @param string $entityMetaId
     * @param string $dateStart YYYY-MM-DD
     * @param string $dateEnd YYYY-MM-DD
     * @param array $breakdowns
     * @return array
     */
    public function fetchInsightsDaily(
        string $entityType,
        string $entityMetaId,
        string $dateStart,
        string $dateEnd,
        array $breakdowns = []
    ): array {
        try {
            $baseUrl = config('services.meta.base_url', 'https://graph.facebook.com/v21.0');
            $accessToken = config('services.meta.access_token');
            
            $url = "{$baseUrl}/{$entityMetaId}/insights";
            $params = [
                'access_token' => $accessToken,
                'time_range' => json_encode([
                    'since' => $dateStart,
                    'until' => $dateEnd,
                ]),
                'level' => $entityType,
                'fields' => implode(',', [
                    'impressions',
                    'clicks',
                    'reach',
                    'spend',
                    'ctr',
                    'cpc',
                    'cpm',
                    'cpp',
                    'frequency',
                    'actions',
                    'action_values',
                ]),
                'limit' => 500,
            ];

            if (!empty($breakdowns)) {
                $params['breakdowns'] = implode(',', $breakdowns);
            }

            $insights = [];
            do {
                $response = \Illuminate\Support\Facades\Http::timeout(120)->get($url, $params);
                
                if (!$response->successful()) {
                    Log::warning('MetaAdsService: Failed to fetch insights', [
                        'entity_type' => $entityType,
                        'entity_id' => $entityMetaId,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    break;
                }

                $data = $response->json();
                $insights = array_merge($insights, $data['data'] ?? []);

                $url = $data['paging']['next'] ?? null;
                $params = [];
            } while ($url);

            return $insights;
        } catch (\Exception $e) {
            Log::error('MetaAdsService: Failed to fetch insights', [
                'entity_type' => $entityType,
                'entity_id' => $entityMetaId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Update entity status (pause/resume)
     * 
     * @param string $entityType (campaign, adset, ad)
     * @param string $entityMetaId
     * @param string $status (PAUSED, ACTIVE, etc.)
     * @param int $userId
     * @return array
     */
    public function updateStatus(string $entityType, string $entityMetaId, string $status, int $userId): array
    {
        try {
            $baseUrl = config('services.meta.base_url', 'https://graph.facebook.com/v21.0');
            $accessToken = config('services.meta.access_token');
            
            $url = "{$baseUrl}/{$entityMetaId}";
            $params = [
                'access_token' => $accessToken,
                'status' => $status,
            ];

            $response = \Illuminate\Support\Facades\Http::post($url, $params);
            
            $requestPayload = $params;
            $responsePayload = $response->json();
            $success = $response->successful();

            // Log the action
            try {
                MetaActionLog::create([
                    'user_id' => $userId,
                    'action_type' => 'update_status',
                    'entity_type' => $entityType,
                    'entity_meta_id' => $entityMetaId,
                    'status' => $success ? 'success' : 'failed',
                    'request_payload' => $requestPayload,
                    'response_payload' => $responsePayload,
                    'error_message' => $success ? null : ($responsePayload['error']['message'] ?? 'Unknown error'),
                    'meta_error_code' => $success ? null : ($responsePayload['error']['code'] ?? null),
                    'meta_error_message' => $success ? null : ($responsePayload['error']['message'] ?? null),
                ]);
            } catch (\Exception $e) {
                Log::error('MetaAdsService: Failed to create action log', [
                    'error' => $e->getMessage(),
                    'user_id' => $userId,
                    'entity_type' => $entityType,
                    'entity_meta_id' => $entityMetaId,
                ]);
            }

            if (!$success) {
                throw new \Exception('Failed to update status: ' . ($responsePayload['error']['message'] ?? 'Unknown error'));
            }

            return $responsePayload;
        } catch (\Exception $e) {
            Log::error('MetaAdsService: Failed to update status', [
                'entity_type' => $entityType,
                'entity_id' => $entityMetaId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update entity budget
     * 
     * @param string $entityType (campaign, adset)
     * @param string $entityMetaId
     * @param array $budgetData ['daily_budget' => 10000] or ['lifetime_budget' => 100000]
     * @param int $userId
     * @return array
     */
    public function updateBudget(string $entityType, string $entityMetaId, array $budgetData, int $userId): array
    {
        try {
            $baseUrl = config('services.meta.base_url', 'https://graph.facebook.com/v21.0');
            $accessToken = config('services.meta.access_token');
            
            $url = "{$baseUrl}/{$entityMetaId}";
            $params = array_merge([
                'access_token' => $accessToken,
            ], $budgetData);

            $response = \Illuminate\Support\Facades\Http::post($url, $params);
            
            $requestPayload = $params;
            $responsePayload = $response->json();
            $success = $response->successful();

            // Log the action
            try {
                MetaActionLog::create([
                    'user_id' => $userId,
                    'action_type' => 'update_budget',
                    'entity_type' => $entityType,
                    'entity_meta_id' => $entityMetaId,
                    'status' => $success ? 'success' : 'failed',
                    'request_payload' => $requestPayload,
                    'response_payload' => $responsePayload,
                    'error_message' => $success ? null : ($responsePayload['error']['message'] ?? 'Unknown error'),
                    'meta_error_code' => $success ? null : ($responsePayload['error']['code'] ?? null),
                    'meta_error_message' => $success ? null : ($responsePayload['error']['message'] ?? null),
                ]);
            } catch (\Exception $e) {
                Log::error('MetaAdsService: Failed to create action log', [
                    'error' => $e->getMessage(),
                    'user_id' => $userId,
                    'entity_type' => $entityType,
                    'entity_meta_id' => $entityMetaId,
                ]);
            }

            if (!$success) {
                throw new \Exception('Failed to update budget: ' . ($responsePayload['error']['message'] ?? 'Unknown error'));
            }

            return $responsePayload;
        } catch (\Exception $e) {
            Log::error('MetaAdsService: Failed to update budget', [
                'entity_type' => $entityType,
                'entity_id' => $entityMetaId,
                'budget_data' => $budgetData,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Bulk update multiple entities
     * 
     * @param string $entityType
     * @param array $updates [['id' => 'meta_id', 'status' => 'PAUSED'], ...]
     * @param int $userId
     * @return array
     */
    public function bulkUpdate(string $entityType, array $updates, int $userId): array
    {
        $results = [];
        
        foreach ($updates as $update) {
            try {
                if (isset($update['status'])) {
                    $this->updateStatus($entityType, $update['id'], $update['status'], $userId);
                }
                if (isset($update['daily_budget']) || isset($update['lifetime_budget'])) {
                    $budgetData = array_filter([
                        'daily_budget' => $update['daily_budget'] ?? null,
                        'lifetime_budget' => $update['lifetime_budget'] ?? null,
                    ]);
                    $this->updateBudget($entityType, $update['id'], $budgetData, $userId);
                }
                $results[$update['id']] = ['status' => 'success'];
            } catch (\Exception $e) {
                $results[$update['id']] = ['status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }
}

