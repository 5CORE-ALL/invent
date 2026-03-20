<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EbayCompetitorItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UpdateEbayCompetitorPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ebay:update-prices {--search-query= : Update specific search query only} {--dry-run : Run without updating database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically update eBay competitor prices for existing searches (runs weekly via cron)';

    /**
     * SerpApi Key
     *
     * @var string
     */
    protected $serpApiKey = '1ce23be0f3d775e0d631854b4856791aefa6e003415b28e33eb99b5a9c6a83c9';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = now();
        $this->info('Starting eBay Competitor Price Update...');
        $this->info('Started at: ' . $startTime->format('Y-m-d H:i:s'));
        
        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No database changes will be made');
        }

        // Get unique search queries to update
        $searchQuery = $this->option('search-query');
        
        if ($searchQuery) {
            $searchQueries = [$searchQuery];
            $this->info("Updating specific search query: {$searchQuery}");
        } else {
            $searchQueries = EbayCompetitorItem::select('search_query', 'marketplace')
                ->distinct()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($item) {
                    return [
                        'query' => $item->search_query,
                        'marketplace' => $item->marketplace ?? 'ebay'
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
            $marketplace = is_array($searchData) ? ($searchData['marketplace'] ?? 'ebay') : 'ebay';
            
            $queriesProcessed++;
            
            $this->info("\n[{$queriesProcessed}/" . count($searchQueries) . "] Processing: {$query}");
            
            try {
                $result = $this->updateSearchQuery($query, $marketplace, $isDryRun);
                $totalUpdated += $result['updated'];
                $totalUnchanged += $result['unchanged'];
                $totalErrors += $result['errors'];
                
                $this->info("  Updated: {$result['updated']}, Unchanged: {$result['unchanged']}, Errors: {$result['errors']}");
                
                // Rate limiting - wait 2 seconds between queries to avoid API limits
                if ($queriesProcessed < count($searchQueries)) {
                    $this->info('  Waiting 2 seconds before next query...');
                    sleep(2);
                }
                
            } catch (\Exception $e) {
                $this->error("  Failed to update query '{$query}': " . $e->getMessage());
                Log::error('eBay Price Update Error', [
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
        
        Log::info('eBay Competitor Price Update Completed', [
            'queries_processed' => $queriesProcessed,
            'items_updated' => $totalUpdated,
            'items_unchanged' => $totalUnchanged,
            'errors' => $totalErrors,
            'duration_seconds' => $duration,
            'dry_run' => $isDryRun
        ]);

        return 0;
    }

    /**
     * Update prices for a specific search query
     *
     * @param string $searchQuery
     * @param string $marketplace
     * @param bool $isDryRun
     * @return array
     */
    protected function updateSearchQuery($searchQuery, $marketplace, $isDryRun)
    {
        $updated = 0;
        $unchanged = 0;
        $errors = 0;
        $maxPages = 10; // Limit to 10 pages for weekly updates

        try {
            // Fetch current prices from eBay via SerpApi
            for ($page = 1; $page <= $maxPages; $page++) {
                $response = Http::timeout(30)->get('https://serpapi.com/search', [
                    'engine' => 'ebay',
                    'ebay_domain' => 'ebay.com',
                    '_nkw' => $searchQuery,
                    '_pgn' => $page,
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
                    $itemId = $result['item_id'] ?? $result['epid'] ?? null;
                    
                    if (!$itemId) {
                        continue;
                    }

                    // Extract price
                    $price = $this->extractPrice($result);
                    
                    // Extract shipping cost
                    $shippingCost = $this->extractShippingCost($result);

                    // Extract other fields
                    $condition = $result['condition'] ?? null;
                    $sellerName = $result['seller']['name'] ?? null;
                    $sellerRating = $result['seller']['rating'] ?? null;
                    $location = $result['location'] ?? null;
                    $link = $result['link'] ?? null;
                    $title = $result['title'] ?? null;
                    $image = $result['thumbnail'] ?? $result['image'] ?? null;

                    // Check if item exists in database
                    $existing = EbayCompetitorItem::where('search_query', $searchQuery)
                        ->where('item_id', $itemId)
                        ->first();

                    if ($existing) {
                        // Check if price or other data has changed
                        $hasChanges = false;
                        $changes = [];

                        if ($existing->price != $price) {
                            $hasChanges = true;
                            $changes['price'] = ['old' => $existing->price, 'new' => $price];
                        }
                        if ($existing->shipping_cost != $shippingCost) {
                            $hasChanges = true;
                            $changes['shipping_cost'] = ['old' => $existing->shipping_cost, 'new' => $shippingCost];
                        }
                        if ($existing->condition != $condition) {
                            $hasChanges = true;
                            $changes['condition'] = ['old' => $existing->condition, 'new' => $condition];
                        }

                        if ($hasChanges) {
                            if (!$isDryRun) {
                                $existing->update([
                                    'price' => $price,
                                    'shipping_cost' => $shippingCost,
                                    'condition' => $condition,
                                    'seller_name' => $sellerName,
                                    'seller_rating' => $sellerRating,
                                    'location' => $location,
                                    'link' => $link,
                                    'title' => $title,
                                    'image' => $image,
                                ]);
                            }
                            
                            $this->line("    Updated item {$itemId}: " . json_encode($changes));
                            $updated++;
                        } else {
                            $unchanged++;
                        }
                    }
                    // Note: We don't add new items, only update existing ones
                }
                
                // Small delay between pages
                if ($page < $maxPages) {
                    usleep(500000); // 0.5 second
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

    /**
     * Extract price from result
     *
     * @param array $result
     * @return float|null
     */
    protected function extractPrice($result)
    {
        if (isset($result['price']['value'])) {
            return $result['price']['value'];
        } elseif (isset($result['price']['raw'])) {
            $priceString = $result['price']['raw'];
            preg_match('/[\d,.]+/', $priceString, $matches);
            if (!empty($matches)) {
                return str_replace(',', '', $matches[0]);
            }
        } elseif (isset($result['price'])) {
            $priceString = is_string($result['price']) ? $result['price'] : '';
            preg_match('/[\d,.]+/', $priceString, $matches);
            if (!empty($matches)) {
                return str_replace(',', '', $matches[0]);
            }
        }
        return null;
    }

    /**
     * Extract shipping cost from result
     *
     * @param array $result
     * @return float|null
     */
    protected function extractShippingCost($result)
    {
        if (isset($result['shipping']['value'])) {
            return $result['shipping']['value'];
        } elseif (isset($result['shipping'])) {
            $shippingString = is_string($result['shipping']) ? $result['shipping'] : '';
            if (stripos($shippingString, 'free') === false) {
                preg_match('/[\d,.]+/', $shippingString, $matches);
                if (!empty($matches)) {
                    return str_replace(',', '', $matches[0]);
                }
            } else {
                return 0;
            }
        }
        return null;
    }
}
