<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GA4ApiService
{
    protected $propertyId;
    protected $accessToken;

    public function __construct()
    {
        $this->propertyId = env('GA4_PROPERTY_ID');
        $this->accessToken = $this->getAccessToken();
    }

    /**
     * Get OAuth2 access token for GA4 API
     */
    protected function getAccessToken()
    {
        // Check cache first
        $cachedToken = Cache::get('ga4_access_token');
        if ($cachedToken) {
            return $cachedToken;
        }

        $clientId = env('GA4_CLIENT_ID');
        $clientSecret = env('GA4_CLIENT_SECRET');
        $refreshToken = env('GA4_REFRESH_TOKEN');

        if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
            Log::warning('GA4 API credentials not configured');
            return null;
        }

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $accessToken = $data['access_token'] ?? null;
                
                if ($accessToken) {
                    // Cache token for 50 minutes (tokens expire in 1 hour)
                    Cache::put('ga4_access_token', $accessToken, now()->addMinutes(50));
                    return $accessToken;
                }
            }

            Log::error('Failed to get GA4 access token', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting GA4 access token: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Fetch campaign revenue and purchases from GA4 (daily breakdown)
     * 
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return array Campaign data with purchases and revenue (keyed by campaign_name, then date)
     */
    public function getCampaignMetricsDaily($startDate, $endDate)
    {
        if (!$this->accessToken || !$this->propertyId) {
            Log::warning('GA4 API not configured. Set GA4_PROPERTY_ID, GA4_CLIENT_ID, GA4_CLIENT_SECRET, GA4_REFRESH_TOKEN in .env');
            return [];
        }

        try {
            $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$this->propertyId}:runReport";
            
            $requestBody = [
                'dateRanges' => [
                    [
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                    ]
                ],
                'dimensions' => [
                    ['name' => 'sessionGoogleAdsCampaignName'],
                    ['name' => 'date'],
                    ['name' => 'eventName'], // Required to filter purchase events
                ],
                'metrics' => [
                    ['name' => 'purchaseRevenue'], // Total revenue from purchases
                    ['name' => 'eventCount'], // Number of events (will filter for purchase)
                ],
                'dimensionFilter' => [
                    'filter' => [
                        'fieldName' => 'eventName',
                        'stringFilter' => [
                            'matchType' => 'EXACT',
                            'value' => 'purchase',
                        ]
                    ]
                ],
            ];

            $response = Http::timeout(60)
                ->withToken($this->accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $requestBody);

            if ($response->successful()) {
                $data = $response->json();
                $campaigns = [];

                if (isset($data['rows'])) {
                    foreach ($data['rows'] as $row) {
                        $campaignName = $row['dimensionValues'][0]['value'] ?? '';
                        $date = $row['dimensionValues'][1]['value'] ?? '';
                        $eventName = $row['dimensionValues'][2]['value'] ?? '';
                        $revenue = floatval($row['metricValues'][0]['value'] ?? 0);
                        $eventCount = floatval($row['metricValues'][1]['value'] ?? 0);

                        // Only process purchase events
                        if ($eventName === 'purchase' && !empty($campaignName) && !empty($date)) {
                            if (!isset($campaigns[$campaignName])) {
                                $campaigns[$campaignName] = [];
                            }
                            $campaigns[$campaignName][$date] = [
                                'campaign_name' => $campaignName,
                                'date' => $date,
                                'purchases' => $eventCount,
                                'revenue' => $revenue,
                            ];
                        }
                    }
                }

                $totalCampaigns = count($campaigns);
                $totalDays = array_sum(array_map('count', $campaigns));
                Log::info("GA4 API: Fetched {$totalCampaigns} campaigns with {$totalDays} daily records");
                return $campaigns;
            } else {
                Log::error('GA4 API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error fetching GA4 campaign metrics: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }

        return [];
    }

    /**
     * Fetch campaign revenue and purchases from GA4 (aggregated)
     * 
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return array Campaign data with purchases and revenue (keyed by campaign name)
     */
    public function getCampaignMetrics($startDate, $endDate)
    {
        $dailyData = $this->getCampaignMetricsDaily($startDate, $endDate);
        $aggregated = [];

        foreach ($dailyData as $campaignName => $dates) {
            $totalPurchases = array_sum(array_column($dates, 'purchases'));
            $totalRevenue = array_sum(array_column($dates, 'revenue'));
            
            $aggregated[$campaignName] = [
                'campaign_name' => $campaignName,
                'purchases' => $totalPurchases,
                'revenue' => $totalRevenue,
            ];
        }

        return $aggregated;
    }

    /**
     * Get GA4 metrics for a specific campaign
     */
    public function getCampaignMetricsByName($campaignName, $startDate, $endDate)
    {
        $allCampaigns = $this->getCampaignMetrics($startDate, $endDate);
        
        // Try exact match first
        if (isset($allCampaigns[$campaignName])) {
            return $allCampaigns[$campaignName];
        }

        // Try case-insensitive match
        foreach ($allCampaigns as $name => $data) {
            if (strcasecmp($name, $campaignName) === 0) {
                return $data;
            }
        }

        // Try partial match (campaign name contains SKU)
        $campaignNameUpper = strtoupper(trim($campaignName));
        foreach ($allCampaigns as $name => $data) {
            $nameUpper = strtoupper(trim($name));
            if (strpos($nameUpper, $campaignNameUpper) !== false || 
                strpos($campaignNameUpper, $nameUpper) !== false) {
                return $data;
            }
        }

        return null;
    }
}
