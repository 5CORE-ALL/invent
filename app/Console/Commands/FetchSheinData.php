<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SheinApiService;

class FetchSheinData extends Command
{
    /**
     * Command signature
     */
    protected $signature = 'shein:fetch
        {type : products | spu | sync | product-details | orders}
        {--spu= : SPU name (required for spu)}
        {--sku= : SKU (required for product-details)}
        {--days=30 : Days of orders to pull (orders type; L30 max 30, L60 max 60)}
        {--target=l30 : Order sync target: l30 (shein_daily_data) or l60 (shein_daily_data_l60)}
        {--query-type=1 : Order queryType 1=new 2=updated}
        {--no-details : Skip order-detail calls (list only)}';

    /**
     * Command description
     */
    protected $description = 'Fetch data from Shein API (Products, Price, Inventory, Orders) → shein_metrics / shein_pricing_prices / shein_daily_data';

    protected SheinApiService $sheinService;

    /**
     * Inject service
     */
    public function __construct(SheinApiService $sheinService)
    {
        parent::__construct();
        $this->sheinService = $sheinService;
    }

    /**
     * Execute command
     */
    public function handle()
    {
        $type = $this->argument('type');

        try {

            switch ($type) {

                case 'products':
                    $this->info('⌛ Fetching all products...');
                    $products = $this->sheinService->listAllProducts();
                    $this->info('✅ Products fetched: ' . count($products));
                    break;

                case 'spu':
                    $spu = $this->option('spu');
                    if (!$spu) {
                        $this->error('❌ --spu is required');
                        return Command::FAILURE;
                    }

                    $this->info("⌛ Fetching SPU: {$spu}");
                    $data = $this->sheinService->fetchBySpu($spu);
                    $this->info('✅ SPU data fetched');
                    break;

                case 'sync':
                    $this->info('⌛ Syncing all Shein product data (Price, Views, Rating, Inventory)...');
                    $result = $this->sheinService->syncAllProductData();
                    
                    if ($result['success']) {
                        $this->info('✅ ' . $result['message']);
                        $this->info('📊 Total products synced: ' . $result['total_products']);
                    } else {
                        $this->error('❌ Sync failed: ' . $result['message']);
                        return Command::FAILURE;
                    }
                    break;

                case 'product-details':
                    $sku = $this->option('sku');
                    if (!$sku) {
                        $this->error('❌ --sku is required');
                        return Command::FAILURE;
                    }

                    $this->info("⌛ Fetching product details for SKU: {$sku}");
                    $details = $this->sheinService->getProductDetails($sku);
                    
                    if ($details) {
                        $this->info('✅ Product details fetched and saved to shein_metrics table:');
                        $this->newLine();
                        
                        $this->table(
                            ['Field', 'Value'],
                            [
                                ['SKU', $details['sku']],
                                ['Product Name', $details['product_name'] ?? 'N/A'],
                                ['SPU Name', $details['spu_name'] ?? 'N/A'],
                                ['Inventory', $details['quantity']],
                                ['Price', $details['price'] ?? 'N/A'],
                                ['Retail Price', $details['retail_price'] ?? 'N/A'],
                                ['Cost Price', $details['cost_price'] ?? 'N/A'],
                                ['Views', $details['views'] ?? 'N/A'],
                                ['Rating', $details['rating'] ?? 'N/A'],
                                ['Review Count', $details['review_count'] ?? 'N/A'],
                                ['Status', $details['status'] ?? 'N/A'],
                                ['Category', $details['category'] ?? 'N/A'],
                            ]
                        );
                    } else {
                        $this->warn('⚠️ No details found for this SKU');
                    }
                    break;

                case 'orders':
                    $target = strtolower((string) $this->option('target')) === 'l60' ? 'l60' : 'l30';
                    $days = (int) $this->option('days');
                    if ($days <= 0) {
                        $days = $target === 'l60' ? 60 : 30;
                    }
                    $table = $target === 'l60' ? 'shein_daily_data_l60' : 'shein_daily_data';
                    $this->info("⌛ Syncing Shein orders from API into {$table} (last {$days} day(s), {$target})...");
                    $result = $this->sheinService->syncOrdersToDailyData($days, $target);
                    if (! ($result['success'] ?? false)) {
                        $this->error('❌ '.($result['message'] ?? 'Orders sync failed'));
                        return Command::FAILURE;
                    }
                    $this->info('✅ '.$result['message']);
                    $this->info('📊 Orders: '.($result['order_count'] ?? 0).' | Lines imported: '.($result['imported'] ?? 0));
                    break;

                default:
                    $this->error('❌ Invalid type');
                    $this->line('Valid types: products, spu, sync, product-details, orders');
                    return Command::FAILURE;
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}