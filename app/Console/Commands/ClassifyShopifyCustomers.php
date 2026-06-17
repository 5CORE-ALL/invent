<?php

namespace App\Console\Commands;

use App\Models\Crm\ShopifyCustomer;
use App\Models\Crm\ShopifyOrder;
use App\Services\Crm\ShopifyCustomerClassifier;
use Illuminate\Console\Command;

class ClassifyShopifyCustomers extends Command
{
    protected $signature = 'shopify:classify-customers
        {--chunk=500 : Number of customers to process per chunk}
        {--all : Reclassify all customers instead of only unclassified rows}';

    protected $description = 'Backfill Shopify customer type and marketplace channel classification.';

    public function handle(ShopifyCustomerClassifier $classifier): int
    {
        $chunkSize = max(50, min(2000, (int) $this->option('chunk')));
        $processed = 0;

        $query = ShopifyCustomer::query()
            ->orderBy('id');

        if (! (bool) $this->option('all')) {
            $query->whereNull('customer_type');
        }

        $query
            ->chunkById($chunkSize, function ($customers) use ($classifier, &$processed): void {
                $customerIds = $customers
                    ->pluck('shopify_customer_id')
                    ->filter()
                    ->values();

                $latestOrderIds = $customerIds->isNotEmpty()
                    ? ShopifyOrder::query()
                        ->whereIn('shopify_customer_id', $customerIds)
                        ->selectRaw('MAX(id) as id')
                        ->groupBy('shopify_customer_id')
                        ->pluck('id')
                        ->filter()
                        ->values()
                    : collect();

                $latestOrderPayloads = $latestOrderIds->isNotEmpty()
                    ? ShopifyOrder::query()
                        ->whereIn('id', $latestOrderIds)
                        ->get(['shopify_customer_id', 'raw_payload'])
                        ->mapWithKeys(fn (ShopifyOrder $order) => [$order->shopify_customer_id => $order->raw_payload])
                    : collect();

                foreach ($customers as $customer) {
                    $payload = $latestOrderPayloads->get($customer->shopify_customer_id);
                    $classifier->classify($customer, is_array($payload) ? $payload : null);
                    $processed++;
                }

                $this->line("Classified {$processed} customers...");
                unset($latestOrderIds, $latestOrderPayloads);
                gc_collect_cycles();
            });

        $this->info("Classified {$processed} Shopify customers.");

        return self::SUCCESS;
    }
}
