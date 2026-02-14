<?php

namespace App\Console\Commands;

use App\Models\Ebay3GeneralReport;
use App\Models\Ebay3Metric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Exception;

class UpdateEbayThreeSuggestedBid extends Command
{
    protected $signature = 'ebay3:update-suggestedbid {--dry-run : Run without making actual API calls}';
    protected $description = 'Bulk update eBay3 ad bids using suggested_bid percentages';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle() {  
        try {
            $dryRun = $this->option('dry-run');
            
            if ($dryRun) {
                $this->warn('=== DRY RUN MODE - No actual changes will be made ===');
            }
            
            $this->info('Starting bulk eBay ad bid update...');

            $accessToken = null;
            if (!$dryRun) {
                $accessToken = $this->getEbayAccessToken();
                if (!$accessToken) {
                    $this->error('Failed to obtain eBay access token.');
                    return 1;
                }
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
            
            // Check database connection
            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                return 1;
            }

            $this->info('Loading Shopify and eBay metrics data...');
            $shopifyData = [];
            $ebayMetrics = collect();
            
            if (!empty($skus)) {
                $shopifyData = ShopifySku::whereIn("sku", $skus)->get()->keyBy("sku");
                $ebayMetrics = Ebay3Metric::whereIn("sku", $skus)->get();
            }
            DB::connection()->disconnect();
            
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
            ->select('listing_id', 'campaign_id', 'bid_percentage', 'suggested_bid')
            ->where('funding_strategy', 'COST_PER_SALE')
            ->get()
            ->keyBy('listing_id')
            ->map(function ($item) {
                return (object) [
                    'listing_id' => $item->listing_id,
                    'campaign_id' => $item->campaign_id,
                    'bid_percentage' => $item->bid_percentage,
                    'suggested_bid' => $item->suggested_bid,
                    'new_bid' => null
                ];
            });

            if ($campaignListings->isEmpty()) {
                $this->info('No campaign listings found.');
                return 0;
            }

            // Get L30 data (clicks) from ebaygeneral report for SCVR calculation
            $this->info('Loading eBay general report data...');
            $ebayGeneralL30 = Ebay3GeneralReport::select('listing_id', 'clicks')
                ->where('report_range', 'L30')
                ->get()
                ->keyBy('listing_id');
            
        // Process ProductMaster data in chunks and update campaign listings
        $this->info('Processing bid updates based on L7_VIEWS...');
        $updatedListings = 0;
        
        ProductMaster::whereNull('deleted_at')
            ->orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->chunk($chunkSize, function ($productMasters) use (
                $shopifyData, 
                $ebayMetricsNormalized, 
                $campaignListings, 
                $ebayGeneralL30, 
                &$updatedListings
            ) {
                foreach ($productMasters as $pm) {
                    $shopify = $shopifyData[$pm->sku] ?? null;
                    $ebayMetric = $ebayMetricsNormalized[$pm->sku] ?? null;

                    if ($ebayMetric && $ebayMetric->item_id && $campaignListings->has($ebayMetric->item_id)) {
                        $listing = $campaignListings[$ebayMetric->item_id];
                        $l30Data = $ebayGeneralL30->get($ebayMetric->item_id);
                        
                        // Get L7_VIEWS for SBID calculation
                        $l7Views = (int) ($ebayMetric->l7_views ?? 0);
                        
                        // Get ESBID (suggested bid from bid_percentage in campaign listing)
                        $esbid = (float) ($listing->suggested_bid ?? 0);
                        
                        // Calculate SBID based on L7_VIEWS ranges
                        $newBid = 2; // Default minimum
                        
                        if ($l7Views >= 0 && $l7Views < 50) {
                            // 0-50: use ESBID
                            $newBid = $esbid;
                        } elseif ($l7Views >= 50 && $l7Views < 100) {
                            $newBid = 10;
                        } elseif ($l7Views >= 100 && $l7Views < 150) {
                            $newBid = 9;
                        } elseif ($l7Views >= 150 && $l7Views < 200) {
                            $newBid = 8;
                        } elseif ($l7Views >= 200 && $l7Views < 250) {
                            $newBid = 7;
                        } elseif ($l7Views >= 250 && $l7Views < 300) {
                            $newBid = 6;
                        } elseif ($l7Views >= 300 && $l7Views < 350) {
                            $newBid = 5;
                        } elseif ($l7Views >= 350 && $l7Views < 400) {
                            $newBid = 4;
                        } elseif ($l7Views >= 400) {
                            $newBid = 3;
                        } else {
                            // Fallback: use ESBID
                            $newBid = $esbid;
                        }

                        // Cap newBid to maximum of 15
                        $newBid = min($newBid, 15);
                        
                        $listing->new_bid = $newBid;
                        $listing->sku = $pm->sku; // Store SKU for logging
                        $this->info("SKU: {$pm->sku} | Listing ID: {$ebayMetric->item_id} | Calculated SBID: {$newBid} | L7_VIEWS: {$l7Views}");
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

        if ($dryRun) {
            $this->info("\n=== DRY RUN SUMMARY ===");
            $totalRequests = 0;
            foreach ($groupedByCampaign as $campaignId => $listings) {
                $requests = [];
                $seenListingIds = [];

                foreach ($listings as $listing) {
                    if (isset($listing->new_bid) && $listing->new_bid > 0) {
                        if (isset($seenListingIds[$listing->listing_id])) {
                            $this->warn("Duplicate listing_id {$listing->listing_id} found. SKU: " . ($listing->sku ?? 'unknown') . " | Previous bid: {$seenListingIds[$listing->listing_id]}, New bid: {$listing->new_bid}");
                        }
                        $seenListingIds[$listing->listing_id] = $listing->new_bid;
                        
                        $requests[] = [
                            'listingId' => $listing->listing_id,
                            'bidPercentage' => (string) $listing->new_bid
                        ];
                        $sku = $listing->sku ?? 'unknown';
                        $this->info("[DRY RUN] Would send to eBay - SKU: {$sku} | Listing ID: {$listing->listing_id} | Bid Percentage: {$listing->new_bid}");
                    }
                }

                if (!empty($requests)) {
                    $totalRequests += count($requests);
                    $this->info("[DRY RUN] Campaign {$campaignId}: Would send " . count($requests) . " bid updates to eBay API");
                }
            }
            $this->info("\n[DRY RUN] Total: {$totalRequests} bid updates would be sent across " . $groupedByCampaign->count() . " campaign(s)");
            $this->warn("\n=== DRY RUN COMPLETE - No actual changes were made ===");
            return 0;
        }

        $client = new Client([
            'base_uri' => config('services.ebay.base_url'),
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ],
        ]);

        foreach ($groupedByCampaign as $campaignId => $listings) {
            $requests = [];
            $seenListingIds = []; // Track to avoid duplicates

            foreach ($listings as $listing) {
                if (isset($listing->new_bid) && $listing->new_bid > 0) {
                    // Avoid duplicate listing_ids in same campaign
                    if (isset($seenListingIds[$listing->listing_id])) {
                        $this->warn("Duplicate listing_id {$listing->listing_id} found. SKU: " . ($listing->sku ?? 'unknown') . " | Previous bid: {$seenListingIds[$listing->listing_id]}, New bid: {$listing->new_bid}");
                        // Use the latest bid value
                    }
                    $seenListingIds[$listing->listing_id] = $listing->new_bid;
                    
                    $requests[] = [
                        'listingId' => $listing->listing_id,
                        'bidPercentage' => (string) $listing->new_bid
                    ];
                    $sku = $listing->sku ?? 'unknown';
                    $this->info("Sending to eBay - SKU: {$sku} | Listing ID: {$listing->listing_id} | Bid Percentage: {$listing->new_bid}");
                }
            }

            if (empty($requests)) {
                continue;
            }

            try {
                $this->info("Campaign {$campaignId}: Sending " . count($requests) . " bid updates to eBay API...");
                $response = $client->post(
                    "sell/marketing/v1/ad_campaign/{$campaignId}/bulk_update_ads_bid_by_listing_id",
                    ['json' => ['requests' => $requests]]
                );
                
                $responseBody = $response->getBody()->getContents();
                $statusCode = $response->getStatusCode();
                
                $this->info("Campaign {$campaignId}: API Response Status: {$statusCode}");
                if ($statusCode === 200 || $statusCode === 207) {
                    $this->info("Campaign {$campaignId}: Successfully updated " . count($requests) . " listings.");
                } else {
                    $this->warn("Campaign {$campaignId}: Response: " . substr($responseBody, 0, 200));
                }
            } catch (\Exception $e) {
                $this->error("Failed to update campaign {$campaignId}: " . $e->getMessage());
            }
        }

            $this->info('eBay ad bid update finished.');
            return 0;
            
        } catch (Exception $e) {
            $this->error('Command failed: ' . $e->getMessage());
            return 1;
        } catch (\Throwable $e) {
            $this->error('Command failed with error: ' . $e->getMessage());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    private function getEbayAccessToken()
    {
        try {
            if (Cache::has('ebay3_access_token')) {
                return Cache::get('ebay3_access_token');
            }

            $clientId = config('services.ebay3.app_id');
            $clientSecret = config('services.ebay3.cert_id');
            $refreshToken = config('services.ebay3.refresh_token');
            
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

                Cache::put('ebay3_access_token', $accessToken, $expiresIn - 60);

                return $accessToken;
            }

            throw new Exception("Failed to refresh token: " . json_encode($data));
            
        } catch (Exception $e) {
            throw $e;
        }
    }
}
