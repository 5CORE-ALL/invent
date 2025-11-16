<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
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
        $this->info('Fetching Reverb Orders...');
        $this->fetchAllOrders();

        $this->info('Fetching Reverb Listings...');
        $listings = $this->fetchAllListings();

        $today = Carbon::today();
        
        // Calculate L30 range (last 30 days from today)
        $l30End = $today->copy();
        $l30Start = $today->copy()->subDays(30);

        // Calculate L60 range (31-60 days from today) - expand to catch all orders
        $l60End = $l30Start->copy()->subDay();
        $l60Start = $l60End->copy()->subDays(35); // Go back further to catch August orders

        $this->info("Date ranges - L30: {$l30Start->toDateString()} to {$l30End->toDateString()}, L60: {$l60Start->toDateString()} to {$l60End->toDateString()}");

        // Get all SKUs with orders
        $orderSkus = ReverbOrderMetric::distinct('sku')->pluck('sku')->toArray();

        // Create map of SKU to listing data
        $listingMap = [];
        foreach ($listings as $item) {
            $sku = $item['sku'] ?? null;
            if ($sku) {
                $listingMap[$sku] = $item;
            }
        }

        // Calculate quantities for each SKU
        $rL30 = $this->calculateQuantitiesFromMetrics($l30Start, $l30End);
        $rL60 = $this->calculateQuantitiesFromMetrics($l60Start, $l60End);

        foreach ($orderSkus as $sku) {
            $r30 = $rL30[$sku] ?? 0;
            $r60 = $rL60[$sku] ?? 0;

            $listing = $listingMap[$sku] ?? null;
            $price = $listing ? ($listing['price']['amount'] ?? null) : null;
            $views = $listing ? ($listing['stats']['views'] ?? null) : null;

            // Store record
            ReverbProduct::updateOrCreate(
                ['sku' => $sku], // Match on SKU
                [
                    'sku' => $sku,
                    'r_l30' => $r30,
                    'r_l60' => $r60,
                    'price' => $price,
                    'views' => $views,
                ]
            );
        }

        $this->info('Reverb data stored successfully.');
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

    protected function fetchAllOrders(): void
    {
        $url = 'https://api.reverb.com/api/my/orders/selling/all';

        do {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.reverb.token'),
                'Accept' => 'application/hal+json',
                'Accept-Version' => '3.0',
            ])->get($url);

            if ($response->failed()) {
                $this->error('Failed to fetch orders.');
                break;
            }

            $data = $response->json();
            $orders = $data['orders'] ?? [];

            foreach ($orders as $order) {
                $paidAt = $order['paid_at'] ?? $order['created_at'] ?? null;
                if (!$paidAt) continue;

                ReverbOrderMetric::updateOrCreate(
                    ['order_number' => $order['order_number']],
                    [
                        'order_date' => Carbon::parse($paidAt)->toDateString(),
                        'status' => $order['status'],
                        'amount' => ($order['total']['amount_cents'] ?? 0) / 100,
                        'display_sku' => $order['title'] ?? null,
                        'sku' => $order['sku'] ?? null,
                        'quantity' => $order['quantity'] ?? 1,
                        'order_number' => $order['order_number'],
                    ]
                );
            }

            $url = $data['_links']['next']['href'] ?? null;

        } while ($url);

        $this->info('Fetched and stored orders.');
    }

    protected function calculateQuantitiesFromMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $this->info("Calculating quantities from metrics table for {$startDate->toDateString()} to {$endDate->toDateString()}...");

        $quantities = ReverbOrderMetric::whereBetween('order_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('status', '!=', 'returned')
            ->selectRaw('sku, SUM(quantity) as total_quantity')
            ->groupBy('sku')
            ->pluck('total_quantity', 'sku')
            ->toArray();

        $this->info("Found " . count($quantities) . " SKUs with orders in this period.");
        return $quantities;
    }

    
}
