<?php

namespace App\Console\Commands;

use App\Models\AliexpressMetric;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SaveAliexpressOrderMetrics extends Command
{
    protected $signature = 'aliexpress:save-order-metrics {--days=30} {--page-size=100}';
    protected $description = 'Fetch and save AliExpress order metrics from API';

    public function handle()
    {
        $url = 'https://ship.5coremanagement.com/api/aliexpress/orders';
        $days = $this->option('days');
        $pageSize = $this->option('page-size');
        $page = 1;
        $totalProcessed = 0;
        
        $this->info("Fetching orders for last {$days} days...");
        
        do {
            $this->info("Processing page {$page}...");
            
            $response = Http::post($url, [
                'page' => $page,
                'page_size' => $pageSize,
                'days' => $days
            ]);
            
            $this->info("API Response Status: " . $response->status());

            if ($response->failed()) {
                $this->error("Failed to fetch data from API for page {$page}: " . $response->body());
                return 1;
            }

            $data = $response->json();
            $orders = $data['orders'] ?? [];
            
            if (empty($orders)) {
                $this->info("No more orders to process.");
                break;
            }

            $this->info("Found " . count($orders) . " orders on page {$page}");
            
            foreach ($orders as $order) {
                $orderDate = Carbon::parse($order['gmt_create']);
                $orderId = $order['order_id'];
                $orderStatus = $order['order_status'];
                
                $this->info("Processing order {$orderId} from {$orderDate} with status {$orderStatus}");
                
                foreach ($order['product_list']['aeop_order_product_dto'] as $product) {
                    $sku = $product['sku_code'] ?? null;
                    if (!$sku) {
                        $this->warn("Skipping product {$product['product_id']} - no SKU found");
                        continue;
                    }
                    
                    // Update metrics using the new method
                    AliexpressMetric::updateOrderMetrics(
                        $product['product_id'],
                        $sku,
                        $order,
                        $product
                    );
                    
                    $totalProcessed++;
                }
            }

            $page++;
            
            // Add a small delay to avoid overwhelming the API
            usleep(100000); // 100ms delay
            
        } while (!empty($orders));

        $this->info("Successfully processed {$totalProcessed} products from orders across " . ($page - 1) . " pages.");
        return 0;
    }
}