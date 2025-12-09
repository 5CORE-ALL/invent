<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ShopifyMarketingService
{
    protected $shopifyStoreUrl;
    protected $shopifyAccessToken;
    protected $apiVersion = '2024-10';

    public function __construct()
    {
        $this->shopifyStoreUrl = env('SHOPIFY_STORE_URL', '5-core.myshopify.com');
        $this->shopifyAccessToken = env('SHOPIFY_ACCESS_TOKEN');
    }

    /**
     * Fetch Facebook campaign data from Shopify Analytics API
     * 
     * @param string $dateRange ('7_days', '30_days', '60_days')
     * @return array
     */
    public function fetchFacebookCampaignData($dateRange = '30_days')
    {
        $dates = $this->getDateRange($dateRange);
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        try {
            // Shopify Admin API endpoint for reports
            $url = "https://{$this->shopifyStoreUrl}/admin/api/{$this->apiVersion}/reports.json";

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                'Content-Type' => 'application/json',
            ])->get($url);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("Shopify Marketing Data fetched for {$dateRange}", ['data' => $data]);
                return $this->parseCampaignData($data, $dateRange, $startDate, $endDate);
            } else {
                Log::error("Failed to fetch Shopify marketing data", [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return [];
            }
        } catch (\Exception $e) {
            Log::error("Exception fetching Shopify marketing data", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Fetch orders with UTM parameters for Meta campaigns (Facebook or Instagram)
     * 
     * @param string $dateRange
     * @param string $channel ('facebook', 'instagram', or 'both')
     * @return array
     */
    public function fetchOrdersWithUtmData($dateRange = '30_days', $channel = 'facebook')
    {
        $dates = $this->getDateRange($dateRange);
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        // Add initial delay to avoid hitting rate limit from other API calls
        sleep(2);

        try {
            // Fetch orders with landing_site containing Facebook UTM parameters
            $url = "https://{$this->shopifyStoreUrl}/admin/api/{$this->apiVersion}/orders.json";

            $orders = [];
            $pageInfo = null;
            $hasNextPage = true;

            while ($hasNextPage) {
                if ($pageInfo) {
                    // When using page_info, only include page_info and limit
                    $params = [
                        'page_info' => $pageInfo,
                        'limit' => 250,
                    ];
                } else {
                    // First request with filters
                    $params = [
                        'status' => 'any',
                        'created_at_min' => $startDate->toIso8601String(),
                        'created_at_max' => $endDate->toIso8601String(),
                        'limit' => 250,
                        'fields' => 'id,created_at,total_price,landing_site,referring_site,source_name,line_items'
                    ];
                }

                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                    'Content-Type' => 'application/json',
                ])->get($url, $params);
                
                // Handle rate limiting with retry
                $retryCount = 0;
                while ($response->status() === 429 && $retryCount < 3) {
                    $retryCount++;
                    $waitTime = $retryCount * 2; // 2, 4, 6 seconds
                    Log::warning("Rate limit hit, waiting {$waitTime} seconds before retry {$retryCount}/3");
                    sleep($waitTime);
                    
                    $response = Http::withHeaders([
                        'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                        'Content-Type' => 'application/json',
                    ])->get($url, $params);
                }
                
                // Rate limiting - wait 1 second between requests to avoid hitting rate limit
                sleep(1);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Filter orders based on channel
                    $filteredOrders = array_filter($data['orders'] ?? [], function($order) use ($channel) {
                        $landingSite = $order['landing_site'] ?? '';
                        $referringSite = $order['referring_site'] ?? '';
                        $sourceName = $order['source_name'] ?? '';
                        
                        $isFacebook = stripos($landingSite, 'facebook') !== false 
                            || stripos($landingSite, 'fb') !== false
                            || stripos($landingSite, 'utm_source=facebook') !== false
                            || stripos($referringSite, 'facebook') !== false
                            || stripos($sourceName, 'facebook') !== false;
                        
                        $isInstagram = stripos($landingSite, 'instagram') !== false 
                            || stripos($landingSite, 'insta') !== false
                            || stripos($landingSite, 'utm_source=instagram') !== false
                            || stripos($referringSite, 'instagram') !== false
                            || stripos($sourceName, 'instagram') !== false;
                        
                        if ($channel === 'both') {
                            return $isFacebook || $isInstagram;
                        } elseif ($channel === 'instagram') {
                            return $isInstagram;
                        } else {
                            // Default to Facebook
                            return $isFacebook;
                        }
                    });

                    $orders = array_merge($orders, $filteredOrders);

                    // Check for pagination
                    $linkHeader = $response->header('Link');
                    $hasNextPage = $linkHeader && strpos($linkHeader, 'rel="next"') !== false;
                    
                    if ($hasNextPage) {
                        preg_match('/<([^>]+)>;\s*rel="next"/', $linkHeader, $matches);
                        if (isset($matches[1])) {
                            parse_str(parse_url($matches[1], PHP_URL_QUERY), $queryParams);
                            $pageInfo = $queryParams['page_info'] ?? null;
                        } else {
                            $hasNextPage = false;
                        }
                    }
                } else {
                    Log::error("Failed to fetch Shopify orders", [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    break;
                }
            }

            return $this->aggregateOrderData($orders, $dateRange, $startDate, $endDate, $channel);

        } catch (\Exception $e) {
            Log::error("Exception fetching Shopify orders", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Aggregate order data by campaign
     * 
     * @param array $orders
     * @param string $dateRange
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param string $channel
     * @return array
     */
    protected function aggregateOrderData($orders, $dateRange, $startDate, $endDate, $channel = 'facebook')
    {
        $campaigns = [];

        foreach ($orders as $order) {
            $campaignName = $this->extractCampaignName($order, $channel);
            $campaignId = $this->extractCampaignId($order);
            $detectedChannel = $this->detectChannel($order);
            
            $key = $campaignId ?: $campaignName;

            if (!isset($campaigns[$key])) {
                $campaigns[$key] = [
                    'campaign_id' => $campaignId,
                    'campaign_name' => $campaignName,
                    'date_range' => $dateRange,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'sales' => 0,
                    'orders' => 0,
                    'sessions' => 0,
                    'conversion_rate' => 0,
                    'ad_spend' => 0,
                    'roas' => 0,
                    'referring_channel' => $detectedChannel,
                    'traffic_type' => 'paid',
                    'country' => 'IN',
                ];
            }

            $campaigns[$key]['sales'] += floatval($order['total_price'] ?? 0);
            $campaigns[$key]['orders'] += 1;
        }

        // Calculate conversion rates and ROAS where applicable
        foreach ($campaigns as &$campaign) {
            if ($campaign['sessions'] > 0) {
                $campaign['conversion_rate'] = ($campaign['orders'] / $campaign['sessions']) * 100;
            }
            if ($campaign['ad_spend'] > 0) {
                $campaign['roas'] = $campaign['sales'] / $campaign['ad_spend'];
            }
        }

        return array_values($campaigns);
    }

    /**
     * Detect channel from order
     * 
     * @param array $order
     * @return string
     */
    protected function detectChannel($order)
    {
        $landingSite = $order['landing_site'] ?? '';
        $referringSite = $order['referring_site'] ?? '';
        $sourceName = $order['source_name'] ?? '';
        
        $isInstagram = stripos($landingSite, 'instagram') !== false 
            || stripos($landingSite, 'insta') !== false
            || stripos($landingSite, 'utm_source=instagram') !== false
            || stripos($referringSite, 'instagram') !== false
            || stripos($sourceName, 'instagram') !== false;
        
        return $isInstagram ? 'instagram' : 'facebook';
    }

    /**
     * Extract campaign name from order
     * 
     * @param array $order
     * @param string $channel
     * @return string
     */
    protected function extractCampaignName($order, $channel = 'facebook')
    {
        $landingSite = $order['landing_site'] ?? '';
        
        // Try to extract utm_campaign parameter
        if (preg_match('/utm_campaign=([^&]+)/', $landingSite, $matches)) {
            return urldecode($matches[1]);
        }

        // Try to extract fbclid for Facebook
        if ($channel === 'facebook' || $channel === 'both') {
            if (preg_match('/fbclid=([^&]+)/', $landingSite, $matches)) {
                return 'Facebook Campaign - ' . substr($matches[1], 0, 20);
            }
        }

        // Default based on channel
        if ($channel === 'instagram') {
            return 'Instagram - General';
        } elseif ($channel === 'both') {
            $detectedChannel = $this->detectChannel($order);
            return ucfirst($detectedChannel) . ' - General';
        }

        return 'Facebook - General';
    }

    /**
     * Extract campaign ID from order
     * 
     * @param array $order
     * @return string|null
     */
    protected function extractCampaignId($order)
    {
        $landingSite = $order['landing_site'] ?? '';
        
        // Try to extract campaign ID from UTM parameters
        if (preg_match('/utm_id=([^&]+)/', $landingSite, $matches)) {
            return urldecode($matches[1]);
        }

        if (preg_match('/campaign_id=([^&]+)/', $landingSite, $matches)) {
            return urldecode($matches[1]);
        }

        return null;
    }

    /**
     * Parse campaign data from API response
     * 
     * @param array $data
     * @param string $dateRange
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function parseCampaignData($data, $dateRange, $startDate, $endDate)
    {
        // This method would parse the actual Shopify marketing data
        // For now, we'll use the order-based approach
        return [];
    }

    /**
     * Get date range based on the period
     * 
     * @param string $dateRange
     * @return array
     */
    protected function getDateRange($dateRange)
    {
        $endDate = Carbon::yesterday();
        
        switch ($dateRange) {
            case '7_days':
                $startDate = Carbon::now()->subDays(7);
                break;
            case '30_days':
                $startDate = Carbon::now()->subDays(30);
                break;
            case '60_days':
                $startDate = Carbon::now()->subDays(60);
                break;
            default:
                $startDate = Carbon::now()->subDays(30);
        }

        return [
            'start' => $startDate,
            'end' => $endDate
        ];
    }
}
