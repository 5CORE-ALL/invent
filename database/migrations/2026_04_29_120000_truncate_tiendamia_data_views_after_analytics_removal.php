<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tiendamia Analytics page removed; clear rows that stored NR / Listed / Live / SPRICE JSON for that flow.
     * Table is retained for Tiendamia Pricing and other features to repopulate as needed.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tiendamia_data_views')) {
            return;
        }

        Schema::disableForeignKeyConstraints();
        try {
            DB::table('tiendamia_data_views')->truncate();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        // Truncated data is not restored.
    }
};
