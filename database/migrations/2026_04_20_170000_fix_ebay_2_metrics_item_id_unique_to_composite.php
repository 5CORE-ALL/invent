<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Legacy / manual index name seen on production (1062 on second variation row). */
    private const LEGACY_ITEM_ID_UNIQUE = 'ebay2_metrics_item_id_unique';

    /** Alternate naming Laravel may have generated for this table. */
    private const ALT_ITEM_ID_UNIQUE = 'ebay_2_metrics_item_id_unique';

    private const COMPOSITE_UNIQUE = 'ebay_2_metrics_item_id_sku_unique';

    public function up(): void
    {
        if (! Schema::hasTable('ebay_2_metrics')) {
            return;
        }

        foreach ([self::LEGACY_ITEM_ID_UNIQUE, self::ALT_ITEM_ID_UNIQUE] as $indexName) {
            if ($this->indexExists('ebay_2_metrics', $indexName)) {
                Schema::table('ebay_2_metrics', function (Blueprint $table) use ($indexName) {
                    $table->dropUnique($indexName);
                });
            }
        }

        if ($this->indexExists('ebay_2_metrics', self::COMPOSITE_UNIQUE)) {
            return;
        }

        Schema::table('ebay_2_metrics', function (Blueprint $table) {
            $table->unique(['item_id', 'sku'], self::COMPOSITE_UNIQUE);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ebay_2_metrics')) {
            return;
        }

        if ($this->indexExists('ebay_2_metrics', self::COMPOSITE_UNIQUE)) {
            Schema::table('ebay_2_metrics', function (Blueprint $table) {
                $table->dropUnique(self::COMPOSITE_UNIQUE);
            });
        }

        // Do not re-apply unique(item_id): after up(), multiple variation rows can share one item_id.
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
