<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\ReverbProduct;
use App\Models\ReverbOrderMetric;
use Carbon\Carbon;

class FetchReverbData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reverb:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate Reverb L30/L60 data from metrics table and update products';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        
        $this->info('Fetching Reverb Orders...');
        $this->fetchAllOrders();

        $this->info('Fetching Reverb Listings...');
        $listings = $this->fetchAllListings();

        // Use California timezone for date calculations
        $today = Carbon::now('America/Los_Angeles')->startOfDay();
        
        // Calculate L30 range (last 30 days from today)
        $l30End = $today->copy();
        $l30Start = $today->copy()->subDays(30);

        // Calculate L60 range (31-60 days from today) - should be exactly 30 days
        $l60End = $l30Start->copy()->subDay();
        $l60Start = $l60End->copy()->subDays(29); // 30 days total for L60 period

        $this->info("Date ranges - L30: {$l30Start->toDateString()} to {$l30End->toDateString()}, L60: {$l60Start->toDateString()} to {$l60End->toDateString()}");

        // Create map of SKU to listing data - THIS IS THE SOURCE OF ALL SKUs
        $listingMap = [];
        foreach ($listings as $item) {
            $sku = $item['sku'] ?? null;
            if ($sku) {
                $listingMap[$sku] = $item;
            }
        }
        $this->info('Found ' . count($listingMap) . ' total listings with SKUs.');

        // Calculate quantities for each SKU (optimized single query)
        $rL30 = $this->calculateQuantitiesFromMetrics($l30Start, $l30End);
        $rL60 = $this->calculateQuantitiesFromMetrics($l60Start, $l60End);

        // Fetch bump bid % for each listing (only bump bid from Reverb API)
        $this->info('Fetching bump bid % for each listing...');
        $bumpBidBySku = $this->fetchBumpBidForListings($listingMap);

        // Prepare bulk update data - Process ALL listed SKUs (not just those with orders)
        $bulkData = [];
        foreach ($listingMap as $sku => $listing) {
            $r30 = $rL30[$sku] ?? 0;
            $r60 = $rL60[$sku] ?? 0;
            
            $price = $listing['price']['amount'] ?? null;
            $views = $listing['stats']['views'] ?? null;
            $remainingInventory = $listing['inventory'] ?? null;
            $bumpBid = $bumpBidBySku[$sku] ?? null;

            $bulkData[] = [
                'sku' => $sku,
                'r_l30' => $r30,
                'r_l60' => $r60,
                'price' => $price,
                'views' => $views,
                'remaining_inventory' => $remainingInventory,
                'bump_bid' => $bumpBid,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }

        // Bulk upsert using database transaction
        $this->info('Bulk updating ' . count($bulkData) . ' records...');
        $this->bulkUpsert($bulkData);

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $this->info("Reverb data stored successfully in {$duration} seconds.");
    }

    protected function fetchAllListings(): array
    {
        $listings = [];
        $url = 'https://api.reverb.com/api/my/listings';

        do {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.reverb.token'),
                'Accept' => 'application/hal+json',
                'Accept-Version' => '3.0',
            ])->get($url);

            if ($response->failed()) {
                $this->error('Failed to fetch listings.');
                break;
            }

            $data = $response->json();
            $listings = array_merge($listings, $data['listings'] ?? []);
            $url = $data['_links']['next']['href'] ?? null;

        } while ($url);

        $this->info('Fetched total listings: ' . count($listings));
        return $listings;
    }

    /**
     * Fetch bump bid % only from Reverb API for each listing.
     * GET https://api.reverb.com/api/listings/{id}/bump returns current_bid (display e.g. "2%").
     */
    protected function fetchBumpBidForListings(array $listingMap): array
    {
        $result = [];
        $total = count($listingMap);
        $index = 0;
        $headers = [
            'Authorization' => 'Bearer ' . config('services.reverb.token'),
            'Accept' => 'application/hal+json',
            'Accept-Version' => '3.0',
        ];

        foreach ($listingMap as $sku => $listing) {
            $listingId = $listing['id'] ?? null;
            if (!$listingId) {
                continue;
            }
            $index++;
            if ($index % 50 === 0) {
                $this->info("  Bump bid: {$index}/{$total}...");
            }

            $response = Http::withHeaders($headers)->get("https://api.reverb.com/api/listings/{$listingId}/bump");
            if ($response->failed()) {
                continue;
            }
            $data = $response->json();
            // current_bid: display "2%", or bump_v2_stats.current_bid.display
            $currentBid = $data['current_bid'] ?? $data['bump_v2_stats']['current_bid'] ?? null;
            $display = is_array($currentBid) ? ($currentBid['display'] ?? null) : null;
            if ($display !== null) {
                $result[$sku] = $display;
            }
            usleep(150000); // 0.15s between calls to avoid rate limit
        }

        $this->info('Fetched bump bid for ' . count($result) . ' listings.');
        return $result;
    }

    protected function fetchAllOrders(): void
    {
        $url = 'https://api.reverb.com/api/my/orders/selling/all';
        $pageCount = 0;
        $totalOrders = 0;
        $bulkOrders = [];

        do {
            $pageCount++;
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . config('services.reverb.token'),
                'Accept' => 'application/hal+json',
                'Accept-Version' => '3.0',
            ])->get($url);

            if ($response->failed()) {
                $this->error('Failed to fetch orders on page ' . $pageCount . ': ' . $response->body());
                break;
            }

            $data = $response->json();
            $orders = $data['orders'] ?? [];
            $totalOrders += count($orders);

            // Prepare bulk insert data
            foreach ($orders as $order) {
                $paidAt = $order['paid_at'] ?? $order['created_at'] ?? null;
                if (!$paidAt) continue;

                $bulkOrders[] = [
                    'order_number' => $order['order_number'],
                    'order_date' => Carbon::parse($paidAt, 'America/Los_Angeles')->toDateString(),
                    'status' => $order['status'] ?? null,
                    'amount' => ($order['total']['amount_cents'] ?? 0) / 100,
                    'display_sku' => $order['title'] ?? null,
                    'sku' => $order['sku'] ?? null,
                    'quantity' => $order['quantity'] ?? 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ];
            }

            // Bulk insert in chunks of 100 to avoid memory issues
            if (count($bulkOrders) >= 100) {
                $this->bulkUpsertOrders($bulkOrders);
                $bulkOrders = [];
            }

            $url = $data['_links']['next']['href'] ?? null;
            $this->info("  Processed page {$pageCount} ({$totalOrders} orders so far)...");

        } while ($url);

        // Insert remaining orders
        if (!empty($bulkOrders)) {
            $this->bulkUpsertOrders($bulkOrders);
        }

        $this->info("Fetched and stored {$totalOrders} orders from {$pageCount} pages.");
    }

    protected function calculateQuantitiesFromMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $this->info("Calculating quantities from metrics table for {$startDate->toDateString()} to {$endDate->toDateString()}...");

        $quantities = ReverbOrderMetric::whereBetween('order_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotIn('status', ['returned', 'refunded'])
            ->whereNotNull('sku')
            ->selectRaw('sku, SUM(quantity) as total_quantity')
            ->groupBy('sku')
            ->pluck('total_quantity', 'sku')
            ->toArray();

        $this->info("Found " . count($quantities) . " SKUs with orders in this period.");
        return $quantities;
    }

    /**
     * Bulk upsert orders using raw SQL for better performance
     */
    protected function bulkUpsertOrders(array $orders): void
    {
        if (empty($orders)) {
            return;
        }

        try {
            DB::transaction(function () use ($orders) {
                foreach ($orders as $order) {
                    DB::table('reverb_order_metrics')
                        ->updateOrInsert(
                            ['order_number' => $order['order_number']],
                            $order
                        );
                }
            });
        } catch (\Exception $e) {
            $this->error('Error bulk upserting orders: ' . $e->getMessage());
            // Fallback to individual inserts if bulk fails
            foreach ($orders as $order) {
                try {
                    ReverbOrderMetric::updateOrCreate(
                        ['order_number' => $order['order_number']],
                        $order
                    );
                } catch (\Exception $e) {
                    $this->warn('Failed to insert order ' . ($order['order_number'] ?? 'unknown') . ': ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Bulk upsert products using raw SQL for better performance
     */
    protected function bulkUpsert(array $data): void
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
                        DB::table('reverb_products')
                            ->updateOrInsert(
                                ['sku' => $item['sku']],
                                $item
                            );
                    }
                });
                
                if ($totalChunks > 1) {
                    $this->info("  Processed chunk " . ($index + 1) . " of {$totalChunks}...");
                }
            }
        } catch (\Exception $e) {
            $this->error('Error bulk upserting products: ' . $e->getMessage());
            // Fallback to individual inserts if bulk fails
            foreach ($data as $item) {
                try {
                    ReverbProduct::updateOrCreate(
                        ['sku' => $item['sku']],
                        $item
                    );
                } catch (\Exception $e) {
                    $this->warn('Failed to insert product ' . ($item['sku'] ?? 'unknown') . ': ' . $e->getMessage());
                }
            }
        }
    }
}
