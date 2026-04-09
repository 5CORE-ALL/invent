<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Allow multiple rows per ASIN (e.g. GSP 12150 8 and GSP 1030 8 same ASIN) by keying on SKU.
     */
    public function up(): void
    {
        $table = 'amazon_datsheets';

        if (! Schema::hasTable($table)) {
            return;
        }

        if ($this->indexExists($table, 'amazon_datsheets_asin_unique')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique('amazon_datsheets_asin_unique');
            });
        }

        if (! Schema::hasColumn($table, 'sku')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('sku')->after('asin')->nullable();
            });
        }

        if ($this->indexExists($table, 'amazon_datsheets_sku_unique')) {
            return;
        }

        if ($this->hasDuplicateNonEmptySkus()) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->unique('sku', 'amazon_datsheets_sku_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = 'amazon_datsheets';

        if (! Schema::hasTable($table)) {
            return;
        }

        if ($this->indexExists($table, 'amazon_datsheets_sku_unique')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique('amazon_datsheets_sku_unique');
            });
        }

        if ($this->indexExists($table, 'amazon_datsheets_asin_unique')) {
            return;
        }

        if ($this->hasDuplicateNonEmptyAsins()) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->unique('asin', 'amazon_datsheets_asin_unique');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $schema = DB::connection()->getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $schema)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->limit(1)
            ->exists();
    }

    private function hasDuplicateNonEmptySkus(): bool
    {
        $row = DB::selectOne(
            "SELECT 1 AS `h` FROM `amazon_datsheets` WHERE `sku` IS NOT NULL AND `sku` != '' GROUP BY `sku` HAVING COUNT(*) > 1 LIMIT 1",
        );

        return $row !== null;
    }

    private function hasDuplicateNonEmptyAsins(): bool
    {
        $row = DB::selectOne(
            "SELECT 1 AS `h` FROM `amazon_datsheets` WHERE `asin` IS NOT NULL AND `asin` != '' GROUP BY `asin` HAVING COUNT(*) > 1 LIMIT 1",
        );

        return $row !== null;
    }
};
