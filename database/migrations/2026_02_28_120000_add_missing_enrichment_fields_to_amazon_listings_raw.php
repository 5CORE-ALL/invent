<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing columns for 26-required-field enrichment from Catalog + Listings APIs.
     */
    public function up(): void
    {
        Schema::table('amazon_listings_raw', function (Blueprint $table) {
            if (! Schema::hasColumn('amazon_listings_raw', 'item_name')) {
                $table->string('item_name', 500)->nullable()->after('asin1');
            }
            if (! Schema::hasColumn('amazon_listings_raw', 'external_product_id')) {
                $table->string('external_product_id', 50)->nullable()->after('asin1');
            }
            if (! Schema::hasColumn('amazon_listings_raw', 'model_name')) {
                $table->string('model_name')->nullable()->after('model_number');
            }
            if (! Schema::hasColumn('amazon_listings_raw', 'product_description')) {
                $table->text('product_description')->nullable()->after('generic_keyword');
            }
            if (! Schema::hasColumn('amazon_listings_raw', 'product_type')) {
                $table->string('product_type')->nullable()->after('item_type_keyword');
            }
            if (! Schema::hasColumn('amazon_listings_raw', 'quantity')) {
                $table->integer('quantity')->nullable()->after('product_type');
            }
            if (! Schema::hasColumn('amazon_listings_raw', 'your_price')) {
                $table->decimal('your_price', 10, 2)->nullable()->after('minimum_advertised_price');
            }
            if (! Schema::hasColumn('amazon_listings_raw', 'bullet_point')) {
                $table->json('bullet_point')->nullable()->after('product_description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('amazon_listings_raw', function (Blueprint $table) {
            $columns = ['item_name', 'external_product_id', 'model_name', 'product_description', 'product_type', 'quantity', 'your_price', 'bullet_point'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('amazon_listings_raw', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
