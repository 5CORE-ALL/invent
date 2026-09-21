<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('temu_ads_api_reports')) {
            return;
        }

        if (! Schema::hasColumn('temu_ads_api_reports', 'sku_id')) {
            Schema::table('temu_ads_api_reports', function (Blueprint $table) {
                $table->string('sku_id')->nullable()->index()->after('sku');
            });
        }

        if ($this->indexExists('temu_ads_api_reports', 'temu_ads_api_reports_goods_period_unique')) {
            Schema::table('temu_ads_api_reports', function (Blueprint $table) {
                $table->dropUnique('temu_ads_api_reports_goods_period_unique');
            });
        }

        if (! $this->indexExists('temu_ads_api_reports', 'temu_ads_api_reports_goods_sku_period_unique')) {
            Schema::table('temu_ads_api_reports', function (Blueprint $table) {
                $table->unique(['goods_id', 'sku_id', 'period'], 'temu_ads_api_reports_goods_sku_period_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('temu_ads_api_reports')) {
            return;
        }

        if ($this->indexExists('temu_ads_api_reports', 'temu_ads_api_reports_goods_sku_period_unique')) {
            Schema::table('temu_ads_api_reports', function (Blueprint $table) {
                $table->dropUnique('temu_ads_api_reports_goods_sku_period_unique');
            });
        }

        if (! $this->indexExists('temu_ads_api_reports', 'temu_ads_api_reports_goods_period_unique')) {
            Schema::table('temu_ads_api_reports', function (Blueprint $table) {
                $table->unique(['goods_id', 'period'], 'temu_ads_api_reports_goods_period_unique');
            });
        }

        if (Schema::hasColumn('temu_ads_api_reports', 'sku_id')) {
            Schema::table('temu_ads_api_reports', function (Blueprint $table) {
                $table->dropColumn('sku_id');
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
