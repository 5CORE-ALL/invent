<?php

use App\Models\Marketplace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SKU Image Manager reads active rows from `marketplaces`.
     * The table is created without rows; production deploys often skip `db:seed`, so the UI was empty on server.
     */
    public function up(): void
    {
        if (! Schema::hasTable('marketplaces')) {
            return;
        }

        foreach ([
            ['name' => 'Reverb', 'code' => 'reverb'],
            ['name' => 'Amazon', 'code' => 'amazon'],
            ['name' => 'eBay', 'code' => 'ebay'],
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

    public function down(): void
    {
        // Intentionally empty: do not delete marketplace rows that may already be referenced.
    }
};
