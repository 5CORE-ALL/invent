<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Description Master "A+" snapshot: the live Shopify body_html (with product images) is fetched
     * once per SKU and kept here so the page can show it without hitting Shopify on every view.
     */
    public function up(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) {
            if (! Schema::hasColumn('product_master', 'shopify_aplus_content')) {
                $table->longText('shopify_aplus_content')->nullable();
            }
            if (! Schema::hasColumn('product_master', 'shopify_aplus_images')) {
                $table->longText('shopify_aplus_images')->nullable();
            }
            if (! Schema::hasColumn('product_master', 'shopify_aplus_fetched_at')) {
                $table->timestamp('shopify_aplus_fetched_at')->nullable();
            }
            if (! Schema::hasColumn('product_master', 'shopify_aplus_fetch_error')) {
                $table->text('shopify_aplus_fetch_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) {
            foreach (['shopify_aplus_content', 'shopify_aplus_images', 'shopify_aplus_fetched_at', 'shopify_aplus_fetch_error'] as $column) {
                if (Schema::hasColumn('product_master', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
