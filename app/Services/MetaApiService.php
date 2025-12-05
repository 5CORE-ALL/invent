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

            // Fetch insights and platform for each campaign
            $campaignsWithInsights = [];
            foreach ($campaigns as $campaign) {
                $insights = $this->fetchCampaignInsights($campaign['id'], $datePreset);
                $platform = $this->getCampaignPlatform($campaign['id']);
                $campaignsWithInsights[] = array_merge($campaign, $insights, ['platform' => $platform]);
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
     * @return array
     */
    public function fetchCampaignsWithBudget($datePreset = 'last_30d')
    {
        $campaigns = $this->fetchCampaignsData($datePreset);

        // Enrich with ad set budget info
        foreach ($campaigns as &$campaign) {
            $campaign['ad_set_budget'] = $this->getAdSetBudget($campaign['id']);
            
            // Determine platform
            $campaign['platform'] = $this->getCampaignPlatform($campaign['id']);
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
}
