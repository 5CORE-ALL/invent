<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_UNIQUE = 'fba_shipments_shipment_id_unique';

    private const COMPOSITE_UNIQUE = 'fba_shipments_shipment_sku_unique';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('fba_shipments')) {
            return;
        }
        if ($this->indexExists('fba_shipments', self::COMPOSITE_UNIQUE)) {
            return;
        }

        if ($this->indexExists('fba_shipments', self::OLD_UNIQUE)) {
            Schema::table('fba_shipments', function (Blueprint $table) {
                $table->dropUnique(self::OLD_UNIQUE);
            });
        }

        if (! $this->indexExists('fba_shipments', self::COMPOSITE_UNIQUE)) {
            Schema::table('fba_shipments', function (Blueprint $table) {
                $table->unique(['shipment_id', 'sku'], self::COMPOSITE_UNIQUE);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('fba_shipments')) {
            return;
        }

        if ($this->indexExists('fba_shipments', self::COMPOSITE_UNIQUE)) {
            Schema::table('fba_shipments', function (Blueprint $table) {
                $table->dropUnique(self::COMPOSITE_UNIQUE);
            });
        }

        if (! $this->indexExists('fba_shipments', self::OLD_UNIQUE)) {
            Schema::table('fba_shipments', function (Blueprint $table) {
                $table->unique('shipment_id', self::OLD_UNIQUE);
            });
        }
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
