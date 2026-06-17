<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'ebay_metrics',
            'ebay_2_metrics',
            'ebay_3_metrics',
            'amazon_metrics',
            'temu_metrics',
            'macy_metrics',
            'reverb_products',
            'shopify_catalog_products',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'image_urls')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->longText('image_urls')->nullable();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'ebay_metrics',
            'ebay_2_metrics',
            'ebay_3_metrics',
            'amazon_metrics',
            'temu_metrics',
            'macy_metrics',
            'reverb_products',
            'shopify_catalog_products',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'image_urls')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('image_urls');
            });
        }
    }
};
