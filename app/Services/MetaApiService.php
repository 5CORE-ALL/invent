<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaApiService
{
    protected $accessToken;
    protected $adAccountId;
    protected $apiVersion;
    protected $baseUrl;

    public function __construct()
    {
        $this->accessToken = config('services.meta.access_token');
        $this->adAccountId = config('services.meta.ad_account_id');
        $this->apiVersion = config('services.meta.api_version', 'v21.0');
        $this->baseUrl = "https://graph.facebook.com/{$this->apiVersion}";

        if (!$this->accessToken || !$this->adAccountId) {
            throw new \Exception('Meta API credentials not configured. Please set META_ACCESS_TOKEN and META_AD_ACCOUNT_ID in .env');
        }

        // Normalize ad account ID - remove 'act_' prefix if it exists, we'll add it when needed
        $this->adAccountId = preg_replace('/^act_/i', '', $this->adAccountId);
    }

    /**
     * Fetch campaigns data for last 30 days (L30)
     * Includes both Facebook and Instagram campaigns
     * 
     * @return array
     */
    public function fetchCampaignsL30()
    {
        $datePreset = 'last_30d';
        return $this->fetchCampaignsData($datePreset);
    }

    /**
     * Fetch campaigns data for last 7 days (L7)
     * Includes both Facebook and Instagram campaigns
     * 
     * @return array
     */
    public function fetchCampaignsL7()
    {
        $datePreset = 'last_7d';
        return $this->fetchCampaignsData($datePreset);
    }

    /**
     * Fetch campaigns data from Meta API
     * 
     * @param string $datePreset (last_7d, last_30d, last_90d, etc.)
     * @return array
     */
    private function fetchCampaignsData($datePreset = 'last_30d')
    {
        try {
            $campaigns = [];
            $url = "{$this->baseUrl}/act_{$this->adAccountId}/campaigns";

            $params = [
                'access_token' => $this->accessToken,
                'fields' => 'id,name,status,daily_budget,lifetime_budget,objective',
                'limit' => 500,
            ];

            // Fetch all campaigns with pagination
            do {
                $response = Http::get($url, $params);

                if (!$response->successful()) {
                    Log::error('Meta API Error - Campaigns', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    throw new \Exception('Failed to fetch campaigns: ' . $response->body());
                }

                $data = $response->json();
                $campaigns = array_merge($campaigns, $data['data'] ?? []);

                // Get next page URL if available
                $url = $data['paging']['next'] ?? null;
                $params = []; // Next URL already has all params
            } while ($url);

            // Fetch all insights in one call for better performance
            $allInsights = $this->fetchAllCampaignsInsights($datePreset);
            
            // Merge campaigns with their insights
            $campaignsWithInsights = [];
            foreach ($campaigns as $campaign) {
                $campaignId = $campaign['id'];
                $insights = $allInsights[$campaignId] ?? $this->getEmptyInsights();
                
                // Set default platform to avoid timeout on large accounts
                $campaignsWithInsights[] = array_merge($campaign, $insights, ['platform' => 'Facebook/Instagram']);
            }

            return $campaignsWithInsights;
        } catch (\Exception $e) {
            Log::error('Meta API Error - fetchCampaignsData', [
                'error' => $e->getMessage(),
                'date_preset' => $datePreset,
            ]);
            throw $e;
        }
    }

    /**
     * Fetch insights for all campaigns in one API call (much faster than per-campaign)
     * 
     * @param string $datePreset
     * @return array Associative array [campaign_id => insights]
     */
    private function fetchAllCampaignsInsights($datePreset = 'last_30d')
    {
        try {
            $url = "{$this->baseUrl}/act_{$this->adAccountId}/insights";

            $params = [
                'access_token' => $this->accessToken,
                'date_preset' => $datePreset,
                'fields' => implode(',', [
                    'campaign_id',
                    'campaign_name',
                    'impressions',
                    'spend',
                    'clicks',
                    'inline_link_clicks',
                    'cpc',
                    'cpm',
                    'ctr',
                    'frequency',
                    'reach',
                    'actions',
                    'action_values',
                ]),
                'level' => 'campaign',
                'limit' => 500,
            ];

            $insights = [];
            
            do {
                $response = Http::timeout(120)->get($url, $params);

                if (!$response->successful()) {
                    Log::warning('Meta API Warning - All Campaign Insights', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    break;
                }

                $data = $response->json();
                
                foreach ($data['data'] ?? [] as $row) {
                    $campaignId = $row['campaign_id'] ?? null;
                    if (!$campaignId) continue;
                    
                    $insights[$campaignId] = [
                        'impressions' => (int) ($row['impressions'] ?? 0),
                        'spend' => (float) ($row['spend'] ?? 0),
                        'clicks' => (int) ($row['clicks'] ?? 0),
                        'link_clicks' => (int) ($row['inline_link_clicks'] ?? 0),
                        'cpc' => (float) ($row['cpc'] ?? 0),
                        'cpm' => (float) ($row['cpm'] ?? 0),
                        'ctr' => (float) ($row['ctr'] ?? 0),
                        'frequency' => (float) ($row['frequency'] ?? 0),
                        'reach' => (int) ($row['reach'] ?? 0),
                        'actions' => $row['actions'] ?? [],
                        'action_values' => $row['action_values'] ?? [],
                    ];
                }

                // Handle pagination
                $url = $data['paging']['next'] ?? null;
                $params = [];
            } while ($url);

            return $insights;
            
        } catch (\Exception $e) {
            Log::error('Meta API Error - fetchAllCampaignsInsights', [
                'error' => $e->getMessage(),
                'date_preset' => $datePreset,
            ]);
            return [];
        }
    }

    /**
     * Fetch insights (metrics) for a specific campaign
     * 
     * @param string $campaignId
     * @param string $datePreset
     * @return array
     */
    private function fetchCampaignInsights($campaignId, $datePreset = 'last_30d')
    {
        try {
            $url = "{$this->baseUrl}/{$campaignId}/insights";

            $params = [
                'access_token' => $this->accessToken,
                'date_preset' => $datePreset,
                'fields' => implode(',', [
                    'impressions',
                    'spend',
                    'clicks',
                    'inline_link_clicks', // This is the "Link Clicks" metric
                    'cpc',
                    'cpm',
                    'ctr',
                    'frequency',
                    'reach',
                    'actions', // For conversions, purchases, etc.
                    'action_values', // For conversion values
                ]),
                'level' => 'campaign',
            ];

            $response = Http::get($url, $params);

            if (!$response->successful()) {
                Log::warning('Meta API Warning - Campaign Insights', [
                    'campaign_id' => $campaignId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return $this->getEmptyInsights();
            }

            $data = $response->json();
            
            if (empty($data['data'])) {
                return $this->getEmptyInsights();
            }

            $insights = $data['data'][0];

            return [
                'impressions' => (int) ($insights['impressions'] ?? 0),
                'spend' => (float) ($insights['spend'] ?? 0),
                'clicks' => (int) ($insights['clicks'] ?? 0),
                'link_clicks' => (int) ($insights['inline_link_clicks'] ?? 0),
                'cpc' => (float) ($insights['cpc'] ?? 0),
                'cpm' => (float) ($insights['cpm'] ?? 0),
                'ctr' => (float) ($insights['ctr'] ?? 0),
                'frequency' => (float) ($insights['frequency'] ?? 0),
                'reach' => (int) ($insights['reach'] ?? 0),
                'actions' => $insights['actions'] ?? [],
                'action_values' => $insights['action_values'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Meta API Error - fetchCampaignInsights', [
                'error' => $e->getMessage(),
                'campaign_id' => $campaignId,
            ]);
            return $this->getEmptyInsights();
        }
    }

    /**
     * Fetch all active ad accounts
     * Useful for debugging or multi-account setups
     * 
     * @return array
     */
    public function fetchAdAccounts()
    {
        try {
            $url = "{$this->baseUrl}/me/adaccounts";

            $params = [
                'access_token' => $this->accessToken,
                'fields' => 'id,account_id,name,account_status,currency,timezone_name',
            ];

            $response = Http::get($url, $params);

            if (!$response->successful()) {
                Log::error('Meta API Error - Ad Accounts', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Failed to fetch ad accounts: ' . $response->body());
            }

            return $response->json()['data'] ?? [];
        } catch (\Exception $e) {
            Log::error('Meta API Error - fetchAdAccounts', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get campaign platform (Facebook, Instagram, or Both)
     * 
     * @param string $campaignId
     * @return string
     */
    public function getCampaignPlatform($campaignId)
    {
        try {
            $url = "{$this->baseUrl}/{$campaignId}/adsets";

            $params = [
                'access_token' => $this->accessToken,
                'fields' => 'targeting',
                'limit' => 1,
            ];

            $response = Http::get($url, $params);

            if (!$response->successful()) {
                return 'Unknown';
            }

            $data = $response->json();
            
            if (empty($data['data'])) {
                return 'Unknown';
            }

            $targeting = $data['data'][0]['targeting'] ?? [];
            $publisherPlatforms = $targeting['publisher_platforms'] ?? [];

            if (empty($publisherPlatforms)) {
                return 'Facebook/Instagram';
            }

            $hasFacebook = in_array('facebook', $publisherPlatforms);
            $hasInstagram = in_array('instagram', $publisherPlatforms);

            if ($hasFacebook && $hasInstagram) {
                return 'Facebook/Instagram';
            } elseif ($hasInstagram) {
                return 'Instagram';
            } elseif ($hasFacebook) {
                return 'Facebook';
            }

            return 'Facebook/Instagram';
        } catch (\Exception $e) {
            Log::error('Meta API Error - getCampaignPlatform', [
                'error' => $e->getMessage(),
                'campaign_id' => $campaignId,
            ]);
            return 'Unknown';
        }
    }

    /**
     * Validate Meta API credentials
     * 
     * @return bool
     */
    public function validateCredentials()
    {
        try {
            $url = "{$this->baseUrl}/me";

            $params = [
                'access_token' => $this->accessToken,
                'fields' => 'id,name',
            ];

            $response = Http::get($url, $params);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Meta API Error - validateCredentials', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get empty insights structure
     * 
     * @return array
     */
    private function getEmptyInsights()
    {
        return [
            'impressions' => 0,
            'spend' => 0,
            'clicks' => 0,
            'link_clicks' => 0,
            'cpc' => 0,
            'cpm' => 0,
            'ctr' => 0,
            'frequency' => 0,
            'reach' => 0,
            'actions' => [],
            'action_values' => [],
        ];
    }

    /**
     * Fetch campaigns with ad set budget information
     * 
     * @param string $datePreset
     * @param bool $includeAdSetBudget Whether to fetch detailed ad set budgets (slower)
     * @return array
     */
    public function fetchCampaignsWithBudget($datePreset = 'last_30d', $includeAdSetBudget = false)
    {
        $campaigns = $this->fetchCampaignsData($datePreset);

        // Optionally enrich with ad set budget info (makes additional API calls)
        if ($includeAdSetBudget) {
            foreach ($campaigns as &$campaign) {
                $campaign['ad_set_budget'] = $this->getAdSetBudget($campaign['id']);
                
                // Set default platform to avoid timeout on large accounts
                // Platform detection can be done separately if needed
                $campaign['platform'] = 'Facebook/Instagram';
            }
        } else {
            // Just add default values without extra API calls
            foreach ($campaigns as &$campaign) {
                $campaign['ad_set_budget'] = 0; // Campaign budget fields are already included
                $campaign['platform'] = 'Facebook/Instagram';
            }
        }

        return $campaigns;
    }

    /**
     * Get total ad set budget for a campaign
     * 
     * @param string $campaignId
     * @return float
     */
    private function getAdSetBudget($campaignId)
    {
        try {
            $url = "{$this->baseUrl}/{$campaignId}/adsets";

            $params = [
                'access_token' => $this->accessToken,
                'fields' => 'daily_budget,lifetime_budget,budget_remaining',
            ];

            $response = Http::get($url, $params);

            if (!$response->successful()) {
                return 0;
            }

            $data = $response->json();
            $totalBudget = 0;

            foreach ($data['data'] ?? [] as $adSet) {
                // Convert cents to dollars
                $dailyBudget = isset($adSet['daily_budget']) ? (float) $adSet['daily_budget'] / 100 : 0;
                $lifetimeBudget = isset($adSet['lifetime_budget']) ? (float) $adSet['lifetime_budget'] / 100 : 0;
                
                // Use daily budget if available, otherwise lifetime budget
                $totalBudget += max($dailyBudget, $lifetimeBudget);
            }

            return $totalBudget;
        } catch (\Exception $e) {
            Log::error('Meta API Error - getAdSetBudget', [
                'error' => $e->getMessage(),
                'campaign_id' => $campaignId,
            ]);
            return 0;
        }
    }

    /**
     * Fetch raw ads data from Meta API
     * Returns all available fields for ads
     * 
     * @param array $fields Optional array of fields to fetch. If empty, fetches all common fields
     * @return array
     */
    public function fetchRawAdsData($fields = [])
    {
        try {
            $ads = [];
            $url = "{$this->baseUrl}/act_{$this->adAccountId}/ads";

            // Default fields if none specified
            if (empty($fields)) {
                $fields = [
                    'id',
                    'name',
                    'status',
                    'adset_id',
                    'campaign_id',
                    'effective_object_story_id',
                    'preview_shareable_link',
                    'source_ad',
                    'source_ad_id',
                    'updated_time',
                    'created_time',
                ];
            }

            $params = [
                'access_token' => $this->accessToken,
                'fields' => implode(',', $fields),
                'limit' => 500,
            ];

            // Fetch all ads with pagination
            do {
                $response = Http::timeout(120)->get($url, $params);

                if (!$response->successful()) {
                    $errorBody = $response->json();
                    $errorMessage = $response->body();
                    
                    // Try to extract a more readable error message
                    if (isset($errorBody['error']['message'])) {
                        $errorMessage = $errorBody['error']['message'];
                        if (isset($errorBody['error']['code'])) {
                            $errorMessage .= ' (Code: ' . $errorBody['error']['code'] . ')';
                        }
                    }
                    
                    Log::error('Meta API Error - Raw Ads', [
                        'status' => $response->status(),
                        'url' => $url,
                        'body' => $response->body(),
                        'ad_account_id' => $this->adAccountId,
                    ]);
                    
                    throw new \Exception('Failed to fetch ads: ' . $errorMessage);
                }

                $data = $response->json();
                
                // Check if there's an error in the response
                if (isset($data['error'])) {
                    $errorMessage = $data['error']['message'] ?? 'Unknown error';
                    if (isset($data['error']['code'])) {
                        $errorMessage .= ' (Code: ' . $data['error']['code'] . ')';
                    }
                    throw new \Exception('Meta API Error: ' . $errorMessage);
                }
                
                $ads = array_merge($ads, $data['data'] ?? []);

                // Get next page URL if available
                $url = $data['paging']['next'] ?? null;
                $params = []; // Next URL already has all params
            } while ($url);

            return $ads;
        } catch (\Exception $e) {
            Log::error('Meta API Error - fetchRawAdsData', [
                'error' => $e->getMessage(),
                'ad_account_id' => $this->adAccountId,
            ]);
            throw $e;
        }
    }

    /**
     * Fetch raw campaigns data from Meta API (without processing)
     * Returns all available fields for campaigns
     * 
     * @param array $fields Optional array of fields to fetch
     * @return array
     */
    public function fetchRawCampaignsData($fields = [])
    {
        try {
            $campaigns = [];
            $url = "{$this->baseUrl}/act_{$this->adAccountId}/campaigns";

            // Default fields if none specified
            if (empty($fields)) {
                $fields = [
                    'id',
                    'name',
                    'status',
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
                'access_token' => $this->accessToken,
                'fields' => implode(',', $fields),
                'limit' => 500,
            ];

            // Fetch all campaigns with pagination
            do {
                $response = Http::timeout(120)->get($url, $params);

                if (!$response->successful()) {
                    Log::error('Meta API Error - Raw Campaigns', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    throw new \Exception('Failed to fetch campaigns: ' . $response->body());
                }

                $data = $response->json();
                $campaigns = array_merge($campaigns, $data['data'] ?? []);

                // Get next page URL if available
                $url = $data['paging']['next'] ?? null;
                $params = []; // Next URL already has all params
            } while ($url);

            return $campaigns;
        } catch (\Exception $e) {
            Log::error('Meta API Error - fetchRawCampaignsData', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Fetch raw insights data from Meta API
     * 
     * @param string $datePreset Date preset (last_7d, last_30d, etc.)
     * @param string $level Level of insights (account, campaign, adset, ad)
     * @return array
     */
    public function fetchRawInsightsData($datePreset = 'last_30d', $level = 'campaign')
    {
        try {
            $insights = [];
            $url = "{$this->baseUrl}/act_{$this->adAccountId}/insights";

            $params = [
                'access_token' => $this->accessToken,
                'date_preset' => $datePreset,
                'level' => $level,
                'limit' => 500,
            ];

            // Fetch all insights with pagination
            do {
                $response = Http::timeout(120)->get($url, $params);

                if (!$response->successful()) {
                    Log::error('Meta API Error - Raw Insights', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    throw new \Exception('Failed to fetch insights: ' . $response->body());
                }

                $data = $response->json();
                $insights = array_merge($insights, $data['data'] ?? []);

                // Get next page URL if available
                $url = $data['paging']['next'] ?? null;
                $params = []; // Next URL already has all params
            } while ($url);

            return $insights;
        } catch (\Exception $e) {
            Log::error('Meta API Error - fetchRawInsightsData', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
