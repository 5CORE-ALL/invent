<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('temu_ads_api_reports')) {
            return;
        }

        Schema::table('temu_ads_api_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('temu_ads_api_reports', 'sku_id')) {
                $table->string('sku_id')->nullable()->index()->after('sku');
            }
        });

        Schema::table('temu_ads_api_reports', function (Blueprint $table) {
            try {
                $table->dropUnique('temu_ads_api_reports_goods_period_unique');
            } catch (\Throwable) {
            }
            try {
                $table->unique(['goods_id', 'sku_id', 'period'], 'temu_ads_api_reports_goods_sku_period_unique');
            } catch (\Throwable) {
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('temu_ads_api_reports')) {
            return;
        }

        Schema::table('temu_ads_api_reports', function (Blueprint $table) {
            try {
                $table->dropUnique('temu_ads_api_reports_goods_sku_period_unique');
            } catch (\Throwable) {
            }
            try {
                $table->unique(['goods_id', 'period'], 'temu_ads_api_reports_goods_period_unique');
            } catch (\Throwable) {
            }
            if (Schema::hasColumn('temu_ads_api_reports', 'sku_id')) {
                $table->dropColumn('sku_id');
            }
        });
    }
};
