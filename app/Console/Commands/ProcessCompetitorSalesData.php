<?php

namespace App\Console\Commands;

use App\Models\AmazonSkuCompetitor;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessCompetitorSalesData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-competitor-sales-data 
                            {--limit= : Limit number of records to process for testing}
                            {--asin= : Test with specific ASIN}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch competitor ASIN sales data from JungleScout API and update amazon_sku_competitors table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        $testAsin = $this->option('asin');
        
        // Fetch unique ASINs from amazon_sku_competitors table
        $query = \DB::table('amazon_sku_competitors')
            ->whereNotNull('asin')
            ->where('asin', '!=', '')
            ->select('id', 'asin', 'sku', 'marketplace');
        
        if ($testAsin) {
            $query->where('asin', $testAsin);
            $this->info("TESTING MODE: Processing only ASIN: $testAsin");
        } elseif ($limit) {
            $query->limit($limit);
            $this->info("TESTING MODE: Processing only $limit records");
        }
        
        $competitors = $query->get()->toArray();
        
        $competitors = array_map(function($item) {
            return (array) $item;
        }, $competitors);
        
        $this->info('Fetched ' . count($competitors) . ' competitor ASINs from amazon_sku_competitors table');
        
        if (empty($competitors)) {
            $this->info('No competitors found to process.');
            return;
        }
        
        try {
            // Process in chunks of 100
            $chunks = array_chunk($competitors, 100);
            $processedCount = 0;
            $updatedCount = 0;

            foreach ($chunks as $chunkIndex => $chunk) {
                $asins = array_column($chunk, 'asin');
                
                $this->info('Processing chunk ' . ($chunkIndex + 1) . ' of ' . count($chunks) . ' (' . count($asins) . ' ASINs)');
                
                // Call JungleScout API
                $apiResponse = Http::withOptions(['verify' => false])
                    ->withHeaders([
                        'Authorization' => config('services.junglescout.key_with_title'),
                        'Content-Type'  => 'application/vnd.api+json',
                        'Accept'        => 'application/vnd.junglescout.v1+json',
                        'X-API-Type'    => 'junglescout',
                    ])
                    ->post('https://developer.junglescout.com/api/product_database_query?marketplace=us', [
                        'data' => [
                            'type' => 'product_database_query',
                            'attributes' => [
                                'include_keywords' => $asins,
                            ],
                        ],
                    ]);

                if (!$apiResponse->ok()) {
                    $this->error('JungleScout API failed with status: ' . $apiResponse->status());
                    Log::error('JungleScout API failed for chunk ' . ($chunkIndex + 1), [
                        'status' => $apiResponse->status(),
                        'response' => $apiResponse->body()
                    ]);
                    continue; // Skip this chunk and continue with next
                }

                $products = $apiResponse->json()['data'] ?? [];
                $this->info('Received ' . count($products) . ' products from JungleScout API');

                foreach ($products as $product) {
                    $asinId = $product['id'] ?? null;
                    $attributes = $product['attributes'] ?? [];

                    if (!$asinId) continue;

                    // Clean ASIN (remove 'us/' prefix if present)
                    $cleanAsin = str_replace('us/', '', $asinId);
                    
                    // Find matching competitor records
                    $matchingCompetitors = collect($chunk)->where('asin', $cleanAsin);

                    if ($matchingCompetitors->isEmpty()) continue;

                    // Prepare sales data
                    $salesData = [
                        'monthly_revenue' => $attributes['approximate_30_day_revenue'] ?? null,
                        'monthly_units_sold' => $attributes['approximate_30_day_units_sold'] ?? null,
                        'buy_box_owner' => $attributes['buy_box_owner'] ?? null,
                        'seller_type_js' => $attributes['seller_type'] ?? null,
                        'sales_data_updated_at' => now(),
                    ];

                    // Update all matching competitor records
                    foreach ($matchingCompetitors as $competitor) {
                        AmazonSkuCompetitor::where('id', $competitor['id'])
                            ->update($salesData);
                        $updatedCount++;
                    }

                    $processedCount++;
                }

                // Add a small delay between chunks to avoid rate limiting
                if ($chunkIndex < count($chunks) - 1) {
                    sleep(1);
                }
            }

            $this->info("Processing completed successfully!");
            $this->info("Processed: $processedCount unique ASINs");
            $this->info("Updated: $updatedCount competitor records");
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('Competitor sales data processing error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            // Send email notification (fail silently if mail not configured)
            try {
                $adminEmail = config('services.admin.email');
                if ($adminEmail) {
                    Mail::raw('Competitor sales data processing failed: ' . $e->getMessage() . "\n\n" . $e->getTraceAsString(), function ($message) use ($adminEmail) {
                        $message->to($adminEmail)->subject('Competitor Sales Data Processing Error');
                    });
                }
            } catch (\Exception $mailException) {
                Log::warning('Could not send error email: ' . $mailException->getMessage());
            }
        }        
    }
}
