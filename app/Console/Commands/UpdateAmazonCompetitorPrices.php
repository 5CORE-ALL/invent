<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AmazonCompetitorAsin;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UpdateAmazonCompetitorPrices extends Command
{
    protected $signature = 'amazon:update-prices {--search-query= : Update specific search query only} {--dry-run : Run without updating database}';
    protected $description = 'Automatically update Amazon competitor prices for existing searches (runs weekly via cron)';
    protected $serpApiKey = '1ce23be0f3d775e0d631854b4856791aefa6e003415b28e33eb99b5a9c6a83c9';

    public function handle()
    {
        $startTime = now();
        $this->info('Starting Amazon Competitor Price Update...');
        $this->info('Started at: ' . $startTime->format('Y-m-d H:i:s'));
        
        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No database changes will be made');
        }

        $searchQuery = $this->option('search-query');
        
        if ($searchQuery) {
            $searchQueries = [$searchQuery];
            $this->info("Updating specific search query: {$searchQuery}");
        } else {
            $searchQueries = AmazonCompetitorAsin::select('search_query', 'marketplace')
                ->distinct()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($item) {
                    return [
                        'query' => $item->search_query,
                        'marketplace' => $item->marketplace ?? 'amazon'
                    ];
                })
                ->toArray();
            
            $this->info('Found ' . count($searchQueries) . ' unique search queries to update');
        }

        $totalUpdated = 0;
        $totalUnchanged = 0;
        $totalErrors = 0;
        $queriesProcessed = 0;

        foreach ($searchQueries as $searchData) {
            $query = is_array($searchData) ? $searchData['query'] : $searchData;
            $marketplace = is_array($searchData) ? ($searchData['marketplace'] ?? 'amazon') : 'amazon';
            
            $queriesProcessed++;
            
            $this->info("\n[{$queriesProcessed}/" . count($searchQueries) . "] Processing: {$query}");
            
            try {
                $result = $this->updateSearchQuery($query, $marketplace, $isDryRun);
                $totalUpdated += $result['updated'];
                $totalUnchanged += $result['unchanged'];
                $totalErrors += $result['errors'];
                
                $this->info("  Updated: {$result['updated']}, Unchanged: {$result['unchanged']}, Errors: {$result['errors']}");
                
                if ($queriesProcessed < count($searchQueries)) {
                    $this->info('  Waiting 2 seconds before next query...');
                    sleep(2);
                }
                
            } catch (\Exception $e) {
                $this->error("  Failed to update query '{$query}': " . $e->getMessage());
                Log::error('Amazon Price Update Error', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $totalErrors++;
            }
        }

        $endTime = now();
        $duration = $endTime->diffInSeconds($startTime);
        
        $this->newLine();
        $this->info('=== Update Complete ===');
        $this->info('Queries Processed: ' . $queriesProcessed);
        $this->info('Total Items Updated: ' . $totalUpdated);
        $this->info('Total Items Unchanged: ' . $totalUnchanged);
        $this->info('Total Errors: ' . $totalErrors);
        $this->info('Duration: ' . gmdate('H:i:s', $duration));
        $this->info('Completed at: ' . $endTime->format('Y-m-d H:i:s'));
        
        Log::info('Amazon Competitor Price Update Completed', [
            'queries_processed' => $queriesProcessed,
            'items_updated' => $totalUpdated,
            'items_unchanged' => $totalUnchanged,
            'errors' => $totalErrors,
            'duration_seconds' => $duration,
            'dry_run' => $isDryRun
        ]);

        return 0;
    }

    protected function updateSearchQuery($searchQuery, $marketplace, $isDryRun)
    {
        $updated = 0;
        $unchanged = 0;
        $errors = 0;
        $maxPages = 10;

        try {
            for ($page = 1; $page <= $maxPages; $page++) {
                $response = Http::timeout(30)->get('https://serpapi.com/search', [
                    'engine' => 'amazon',
                    'amazon_domain' => 'amazon.com',
                    'k' => $searchQuery,
                    'page' => $page,
                    'api_key' => $this->serpApiKey,
                ]);

                if (!$response->successful()) {
                    $this->warn("  API request failed for page {$page}");
                    break;
                }

                $data = $response->json();
                
                if (!isset($data['organic_results']) || empty($data['organic_results'])) {
                    break;
                }

                $organicResults = $data['organic_results'];
                
                foreach ($organicResults as $result) {
                    $asin = $result['asin'] ?? null;
                    
                    if (!$asin) {
                        continue;
                    }

                    $price = $this->extractPrice($result);
                    $rating = $result['rating'] ?? null;
                    $reviews = $result['reviews_count'] ?? $result['ratings_total'] ?? null;
                    $title = $result['title'] ?? null;
                    $image = $result['thumbnail'] ?? $result['image'] ?? null;

                    $existing = AmazonCompetitorAsin::where('search_query', $searchQuery)
                        ->where('asin', $asin)
                        ->first();

                    if ($existing) {
                        $hasChanges = false;
                        $changes = [];

                        if ($existing->price != $price) {
                            $hasChanges = true;
                            $changes['price'] = ['old' => $existing->price, 'new' => $price];
                        }
                        if ($existing->rating != $rating) {
                            $hasChanges = true;
                            $changes['rating'] = ['old' => $existing->rating, 'new' => $rating];
                        }
                        if ($existing->reviews != $reviews) {
                            $hasChanges = true;
                            $changes['reviews'] = ['old' => $existing->reviews, 'new' => $reviews];
                        }

                        if ($hasChanges) {
                            if (!$isDryRun) {
                                $existing->update([
                                    'price' => $price,
                                    'rating' => $rating,
                                    'reviews' => $reviews,
                                    'title' => $title,
                                    'image' => $image,
                                ]);
                            }
                            
                            $this->line("    Updated ASIN {$asin}: " . json_encode($changes));
                            $updated++;
                        } else {
                            $unchanged++;
                        }
                    }
                }
                
                if ($page < $maxPages) {
                    usleep(500000);
                }
            }

        } catch (\Exception $e) {
            $this->error("  Exception: " . $e->getMessage());
            $errors++;
        }

        return [
            'updated' => $updated,
            'unchanged' => $unchanged,
            'errors' => $errors
        ];
    }

    protected function extractPrice($result)
    {
        if (isset($result['price']['value'])) {
            return $result['price']['value'];
        } elseif (isset($result['price'])) {
            $priceString = is_string($result['price']) ? $result['price'] : '';
            preg_match('/[\d,.]+/', $priceString, $matches);
            if (!empty($matches)) {
                return str_replace(',', '', $matches[0]);
            }
        }
        return null;
    }
}
