<?php

namespace Database\Seeders;

use App\Models\Marketplace;
use Illuminate\Database\Seeder;

class SkuImageMarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('marketplaces')) {
            return;
        }

        foreach ([
            ['name' => 'eBay 1', 'code' => 'ebay'],
            ['name' => 'eBay 2', 'code' => 'ebay2'],
            ['name' => 'eBay 3', 'code' => 'ebay3'],
            ['name' => "Macy's", 'code' => 'macy'],
            ['name' => 'Amazon', 'code' => 'amazon'],
            ['name' => 'Temu', 'code' => 'temu'],
            ['name' => 'Reverb', 'code' => 'reverb'],
            ['name' => 'Wayfair', 'code' => 'wayfair'],
            ['name' => 'Best Buy', 'code' => 'bestbuy'],
            ['name' => 'Shopify Main', 'code' => 'shopify_main'],
            ['name' => 'Shopify PLS', 'code' => 'shopify_pls'],
        ] as $row) {
            Marketplace::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'status' => true,
                ]
            );
        }
    }
}
