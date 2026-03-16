<?php

namespace App\Console\Commands;

use App\Models\ProductMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Aws\Credentials\Credentials;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Psr7\Request;

class PushTitlesToAmazon extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'titles:push-to-amazon {--sku=} {--all} {--limit=100}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push product title data to Amazon panel via API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Amazon Title Push...');
        $this->warn('Note: This will UPDATE product titles on Amazon Seller Central');

        // Get Amazon API configuration from existing .env variables
        $clientId = config('services.amazon_sp.client_id');
        $clientSecret = config('services.amazon_sp.client_secret');
        $refreshToken = config('services.amazon_sp.refresh_token');
        $sellerId = config('services.amazon_sp.seller_id');
        $marketplaceId = config('services.amazon_sp.marketplace_id');

        if (!$clientId || !$clientSecret || !$refreshToken || !$sellerId) {
            $this->error('Amazon SP-API credentials not configured properly in .env file');
            $this->error('Required: SPAPI_CLIENT_ID, SPAPI_CLIENT_SECRET, SPAPI_REFRESH_TOKEN, AMAZON_SELLER_ID');
            return Command::FAILURE;
        }

        $this->info("Seller ID: {$sellerId}");
        $this->info("Marketplace ID: {$marketplaceId}");

        // Build query for products - only those updated today
        $query = ProductMaster::whereNotNull('title150')
            ->where('title150', '!=', '')
            ->whereDate('updated_at', today());

        // Filter by SKU if provided
        if ($this->option('sku')) {
            $query->where('SKU', $this->option('sku'));
        }

        // Apply limit unless --all flag is used
        if (!$this->option('all')) {
            $limit = (int) $this->option('limit');
            $query->limit($limit);
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            $this->warn('No products found with title data updated today');
            return Command::SUCCESS;
        }

        $this->info("Found {$products->count()} products updated today to push");
        $this->newLine();

        // Get LWA access token
        $lwaToken = $this->getAccessToken($clientId, $clientSecret, $refreshToken);
        
        if (!$lwaToken) {
            $this->error('Failed to obtain Amazon LWA access token');
            return Command::FAILURE;
        }

        $this->info('✓ Obtained LWA access token');
        $this->newLine();

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        $progressBar = $this->output->createProgressBar($products->count());
        $progressBar->start();

        foreach ($products as $product) {
            try {
                $sku = $product->SKU;
                
                // Use Listings Items API PUT method (requires Product Listing role only)
                $endpoint = "https://sellingpartnerapi-na.amazon.com";
                $path = "/listings/2021-08-01/items/{$sellerId}/{$sku}";
                $url = $endpoint . $path;

                // Use PUT with full product data (works with Product Listing permission)
                $payload = [
                    'productType' => 'PRODUCT',
                    'requirements' => 'LISTING',
                    'attributes' => [
                        'item_name' => [
                            [
                                'value' => $product->title150,
                                'marketplace_id' => $marketplaceId
                            ]
                        ]
                    ]
                ];

                // Make PUT request to update title (try PUT instead of PATCH)
                $response = Http::withHeaders([
                    'x-amz-access-token' => $lwaToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->timeout(30)
                ->put($url, $payload);

                $statusCode = $response->status();

                // Amazon returns 202 (Accepted) for successful updates
                if ($statusCode == 200 || $statusCode == 202) {
                    $successCount++;
                    
                    // Update product with last sync timestamp
                    $product->amazon_last_sync = now();
                    $product->amazon_sync_status = 'success';
                    $product->amazon_sync_error = null;
                    $product->save();
                    
                } else {
                    $errorCount++;
                    $responseBody = $response->body();
                    $errorMsg = "SKU {$sku}: {$statusCode} - " . substr($responseBody, 0, 200);
                    $errors[] = $errorMsg;
                    
                    // Update error status
                    $product->amazon_sync_status = 'failed';
                    $product->amazon_sync_error = $responseBody;
                    $product->save();
                    
                    Log::error('Amazon API Error: ' . $errorMsg);
                }

            } catch (\Exception $e) {
                $errorCount++;
                $errorMsg = "SKU {$product->SKU}: " . $e->getMessage();
                $errors[] = $errorMsg;
                
                // Update error status
                $product->amazon_sync_status = 'failed';
                $product->amazon_sync_error = $e->getMessage();
                $product->save();
                
                Log::error('Amazon Push Exception: ' . $errorMsg);
            }

            $progressBar->advance();

            // Small delay to avoid rate limiting
            usleep(250000); // 0.25 seconds
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->info("✓ Successfully pushed: {$successCount}");
        
        if ($errorCount > 0) {
            $this->error("✗ Failed: {$errorCount}");
            
            if (count($errors) > 0) {
                $this->newLine();
                $this->error('First 10 errors:');
                foreach (array_slice($errors, 0, 10) as $error) {
                    $this->line("  - {$error}");
                }
                
                $this->newLine();
                $this->warn('If you see "Unauthorized" errors:');
                $this->line('1. Grant "Listings Items" role to your app in Seller Central');
                $this->line('   Go to: Seller Central > Apps & Services > Develop Apps');
                $this->line('2. Make sure the product exists on Amazon with this SKU');
                $this->line('3. Check that AMAZON_SELLER_ID matches your account');
            }
        }

        $this->newLine();
        $this->info('Amazon title push completed!');

        return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Get Amazon SP-API access token using LWA (Login with Amazon)
     */
    private function getAccessToken($clientId, $clientSecret, $refreshToken)
    {
        try {
            $response = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            }

            Log::error('Failed to get Amazon access token: ' . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error('Exception getting Amazon access token: ' . $e->getMessage());
            return null;
        }
    }
}
