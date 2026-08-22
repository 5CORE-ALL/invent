<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_listing_prices')) {
            return;
        }

        Schema::table('store_listing_prices', function (Blueprint $table) {
            if (! Schema::hasColumn('store_listing_prices', 'views')) {
                $table->unsignedInteger('views')->nullable()->after('currency');
            }
            if (! Schema::hasColumn('store_listing_prices', 'sold')) {
                $table->unsignedInteger('sold')->nullable()->after('views');
            }
            if (! Schema::hasColumn('store_listing_prices', 'qty')) {
                $table->integer('qty')->nullable()->after('sold');
            }
            if (! Schema::hasColumn('store_listing_prices', 'is_in_stock')) {
                $table->boolean('is_in_stock')->nullable()->after('qty');
            }
            if (! Schema::hasColumn('store_listing_prices', 'url')) {
                $table->string('url', 500)->nullable()->after('is_in_stock');
            }
            if (! Schema::hasColumn('store_listing_prices', 'brand')) {
                $table->string('brand', 191)->nullable()->after('url');
            }
            if (! Schema::hasColumn('store_listing_prices', 'rating_percent')) {
                $table->decimal('rating_percent', 8, 2)->nullable()->after('brand');
            }
            if (! Schema::hasColumn('store_listing_prices', 'base_image')) {
                $table->string('base_image', 500)->nullable()->after('rating_percent');
            }
            if (! Schema::hasColumn('store_listing_prices', 'categories_json')) {
                $table->json('categories_json')->nullable()->after('base_image');
            }
            if (! Schema::hasColumn('store_listing_prices', 'tags_json')) {
                $table->json('tags_json')->nullable()->after('categories_json');
            }
            if (! Schema::hasColumn('store_listing_prices', 'images_json')) {
                $table->json('images_json')->nullable()->after('tags_json');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('store_listing_prices')) {
            return;
        }

        Schema::table('store_listing_prices', function (Blueprint $table) {
            foreach ([
                'views', 'sold', 'qty', 'is_in_stock', 'url', 'brand',
                'rating_percent', 'base_image', 'categories_json', 'tags_json', 'images_json',
            ] as $column) {
                if (Schema::hasColumn('store_listing_prices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
