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
        {type : orders | order-items | products | spu}
        {--oid= : Order ID (required for order-items)}
        {--spu= : SPU name (required for spu)}';

    /**
     * Command description
     */
    protected $description = 'Fetch data from Shein API (Orders, Order Items, Products, SPU)';

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

                case 'orders':
                    $this->info('⌛ Fetching Shein Orders...');
                    $orders = $this->sheinService->listAllOrders();
                    $this->info('✅ Orders fetched: ' . count($orders));
                    break;

                case 'order-items':
                    $oid = $this->option('oid');
                    if (!$oid) {
                        $this->error('❌ --oid is required');
                        return Command::FAILURE;
                    }

                    $this->info("⌛ Fetching items for Order: {$oid}");
                    $items = $this->sheinService->getOrderItems($oid);
                    $this->info('✅ Order items fetched');
                    break;

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

                default:
                    $this->error('❌ Invalid type');
                    return Command::FAILURE;
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}