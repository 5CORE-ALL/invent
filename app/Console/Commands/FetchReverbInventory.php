<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\ReverbProduct;

class FetchReverbInventory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reverb:inventory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch remaining inventory for all Reverb listings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        
        $this->info('Fetching Reverb Listings Inventory...');
        $listings = $this->fetchAllListings();

        $bulkData = [];
        foreach ($listings as $item) {
            $sku = $item['sku'] ?? null;
            if (!$sku) continue;

            $price = $item['price']['amount'] ?? null;
            $views = $item['stats']['views'] ?? null;
            $remainingInventory = $item['inventory'] ?? null;

            $bulkData[] = [
                'sku' => $sku,
                'price' => $price,
                'views' => $views,
                'remaining_inventory' => $remainingInventory,
                'updated_at' => now(),
            ];
        }

        // Bulk update using database transaction
        $this->info('Updating ' . count($bulkData) . ' products...');
        $this->bulkUpdate($bulkData);

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $this->info("Reverb inventory updated successfully in {$duration} seconds.");
    }

    protected function fetchAllListings(): array
    {
        $listings = [];
        $url = 'https://api.reverb.com/api/my/listings';
        $pageCount = 0;

        do {
            $pageCount++;
            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . config('services.reverb.token'),
                'Accept' => 'application/hal+json',
                'Accept-Version' => '3.0',
            ])->get($url);

            if ($response->failed()) {
                $this->error('Failed to fetch listings on page ' . $pageCount);
                break;
            }

            $data = $response->json();
            $currentListings = $data['listings'] ?? [];
            $listings = array_merge($listings, $currentListings);
            
            $url = $data['_links']['next']['href'] ?? null;
            $this->info("  Fetched page {$pageCount} (" . count($listings) . " listings so far)...");

        } while ($url);

        $this->info('Fetched total listings: ' . count($listings));
        return $listings;
    }

    protected function bulkUpdate(array $data): void
    {
        if (empty($data)) {
            return;
        }

        try {
            // Use chunking for very large datasets
            $chunks = array_chunk($data, 500);
            $totalChunks = count($chunks);
            
            foreach ($chunks as $index => $chunk) {
                DB::transaction(function () use ($chunk) {
                    foreach ($chunk as $item) {
                        // Check if product exists
                        $exists = DB::table('reverb_products')
                            ->where('sku', $item['sku'])
                            ->exists();

                        if ($exists) {
                            // Update only inventory, price, views
                            DB::table('reverb_products')
                                ->where('sku', $item['sku'])
                                ->update([
                                    'price' => $item['price'],
                                    'views' => $item['views'],
                                    'remaining_inventory' => $item['remaining_inventory'],
                                    'updated_at' => $item['updated_at'],
                                ]);
                        } else {
                            // Insert new record with default L30/L60 = 0
                            DB::table('reverb_products')
                                ->insert([
                                    'sku' => $item['sku'],
                                    'r_l30' => 0,
                                    'r_l60' => 0,
                                    'price' => $item['price'],
                                    'views' => $item['views'],
                                    'remaining_inventory' => $item['remaining_inventory'],
                                    'created_at' => now(),
                                    'updated_at' => $item['updated_at'],
                                ]);
                        }
                    }
                });
                
                if ($totalChunks > 1) {
                    $this->info("  Processed chunk " . ($index + 1) . " of {$totalChunks}...");
                }
            }
        } catch (\Exception $e) {
            $this->error('Error updating inventory: ' . $e->getMessage());
            // Fallback to individual updates
            foreach ($data as $item) {
                try {
                    $exists = DB::table('reverb_products')
                        ->where('sku', $item['sku'])
                        ->exists();

                    if ($exists) {
                        DB::table('reverb_products')
                            ->where('sku', $item['sku'])
                            ->update([
                                'price' => $item['price'],
                                'views' => $item['views'],
                                'remaining_inventory' => $item['remaining_inventory'],
                                'updated_at' => $item['updated_at'],
                            ]);
                    } else {
                        ReverbProduct::create([
                            'sku' => $item['sku'],
                            'r_l30' => 0,
                            'r_l60' => 0,
                            'price' => $item['price'],
                            'views' => $item['views'],
                            'remaining_inventory' => $item['remaining_inventory'],
                        ]);
                    }
                } catch (\Exception $e) {
                    $this->warn('Failed to update product ' . ($item['sku'] ?? 'unknown') . ': ' . $e->getMessage());
                }
            }
        }
    }
}
