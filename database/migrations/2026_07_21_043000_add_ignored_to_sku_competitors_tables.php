<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['amazon_sku_competitors', 'ebay_sku_competitors', 'google_sku_competitors'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'ignored')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->boolean('ignored')->default(false)->after('price');
            });
        }
    }

    public function down(): void
    {
        foreach (['amazon_sku_competitors', 'ebay_sku_competitors', 'google_sku_competitors'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'ignored')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('ignored');
            });
        }
    }
};
