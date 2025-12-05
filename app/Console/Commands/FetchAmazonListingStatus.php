<?php

namespace App\Console\Commands;

use App\Models\AmazonDatasheet;
use App\Models\ProductMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchAmazonListingStatus extends Command
{
    protected $signature = 'amazon:fetch-listing-status';
    protected $description = 'Fetch listing status for all SKUs from Amazon SP-API and store in amazon_datsheets table';

    public function handle()
    {
        $this->info('Starting Amazon listing status fetch...');

        // Get all active SKUs from product_masters
        $skus = ProductMaster::whereNull('deleted_at')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where('sku', 'NOT LIKE', '%PARENT%')
            ->pluck('sku')
            ->unique()
            ->values();

        if ($skus->isEmpty()) {
            $this->error('No SKUs found to process');
            return 1;
        }

        $this->info("Found {$skus->count()} SKUs to process");

        // Get access token
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->error('Failed to get Amazon access token');
            return 1;
        }

        $marketplaceId = env('SPAPI_MARKETPLACE_ID');
        $sellerId = env('AMAZON_SELLER_ID');
        $endpoint = env('SPAPI_ENDPOINT', 'https://sellingpartnerapi-na.amazon.com');
        
        $processed = 0;
        $updated = 0;
        $failed = 0;
        $allData = []; // Store all data here

        // Fetch all SKU statuses from Amazon
        $totalSkus = $skus->count();
        
        $this->info("Fetching listing status from Amazon for {$totalSkus} SKUs...");
        
        foreach ($skus as $index => $sku) {
            if ($index % 100 == 0) {
                $this->info("Progress: {$index}/{$totalSkus}");
            }
            
            try {
                $encodedSku = rawurlencode($sku);
                $url = "{$endpoint}/listings/2021-08-01/items/{$sellerId}/{$encodedSku}?marketplaceIds={$marketplaceId}";
                
                $response = Http::timeout(5)
                    ->withHeaders([
                        'x-amz-access-token' => $accessToken,
                        'Content-Type' => 'application/json',
                    ])
                    ->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Determine listing status
                    $listingStatus = 'INCOMPLETE';
                    
                    // Check summaries for status information
                    if (isset($data['summaries'][0]['status'][0])) {
                        $statusValue = $data['summaries'][0]['status'][0];
                        if ($statusValue === 'BUYABLE') {
                            $listingStatus = 'ACTIVE';
                        } elseif ($statusValue === 'DISCOVERABLE') {
                            $listingStatus = 'INACTIVE';
                        } else {
                            $listingStatus = $statusValue;
                        }
                    } elseif (isset($data['status'][0]['status'])) {
                        $statusValue = $data['status'][0]['status'];
                        if ($statusValue === 'BUYABLE') {
                            $listingStatus = 'ACTIVE';
                        } elseif ($statusValue === 'DISCOVERABLE') {
                            $listingStatus = 'INACTIVE';
                        } else {
                            $listingStatus = $statusValue;
                        }
                    }

                    // Store data for bulk insert
                    $allData[] = [
                        'sku' => $sku,
                        'listing_status' => $listingStatus
                    ];
                    
                    $updated++;
                    
                } else {
                    $failed++;
                }

                $processed++;

            } catch (\Exception $e) {
                Log::error('Amazon listing status fetch error', [
                    'sku' => $sku,
                    'error' => $e->getMessage()
                ]);
                $failed++;
                $processed++;
            }
        }
        
        // Now update database in one shot
        $this->info("\nUpdating database with all {$updated} records...");
        
        if (!empty($allData)) {
            $cases = [];
            $skuList = [];
            
            foreach ($allData as $data) {
                $sku = addslashes($data['sku']);
                $status = $data['listing_status'];
                $cases[] = "WHEN '{$sku}' THEN '{$status}'";
                $skuList[] = "'{$sku}'";
            }
            
            if (!empty($cases)) {
                $caseSql = implode(' ', $cases);
                $skuListSql = implode(',', $skuList);
                
                DB::statement("
                    UPDATE amazon_datsheets 
                    SET listing_status = CASE sku {$caseSql} END,
                        updated_at = NOW()
                    WHERE sku IN ({$skuListSql})
                ");
                
                $this->info("✓ Database updated successfully!");
            }
        }

        $this->info("\n=== Summary ===");
        $this->info("Total SKUs: {$skus->count()}");
        $this->info("Processed: {$processed}");
        $this->info("Updated: {$updated}");
        $this->info("Failed: {$failed}");

        return 0;
    }

    private function getAccessToken()
    {
        $clientId = env('SPAPI_CLIENT_ID');
        $clientSecret = env('SPAPI_CLIENT_SECRET');
        $refreshToken = env('SPAPI_REFRESH_TOKEN');

        if (!$clientId || !$clientSecret || !$refreshToken) {
            $this->error('Missing Amazon LWA credentials in .env file');
            return null;
        }

        try {
            $response = Http::timeout(15)->asForm()->post('https://api.amazon.com/auth/o2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if ($response->successful()) {
                return $response->json()['access_token'] ?? null;
            }

            $this->error('Token request failed: ' . $response->body());
            return null;

        } catch (\Exception $e) {
            $this->error('Token request exception: ' . $e->getMessage());
            return null;
        }
    }
}
