<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stored HTML templates for Description Master “Advanced / HTML Editor” mode (Shopify body_html).
     */
    public function up(): void
    {
        foreach (['shopify_metrics', 'shopify_pls_metrics'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'shopify_custom_html')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->longText('shopify_custom_html')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['shopify_metrics', 'shopify_pls_metrics'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'shopify_custom_html')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('shopify_custom_html');
                });
            }
        }
    }
};
