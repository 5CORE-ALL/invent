<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopify_catalog_products')) {
            return;
        }

        Schema::table('shopify_catalog_products', function (Blueprint $table) {
            if (! Schema::hasColumn('shopify_catalog_products', 'image_src')) {
                $table->string('image_src')->nullable()->after('product_type');
            }
            if (! Schema::hasColumn('shopify_catalog_products', 'images')) {
                $table->longText('images')->nullable()->after('image_src');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shopify_catalog_products')) {
            return;
        }

        Schema::table('shopify_catalog_products', function (Blueprint $table) {
            if (Schema::hasColumn('shopify_catalog_products', 'images')) {
                $table->dropColumn('images');
            }
            if (Schema::hasColumn('shopify_catalog_products', 'image_src')) {
                $table->dropColumn('image_src');
            }
        });
    }
};
