<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('walmart_daily_data')) {
            return;
        }

        // Step 1: Delete duplicate rows (keep the one with lowest id)
        DB::statement("
            DELETE t1 FROM walmart_daily_data t1
            INNER JOIN walmart_daily_data t2 
            WHERE t1.id > t2.id 
            AND t1.purchase_order_id = t2.purchase_order_id 
            AND t1.order_line_number = t2.order_line_number
        ");

        // Step 2: Add unique index
        if (! $this->indexExists('walmart_daily_data', 'walmart_daily_unique')) {
            Schema::table('walmart_daily_data', function (Blueprint $table) {
                $table->unique(['purchase_order_id', 'order_line_number'], 'walmart_daily_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('walmart_daily_data')) {
            return;
        }
        if (! $this->indexExists('walmart_daily_data', 'walmart_daily_unique')) {
            return;
        }

        Schema::table('walmart_daily_data', function (Blueprint $table) {
            $table->dropUnique('walmart_daily_unique');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = Schema::getConnection()->getDatabaseName();

        $row = DB::selectOne(
            'select count(*) as c from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ?',
            [$database, $table, $indexName]
        );

        return isset($row->c) && (int) $row->c > 0;
    }
};

