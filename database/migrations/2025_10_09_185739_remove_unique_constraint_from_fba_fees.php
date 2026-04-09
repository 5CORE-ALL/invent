<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'fba_fees_seller_sku_report_generated_at_unique';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('fba_fees')) {
            return;
        }
        if (! $this->indexExists('fba_fees', self::INDEX_NAME)) {
            return;
        }

        Schema::table('fba_fees', function (Blueprint $table) {
            $table->dropUnique(['seller_sku', 'report_generated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('fba_fees')) {
            return;
        }
        if ($this->indexExists('fba_fees', self::INDEX_NAME)) {
            return;
        }

        Schema::table('fba_fees', function (Blueprint $table) {
            $table->unique(['seller_sku', 'report_generated_at']);
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
