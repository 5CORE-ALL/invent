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
            ['name' => 'Reverb', 'code' => 'reverb'],
            ['name' => 'Amazon', 'code' => 'amazon'],
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
