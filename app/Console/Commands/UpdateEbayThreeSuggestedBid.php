<?php

namespace App\Console\Commands;

use App\Models\Ebay3GeneralReport;
use App\Models\Ebay3Metric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Exception;

class UpdateEbayThreeSuggestedBid extends Command
{
    protected $signature = 'ebay3:update-suggestedbid';
    protected $description = 'Bulk update eBay3 ad bids using suggested_bid percentages';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle() {  
        try {
            $this->info('Starting bulk eBay ad bid update...');

            $accessToken = $this->getEbayAccessToken();
            if (!$accessToken) {
                $this->error('Failed to obtain eBay access token.');
                return 1;
            }

            // Process ProductMaster records in chunks to prevent "Too many connections" error
            $chunkSize = 1000;
            $totalRecords = ProductMaster::whereNull('deleted_at')->count();
            
            if ($totalRecords === 0) {
                $this->info('No product masters found.');
                return 0;
            }
            
            $this->info("Processing {$totalRecords} product masters in chunks of {$chunkSize}...");
            
            $allSkus = collect();
            $processedCount = 0;
            
            // Collect all SKUs first using chunked processing
            ProductMaster::whereNull('deleted_at')
                ->orderBy("parent", "asc")
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy("sku", "asc")
                ->chunk($chunkSize, function ($productMasters) use (&$allSkus, &$processedCount, $totalRecords) {
                    $chunkSkus = $productMasters->pluck("sku")->filter()->unique();
                    $allSkus = $allSkus->merge($chunkSkus);
                    $processedCount += $productMasters->count();
                    $this->info("Processed {$processedCount}/{$totalRecords} product masters...");
                });
            
            $skus = $allSkus->unique()->values()->all();
            
            if (empty($skus)) {
                $this->info('No valid SKUs found in product masters.');
                return 0;
            }
            
            $this->info('Loading Shopify and eBay metrics data...');
            $shopifyData = ShopifySku::whereIn("sku", $skus)->get()->keyBy("sku");
            $ebayMetrics = Ebay3Metric::whereIn("sku", $skus)->get();
            
            if ($ebayMetrics->isEmpty()) {
                $this->info('No eBay metrics found for the SKUs.');
                return 0;
            }
        
        // Normalize SKUs by replacing non-breaking spaces with regular spaces for matching
        $ebayMetricsNormalized = $ebayMetrics->mapWithKeys(function($item) {
            $normalizedSku = str_replace(["\xC2\xA0", "\u{00A0}"], ' ', $item->sku);
            return [$normalizedSku => $item];
        });

        // Load campaign listings efficiently
        $this->info('Loading campaign listings...');
        $campaignListings = DB::connection('apicentral')
            ->table('ebay3_campaign_ads_listings')
            ->select('listing_id', 'campaign_id', 'bid_percentage')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->get()
            ->keyBy('listing_id')
            ->map(function ($item) {
                return (object) [
                    'listing_id' => $item->listing_id,
                    'campaign_id' => $item->campaign_id,
                    'bid_percentage' => $item->bid_percentage,
                    'new_bid' => null
                ];
            });

            if ($campaignListings->isEmpty()) {
                $this->info('No campaign listings found.');
                return 0;
            }

            // Get L7 clicks from ebaygeneral report
            $this->info('Loading eBay general report data...');
            $ebayGeneralL7 = Ebay3GeneralReport::select('listing_id', 'clicks')
                ->where('report_range', 'L7')
                ->get()
                ->keyBy('listing_id');
            
        // Process ProductMaster data in chunks and update campaign listings
        $this->info('Processing bid updates...');
        $updatedListings = 0;
        
        ProductMaster::whereNull('deleted_at')
            ->orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->chunk($chunkSize, function ($productMasters) use (
                $shopifyData, 
                $ebayMetricsNormalized, 
                $campaignListings, 
                $ebayGeneralL7, 
                &$updatedListings
            ) {
                foreach ($productMasters as $pm) {
                    $shopify = $shopifyData[$pm->sku] ?? null;
                    $ebayMetric = $ebayMetricsNormalized[$pm->sku] ?? null;

                    if ($ebayMetric && $ebayMetric->item_id && $campaignListings->has($ebayMetric->item_id)) {
                        $listing = $campaignListings[$ebayMetric->item_id];
                        $currentBid = (float) ($listing->bid_percentage ?? 0);
                        $l7ClicksData = $ebayGeneralL7->get($ebayMetric->item_id);
                        $l7Clicks = $l7ClicksData ? (int) ($l7ClicksData->clicks ?? 0) : 0;
                        
                        $newBid = $currentBid;
                        
                        if ($l7Clicks < 70) {
                            $newBid = $currentBid + 0.5;
                        } elseif ($l7Clicks > 140) {
                            $newBid = $currentBid - 0.5;
                        }
                        
                        // Apply 10% cap and 2% minimum
                        if ($newBid > 10) {
                            $newBid = 10;
                        }
                        
                        // Ensure bid doesn't go below 2
                        if ($newBid < 2) {
                            $newBid = 2;
                        }
                        
                        $listing->new_bid = $newBid;
                        $updatedListings++;
                    }
                }
            });
        
        $this->info("Updated bids for {$updatedListings} listings.");

        $groupedByCampaign = collect($campaignListings)->groupBy('campaign_id');

        if ($groupedByCampaign->isEmpty()) {
            $this->info('No campaign listings found.');
            return;
        }

        $client = new Client([
            'base_uri' => env('EBAY_BASE_URL', 'https://api.ebay.com/'),
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ],
        ]);

        foreach ($groupedByCampaign as $campaignId => $listings) {
            $requests = [];

            foreach ($listings as $listing) {
                if (isset($listing->new_bid)) {
                    $requests[] = [
                        'listingId' => $listing->listing_id,
                        'bidPercentage' => (string) $listing->new_bid
                    ];
                }
            }

            if (empty($requests)) {
                continue;
            }

            try {
                $response = $client->post(
                    "sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_bid_by_listing_id",
                    ['json' => ['requests' => $requests]]
                );
                $this->info("Campaign {$campaignId}: Updated " . json_encode($requests, JSON_PRETTY_PRINT) . " listings.");
                Log::info("eBay campaign {$campaignId} bulk update response: " . $response->getBody()->getContents());
            } catch (\Exception $e) {
                Log::error("Failed to update eBay campaign {$campaignId}: " . $e->getMessage());
                $this->error("Failed to update campaign {$campaignId}. Check logs.");
            }
        }

            $this->info('eBay ad bid update finished.');
            return 0;
            
        } catch (Exception $e) {
            Log::error('eBay bid update command failed: ' . $e->getMessage());
            $this->error('Command failed: ' . $e->getMessage());
            return 1;
        } catch (\Throwable $e) {
            Log::error('eBay bid update command failed with throwable: ' . $e->getMessage());
            $this->error('Command failed with error: ' . $e->getMessage());
            return 1;
        }
    }

    private function getEbayAccessToken()
    {
        try {
            if (Cache::has('ebay_access_token')) {
                return Cache::get('ebay_access_token');
            }

            $clientId = env('EBAY_3_APP_ID');
            $clientSecret = env('EBAY_3_CERT_ID');
            $refreshToken = env('EBAY_3_REFRESH_TOKEN');
            
            if (!$clientId || !$clientSecret || !$refreshToken) {
                throw new Exception('Missing eBay API credentials in environment variables');
            }
        $endpoint = "https://api.ebay.com/identity/v1/oauth2/token";

        $postFields = http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'scope' => 'https://api.ebay.com/oauth/api_scope/sell.marketing'
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/x-www-form-urlencoded",
                "Authorization: Basic " . base64_encode("$clientId:$clientSecret")
            ],
        ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new Exception('cURL Error: ' . $error);
            }
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception('HTTP Error: ' . $httpCode . ' Response: ' . $response);
            }

            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response: ' . json_last_error_msg());
            }

            if (isset($data['access_token'])) {
                $accessToken = $data['access_token'];
                $expiresIn = $data['expires_in'] ?? 7200;

                Cache::put('ebay_access_token', $accessToken, $expiresIn - 60);

                return $accessToken;
            }

            throw new Exception("Failed to refresh token: " . json_encode($data));
            
        } catch (Exception $e) {
            Log::error('Failed to get eBay access token: ' . $e->getMessage());
            throw $e;
        }
    }
}
