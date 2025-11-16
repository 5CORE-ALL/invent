<?php

namespace App\Console\Commands;

use App\Models\ShopifySku;
use Illuminate\Console\Command;
use App\Http\Controllers\ApiController;
class SyncShopifyQuantity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:shopify-quantity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Shopify Quantity';

    /**
     * Execute the console command.
     */
     public function handle()
    {
        $controller = new ApiController();
        $sheet = $controller->fetchShopifyB2CListingData();
        $rows = collect($sheet->getData()->data ?? []);

        foreach ($rows as $row) {
            $sku = trim($row->{'(Child) sku'} ?? '');
            if (!$sku) continue;

            ShopifySku::updateOrCreate(
                ['sku' => $sku],
                [
                    'shopify_l30'  => $this->toDecimalOrNull($row->{'SH L30'} ?? null),
                ]
            );
        }

        $this->info('Shopify sheet synced successfully!');
    }

    private function toDecimalOrNull($value)
    {
        return is_numeric($value) ? round((float)$value, 2) : null;
    }

    private function toIntOrNull($value)
    {
        return is_numeric($value) ? (int)$value : null;
    }
}
