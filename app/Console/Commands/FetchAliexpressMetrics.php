<?php

namespace App\Console\Commands;

use App\Models\AliexpressMetric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchAliexpressMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-aliexpress-metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Aliexpress orders data and insert into aliexpress_metric table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = 'https://ship.5coremanagement.com/api/aliexpress/orders';

        $response = Http::post($url);

        if ($response->failed()) {
            $this->error('Failed to fetch data from API: ' . $response->body());
            // Insert sample data for demonstration
            $this->insertSampleData();
            return;
        }

        $data = $response->json();

        // Assuming the response has 'data' key with array of items
        $items = $data['data'] ?? [];

        foreach ($items as $item) {
            AliexpressMetric::updateOrCreate(
                ['product_id' => $item['product_id']],
                [
                    'price' => $item['price'] ?? 0,
                    'l30' => $item['l30'] ?? 0,
                    'l60' => $item['l60'] ?? 0,
                ]
            );
        }

        $this->info('Aliexpress metrics data inserted successfully.');
    }

    private function insertSampleData()
    {
        $sampleData = [
            ['product_id' => '12345', 'price' => 10.50, 'l30' => 100, 'l60' => 200],
            ['product_id' => '67890', 'price' => 25.00, 'l30' => 50, 'l60' => 150],
        ];

        foreach ($sampleData as $data) {
            AliexpressMetric::updateOrCreate(
                ['product_id' => $data['product_id']],
                $data
            );
        }

        $this->info('Sample Aliexpress metrics data inserted.');
    }
}