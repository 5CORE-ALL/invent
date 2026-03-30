<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * JSON array of image URLs (or paths) last pushed / saved per marketplace for Image Master.
     */
    public function up(): void
    {
        $tables = [
            'ebay_metrics',
            'ebay2_metrics',
            'ebay_2_metrics',
            'ebay3_metrics',
            'ebay_3_metrics',
            'walmart_metrics',
            'macy_metrics',
            'aliexpress_metrics',
            'faire_metrics',
            'temu_metrics',
            'amazon_metrics',
            'reverb_metrics',
            'shopify_metrics',
            'shopify_pls_metrics',
            'doba_metrics',
            'wayfair_metrics',
            'shein_metrics',
            'bestbuy_metrics',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'image_master_json')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->longText('image_master_json')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'ebay_metrics',
            'ebay2_metrics',
            'ebay_2_metrics',
            'ebay3_metrics',
            'ebay_3_metrics',
            'walmart_metrics',
            'macy_metrics',
            'aliexpress_metrics',
            'faire_metrics',
            'temu_metrics',
            'amazon_metrics',
            'reverb_metrics',
            'shopify_metrics',
            'shopify_pls_metrics',
            'doba_metrics',
            'wayfair_metrics',
            'shein_metrics',
            'bestbuy_metrics',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'image_master_json')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('image_master_json');
                });
            }
        }
    }
};
