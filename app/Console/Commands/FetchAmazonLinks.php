<?php

namespace App\Console\Commands;

use App\Models\AmazonDataView;
use App\Models\AmazonDatasheet;
use App\Models\ProductMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchAmazonLinks extends Command
{
    protected $signature = 'amazon:fetch-links {--sku= : Specific SKU to update} {--use-api : Use Amazon API to fetch links}';
    protected $description = 'Fetch and update buyer/seller links for Amazon listings';

    public function handle()
    {
        $sku = $this->option('sku');
        $useApi = $this->option('use-api');

        if ($sku) {
            // Update single SKU
            $this->info("Updating links for SKU: {$sku}");
            $result = $this->updateLinksForSku($sku, $useApi);
            
            if ($result['success']) {
                $this->info("✓ Successfully updated links for {$sku}");
                $this->line("  Buyer Link: " . ($result['buyer_link'] ?? 'N/A'));
                $this->line("  Seller Link: " . ($result['seller_link'] ?? 'N/A'));
            } else {
                $this->error("✗ Failed to update links: " . $result['message']);
                return 1;
            }
        } else {
            // Update all SKUs
            $this->info('Fetching links for all Amazon listings...');
            
            $skus = ProductMaster::whereNull('deleted_at')
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->where('sku', 'NOT LIKE', '%PARENT%')
                ->pluck('sku')
                ->unique()
                ->values();

            $total = $skus->count();
            $updated = 0;
            $failed = 0;
            $skipped = 0;

            $bar = $this->output->createProgressBar($total);
            $bar->start();

            foreach ($skus as $currentSku) {
                $result = $this->updateLinksForSku($currentSku, $useApi);
                
                if ($result['success']) {
                    $updated++;
                } elseif ($result['skipped'] ?? false) {
                    $skipped++;
                } else {
                    $failed++;
                }
                
                $bar->advance();
                
                // Small delay to avoid rate limiting when using API
                if ($useApi) {
                    usleep(100000); // 100ms
                }
            }

            $bar->finish();
            $this->newLine(2);
            
            $this->info("=== Summary ===");
            $this->info("Total SKUs: {$total}");
            $this->info("Updated: {$updated}");
            $this->info("Skipped: {$skipped}");
            $this->info("Failed: {$failed}");
        }

        return 0;
    }

    /**
     * Update links for a specific SKU
     */
    private function updateLinksForSku($sku, $useApi = false)
    {
        try {
            $asin = null;
            $buyerLink = null;
            $sellerLink = null;

            // Get ASIN from amazon_datsheets
            $amazonSheet = AmazonDatasheet::where('sku', $sku)->first();
            if ($amazonSheet && $amazonSheet->asin) {
                $asin = $amazonSheet->asin;
            }

            // If using API and ASIN not found, try to fetch from API
            if ($useApi && !$asin) {
                $apiResult = $this->fetchLinksFromApi($sku);
                if ($apiResult['success']) {
                    $asin = $apiResult['asin'] ?? null;
                    $buyerLink = $apiResult['buyer_link'] ?? null;
                    $sellerLink = $apiResult['seller_link'] ?? null;
                }
            }

            // Generate links from ASIN if we have it
            if ($asin && (!$buyerLink || !$sellerLink)) {
                if (!$buyerLink) {
                    $buyerLink = "https://www.amazon.com/dp/{$asin}";
                }
                if (!$sellerLink) {
                    // Seller Central link format
                    $sellerLink = "https://sellercentral.amazon.com/inventory/ref=xx_invmgr_dnav_xx?asin={$asin}";
                }
            }

            // If no ASIN found, skip
            if (!$asin) {
                return [
                    'success' => false,
                    'skipped' => true,
                    'message' => 'ASIN not found for SKU'
                ];
            }

            // Update links in amazon_data_view
            $status = AmazonDataView::where('sku', $sku)->first();
            $existing = $status ? $status->value : [];

            $existing['buyer_link'] = $buyerLink;
            $existing['seller_link'] = $sellerLink;

            AmazonDataView::updateOrCreate(
                ['sku' => $sku],
                ['value' => $existing]
            );

            return [
                'success' => true,
                'asin' => $asin,
                'buyer_link' => $buyerLink,
                'seller_link' => $sellerLink
            ];

        } catch (\Exception $e) {
            Log::error('Error updating links for SKU', [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Fetch links from Amazon API
     */
    private function fetchLinksFromApi($sku)
    {
        try {
            $sellerId = config('services.amazon_sp.seller_id');
            $marketplaceId = config('services.amazon_sp.marketplace_id');
            $endpoint = config('services.amazon_sp.endpoint');

            if (!$sellerId) {
                return ['success' => false, 'message' => 'Seller ID not configured'];
            }

            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return ['success' => false, 'message' => 'Failed to get access token'];
            }

            // Try different SKU formats
            $variations = [$sku, strtoupper($sku), strtolower($sku), trim($sku)];
            
            foreach ($variations as $skuVariation) {
                $encodedSku = rawurlencode($skuVariation);
                $url = "{$endpoint}/listings/2021-08-01/items/{$sellerId}/{$encodedSku}?marketplaceIds={$marketplaceId}";

                $response = \Illuminate\Support\Facades\Http::timeout(30)
                    ->withHeaders([
                        'x-amz-access-token' => $accessToken,
                        'Content-Type' => 'application/json',
                    ])
                    ->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    $asin = null;

                    if (isset($data['summaries'][0]['asin'])) {
                        $asin = $data['summaries'][0]['asin'];
                    } elseif (isset($data['attributes']['identifiers'][0]['marketplace_asin']['asin'])) {
                        $asin = $data['attributes']['identifiers'][0]['marketplace_asin']['asin'];
                    }

                    if ($asin) {
                        return [
                            'success' => true,
                            'asin' => $asin,
                            'buyer_link' => "https://www.amazon.com/dp/{$asin}",
                            'seller_link' => "https://sellercentral.amazon.com/inventory/ref=xx_invmgr_dnav_xx?asin={$asin}"
                        ];
                    }
                }
            }

            return ['success' => false, 'message' => 'SKU not found in Amazon API'];

        } catch (\Exception $e) {
            Log::error('Error fetching links from API', [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'message' => 'API Error: ' . $e->getMessage()];
        }
    }

    /**
     * Get Amazon access token
     */
    private function getAccessToken()
    {
        $clientId = config('services.amazon_sp.client_id');
        $clientSecret = config('services.amazon_sp.client_secret');
        $refreshToken = config('services.amazon_sp.refresh_token');

        if (!$clientId || !$clientSecret || !$refreshToken) {
            return null;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->asForm()
                ->post('https://api.amazon.com/auth/o2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);

            if ($response->successful()) {
                return $response->json()['access_token'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
