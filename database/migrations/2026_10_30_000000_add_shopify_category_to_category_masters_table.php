<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category_masters')) {
            return;
        }

        Schema::table('category_masters', function (Blueprint $table) {
            // Read once from Shopify per SKU. synced_at marks the SKU as already looked up,
            // so a SKU that Shopify does not know is not asked for again on every page load.
            if (! Schema::hasColumn('category_masters', 'category_shopify')) {
                $table->string('category_shopify', 255)->nullable()->after('category_temu_id');
            }
            if (! Schema::hasColumn('category_masters', 'category_shopify_id')) {
                $table->string('category_shopify_id', 64)->nullable()->after('category_shopify');
            }
            if (! Schema::hasColumn('category_masters', 'category_shopify_synced_at')) {
                $table->timestamp('category_shopify_synced_at')->nullable()->after('category_shopify_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('category_masters')) {
            return;
        }

        Schema::table('category_masters', function (Blueprint $table) {
            foreach (['category_shopify', 'category_shopify_id', 'category_shopify_synced_at'] as $column) {
                if (Schema::hasColumn('category_masters', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
