<?php

namespace App\Console\Commands;

use App\Models\WaifairProductSheet;
use App\Services\WayfairApiService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SyncWayfairL30FromAPI extends Command
{
    protected $signature = 'sync:wayfair-l30-api';
    protected $description = 'Sync Wayfair L30/L60 from Wayfair API (Purchase Orders)';

    public function handle()
    {
        $this->info('Fetching Wayfair purchase orders from API...');

        try {
            $wayfairService = new WayfairApiService();
            $result = $wayfairService->getInventory();

            $this->info("Total Orders: {$result['total_orders']}");
            $this->info("Total Products: {$result['total_products']}");

            // Calculate L30 and L60 from purchase orders
            $now = Carbon::now();
            $thirtyDaysAgo = $now->copy()->subDays(30);
            $sixtyDaysAgo = $now->copy()->subDays(60);

            $skuL30 = [];
            $skuL60 = [];

            foreach ($result['products'] as $product) {
                $sku = $product['sku'];
                $quantity = $product['quantity'];
                $poDate = Carbon::parse($product['po_date']);

                // L60
                if ($poDate >= $sixtyDaysAgo) {
                    $skuL60[$sku] = ($skuL60[$sku] ?? 0) + $quantity;
                }

                // L30
                if ($poDate >= $thirtyDaysAgo) {
                    $skuL30[$sku] = ($skuL30[$sku] ?? 0) + $quantity;
                }
            }

            $this->info('Updating wayfair_product_sheets table...');
            $updated = 0;

            foreach ($skuL30 as $sku => $l30) {
                $l60 = $skuL60[$sku] ?? 0;

                $record = WaifairProductSheet::where('sku', $sku)->first();

                if ($record) {
                    $record->update([
                        'l30' => $l30,
                        'l60' => $l60,
                    ]);
                    $updated++;
                } else {
                    // Create new record if doesn't exist
                    WaifairProductSheet::create([
                        'sku' => $sku,
                        'l30' => $l30,
                        'l60' => $l60,
                    ]);
                    $updated++;
                }
            }

            $this->info("✅ Updated {$updated} SKUs with L30/L60 data from Wayfair API");

            // Show sample data
            $this->info("\nSample SKUs updated:");
            $this->table(
                ['SKU', 'L30', 'L60'],
                collect($skuL30)->take(10)->map(function ($l30, $sku) use ($skuL60) {
                    return [$sku, $l30, $skuL60[$sku] ?? 0];
                })->values()->all()
            );

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
