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

        $marketplaceId = config('services.amazon_sp.marketplace_id');
        $sellerId = config('services.amazon_sp.seller_id');
        $endpoint = config('services.amazon_sp.endpoint');
        
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
                
                $response = Http::timeout(30)
                    ->withHeaders([
                        'x-amz-access-token' => $accessToken,
                        'Content-Type' => 'application/json',
                    ])
                    ->get($url);

                // Handle rate limiting (429 Too Many Requests)
                if ($response->status() === 429) {
                    $retryAfter = $response->header('Retry-After', 1);
                    $this->warn("Rate limited. Waiting {$retryAfter} seconds...");
                    sleep((int)$retryAfter);
                    
                    // Retry once
                    $response = Http::timeout(30)
                        ->withHeaders([
                            'x-amz-access-token' => $accessToken,
                            'Content-Type' => 'application/json',
                        ])
                        ->get($url);
                }

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Determine listing status with comprehensive checking
                    $listingStatus = $this->determineListingStatus($data, $sku);

                    // Store data for bulk insert
                    $allData[] = [
                        'sku' => $sku,
                        'listing_status' => $listingStatus
                    ];
                    
                    $updated++;
                    
                } else {
                    $statusCode = $response->status();
                    
                    // Don't count 404 (not found) as a failure - SKU might not be listed
                    if ($statusCode === 404) {
                        // SKU not found in Amazon listings - this is expected for some SKUs
                        $allData[] = [
                            'sku' => $sku,
                            'listing_status' => 'NOT_LISTED'
                        ];
                        $updated++;
                    } else {
                        // Only log non-404 errors
                        Log::warning('Amazon listing status API error', [
                            'sku' => $sku,
                            'status_code' => $statusCode
                        ]);
                        $failed++;
                    }
                }

                $processed++;
                
                // Small delay to avoid rate limiting (50ms between requests)
                usleep(50000);

            } catch (\Exception $e) {
                Log::error('Amazon listing status fetch error', [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $failed++;
                $processed++;
            }
        }
        
        // Now update database using upsert for better reliability
        $this->info("\nUpdating database with all {$updated} records...");
        
        if (!empty($allData)) {
            $chunkSize = 500;
            $chunks = array_chunk($allData, $chunkSize);
            $totalUpdated = 0;
            
            foreach ($chunks as $chunkIndex => $chunk) {
                try {
                    // Use upsert to handle both updates and inserts
                    foreach ($chunk as $data) {
                        AmazonDatasheet::updateOrCreate(
                            ['sku' => $data['sku']],
                            [
                                'listing_status' => $data['listing_status'],
                                'updated_at' => now()
                            ]
                        );
                    }
                    
                    $totalUpdated += count($chunk);
                    $this->info("  Processed chunk " . ($chunkIndex + 1) . "/" . count($chunks) . " ({$totalUpdated}/{$updated} records)");
                    
                } catch (\Exception $e) {
                    Log::error('Error updating amazon_datsheets', [
                        'chunk_index' => $chunkIndex,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->error("Error processing chunk " . ($chunkIndex + 1) . ": " . $e->getMessage());
                    
                    // Fallback: try individual updates
                    foreach ($chunk as $data) {
                        try {
                            AmazonDatasheet::updateOrCreate(
                                ['sku' => $data['sku']],
                                [
                                    'listing_status' => $data['listing_status'],
                                    'updated_at' => now()
                                ]
                            );
                        } catch (\Exception $individualError) {
                            Log::error('Error updating individual record', [
                                'sku' => $data['sku'],
                                'error' => $individualError->getMessage()
                            ]);
                        }
                    }
                }
            }
            
            $this->info("✓ Database updated successfully! Total records processed: {$totalUpdated}");
        }

        $this->info("\n=== Summary ===");
        $this->info("Total SKUs: {$skus->count()}");
        $this->info("Processed: {$processed}");
        $this->info("Updated: {$updated}");
        $this->info("Failed: {$failed}");

        return 0;
    }

    /**
     * Determine listing status from Amazon API response
     * Handles multiple response formats and status locations
     */
    private function determineListingStatus(array $data, string $sku): string
    {
        $listingStatus = 'INCOMPLETE';
        $statusFound = false;
        $allStatusValues = []; // Collect all status values found for debugging
        
        // Method 1: Check summaries array (most common location) - PRIORITIZE THIS
        if (isset($data['summaries']) && is_array($data['summaries']) && !empty($data['summaries'])) {
            foreach ($data['summaries'] as $summary) {
                // Check if status is an array
                if (isset($summary['status']) && is_array($summary['status']) && !empty($summary['status'])) {
                    foreach ($summary['status'] as $statusItem) {
                        $allStatusValues[] = $statusItem;
                        // Prioritize BUYABLE status
                        if (strtoupper($statusItem) === 'BUYABLE' || strtoupper($statusItem) === 'BUYABLE_BY_QUANTITY') {
                            $listingStatus = 'ACTIVE';
                            $statusFound = true;
                            break 2; // Break out of both loops
                        }
                    }
                    // If no BUYABLE found, use first status
                    if (!$statusFound) {
                        $statusValue = $summary['status'][0];
                        $listingStatus = $this->mapStatusValue($statusValue);
                        $statusFound = true;
                        break;
                    }
                }
                // Check if status is a string
                elseif (isset($summary['status']) && is_string($summary['status'])) {
                    $allStatusValues[] = $summary['status'];
                    $listingStatus = $this->mapStatusValue($summary['status']);
                    $statusFound = true;
                    break;
                }
            }
        }
        
        // Method 2: Check top-level status array
        if (!$statusFound && isset($data['status']) && is_array($data['status']) && !empty($data['status'])) {
            foreach ($data['status'] as $statusItem) {
                if (is_array($statusItem) && isset($statusItem['status'])) {
                    $statusValue = $statusItem['status'];
                    $allStatusValues[] = $statusValue;
                    // Prioritize BUYABLE
                    if (strtoupper($statusValue) === 'BUYABLE' || strtoupper($statusValue) === 'BUYABLE_BY_QUANTITY') {
                        $listingStatus = 'ACTIVE';
                        $statusFound = true;
                        break;
                    }
                } elseif (is_string($statusItem)) {
                    $allStatusValues[] = $statusItem;
                    // Prioritize BUYABLE
                    if (strtoupper($statusItem) === 'BUYABLE' || strtoupper($statusItem) === 'BUYABLE_BY_QUANTITY') {
                        $listingStatus = 'ACTIVE';
                        $statusFound = true;
                        break;
                    }
                }
            }
            
            // If we didn't find BUYABLE, use first status
            if (!$statusFound && !empty($allStatusValues)) {
                $listingStatus = $this->mapStatusValue($allStatusValues[0]);
                $statusFound = true;
            }
        }
        
        // Method 3: Check for marketplace-specific status
        if (!$statusFound && isset($data['marketplaceStatuses']) && is_array($data['marketplaceStatuses'])) {
            foreach ($data['marketplaceStatuses'] as $marketplaceStatus) {
                if (isset($marketplaceStatus['status'])) {
                    $statusValue = is_array($marketplaceStatus['status']) 
                        ? ($marketplaceStatus['status'][0] ?? null)
                        : $marketplaceStatus['status'];
                    
                    if ($statusValue) {
                        $allStatusValues[] = $statusValue;
                        // Prioritize BUYABLE
                        if (strtoupper($statusValue) === 'BUYABLE' || strtoupper($statusValue) === 'BUYABLE_BY_QUANTITY') {
                            $listingStatus = 'ACTIVE';
                            $statusFound = true;
                            break;
                        }
                    }
                }
            }
            
            // If we didn't find BUYABLE, use first marketplace status
            if (!$statusFound && !empty($allStatusValues)) {
                $listingStatus = $this->mapStatusValue($allStatusValues[0]);
                $statusFound = true;
            }
        }
        
        // Method 4: Check items array (sometimes status is nested in items)
        if (!$statusFound && isset($data['items']) && is_array($data['items']) && !empty($data['items'])) {
            foreach ($data['items'] as $item) {
                if (isset($item['status'])) {
                    $statusValue = is_array($item['status']) 
                        ? ($item['status'][0] ?? null)
                        : $item['status'];
                    
                    if ($statusValue) {
                        $allStatusValues[] = $statusValue;
                        if (strtoupper($statusValue) === 'BUYABLE' || strtoupper($statusValue) === 'BUYABLE_BY_QUANTITY') {
                            $listingStatus = 'ACTIVE';
                            $statusFound = true;
                            break;
                        }
                    }
                }
            }
        }
        
        // Method 5: Check for buyBoxEligible or other indicators of active status
        // Sometimes listings are active but status field might not reflect it correctly
        if (!$statusFound || $listingStatus === 'INACTIVE') {
            // Check for buyBoxEligible flag
            if (isset($data['buyBoxEligible']) && $data['buyBoxEligible'] === true) {
                $listingStatus = 'ACTIVE';
                $statusFound = true;
            }
            
            // Check summaries for buyBoxEligible
            if (isset($data['summaries']) && is_array($data['summaries'])) {
                foreach ($data['summaries'] as $summary) {
                    if (isset($summary['buyBoxEligible']) && $summary['buyBoxEligible'] === true) {
                        $listingStatus = 'ACTIVE';
                        $statusFound = true;
                        break;
                    }
                    // Check for availability
                    if (isset($summary['availability']) && 
                        (stripos($summary['availability'], 'in stock') !== false || 
                         stripos($summary['availability'], 'available') !== false)) {
                        $listingStatus = 'ACTIVE';
                        $statusFound = true;
                        break;
                    }
                }
            }
            
            // Check if there are no blocking issues and listing has inventory
            if (isset($data['issues']) && is_array($data['issues'])) {
                $hasOnlyWarnings = true;
                $hasErrors = false;
                foreach ($data['issues'] as $issue) {
                    $severity = strtoupper($issue['severity'] ?? '');
                    if ($severity === 'ERROR') {
                        $hasErrors = true;
                        $hasOnlyWarnings = false;
                        break;
                    }
                }
                // If no errors and we have inventory-related data, might be active
                if (!$hasErrors && isset($data['summaries'][0])) {
                    // Check if we have any positive indicators
                    $summary = $data['summaries'][0];
                    if (isset($summary['asin']) || isset($summary['productType'])) {
                        // Has ASIN and product type suggests it's a real listing
                        // If status is still INACTIVE but has these, might be misclassified
                        if ($listingStatus === 'INACTIVE' && !$statusFound) {
                            // Default to ACTIVE if we have listing data but no clear status
                            $listingStatus = 'ACTIVE';
                            $statusFound = true;
                        }
                    }
                }
            }
        }
        
        // REMOVED: Don't downgrade ACTIVE status based on issues
        // A listing can be ACTIVE even with warnings. Only errors that truly block the listing
        // should affect status, and those are usually reflected in the status field itself.
        
        // Final fallback: If we have listing data but status is unclear, check for positive indicators
        if (!$statusFound || ($listingStatus === 'INCOMPLETE' && !empty($data))) {
            // If we have summaries with data, it means the listing exists
            if (isset($data['summaries']) && is_array($data['summaries']) && !empty($data['summaries'])) {
                $summary = $data['summaries'][0];
                // If listing has ASIN and product info, it's likely active
                if (isset($summary['asin']) && !empty($summary['asin'])) {
                    // Check if there are no blocking errors
                    $hasBlockingErrors = false;
                    if (isset($data['issues']) && is_array($data['issues'])) {
                        foreach ($data['issues'] as $issue) {
                            $severity = strtoupper($issue['severity'] ?? '');
                            $code = strtoupper($issue['code'] ?? '');
                            // Only treat as blocking if it's a true error that prevents purchase
                            if ($severity === 'ERROR' && 
                                (stripos($code, 'BLOCK') !== false || 
                                 stripos($code, 'SUPPRESS') !== false ||
                                 stripos($code, 'INVALID') !== false)) {
                                $hasBlockingErrors = true;
                                break;
                            }
                        }
                    }
                    
                    // If no blocking errors and listing exists, default to ACTIVE
                    if (!$hasBlockingErrors) {
                        $listingStatus = 'ACTIVE';
                        $statusFound = true;
                    }
                }
            }
        }
        
        // Only log if we couldn't determine status at all
        if (!$statusFound) {
            Log::warning('Could not determine listing status from response', [
                'sku' => $sku,
                'response_keys' => array_keys($data),
                'default_status' => $listingStatus
            ]);
        }
        
        return $listingStatus;
    }
    
    /**
     * Map Amazon API status values to our database status values
     */
    private function mapStatusValue(string $statusValue): string
    {
        $statusValue = strtoupper(trim($statusValue));
        
        // Map Amazon status values to our status values
        // ACTIVE statuses (buyable/available)
        $activeStatuses = [
            'BUYABLE',
            'BUYABLE_BY_QUANTITY',
            'ACTIVE',
            'LIVE',
            'PUBLISHED',
        ];
        
        // INACTIVE statuses (not buyable)
        $inactiveStatuses = [
            'DISCOVERABLE',
            'INELIGIBLE',
            'INVALID',
            'OUT_OF_STOCK',
            'UNBUYABLE',
            'INACTIVE',
            'SUPPRESSED',
            'STOPPED',
        ];
        
        // INCOMPLETE statuses (needs attention)
        $incompleteStatuses = [
            'INCOMPLETE',
            'DRAFT',
            'PENDING',
        ];
        
        // Check if status matches any category
        if (in_array($statusValue, $activeStatuses)) {
            return 'ACTIVE';
        } elseif (in_array($statusValue, $inactiveStatuses)) {
            return 'INACTIVE';
        } elseif (in_array($statusValue, $incompleteStatuses)) {
            return 'INCOMPLETE';
        }
        
        // Default: if it contains "BUY" or "ACTIVE", treat as ACTIVE
        if (stripos($statusValue, 'BUY') !== false || stripos($statusValue, 'ACTIVE') !== false) {
            return 'ACTIVE';
        }
        
        // Default: if it contains "INACTIVE", "INVALID", "STOP", "SUPPRESS", treat as INACTIVE
        if (stripos($statusValue, 'INACTIVE') !== false || 
            stripos($statusValue, 'INVALID') !== false || 
            stripos($statusValue, 'STOP') !== false ||
            stripos($statusValue, 'SUPPRESS') !== false) {
            return 'INACTIVE';
        }
        
        // Return original if we can't determine
        return $statusValue;
    }

    private function getAccessToken()
    {
        $clientId = config('services.amazon_sp.client_id');
        $clientSecret = config('services.amazon_sp.client_secret');
        $refreshToken = config('services.amazon_sp.refresh_token');

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
