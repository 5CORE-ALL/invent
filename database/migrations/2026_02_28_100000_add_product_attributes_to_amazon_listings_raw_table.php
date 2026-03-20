<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add product attribute columns to amazon_listings_raw for complete product data.
     * Maps Amazon UI field names to our system fields.
     */
    public function up(): void
    {
        Schema::table('amazon_listings_raw', function (Blueprint $table) {
            $table->string('color')->nullable()->after('asin1');
            $table->string('material')->nullable()->after('color');
            $table->string('style')->nullable()->after('material');
            $table->string('size')->nullable()->after('style');
            $table->string('model_number')->nullable()->after('size');
            $table->string('part_number')->nullable()->after('model_number');
            $table->string('manufacturer')->nullable()->after('part_number');
            $table->string('exterior_finish')->nullable()->after('manufacturer');
            $table->integer('number_of_items')->nullable()->after('exterior_finish');
            $table->boolean('assembly_required')->default(false)->after('number_of_items');
            $table->string('item_type_keyword')->nullable()->after('assembly_required');
            $table->text('generic_keyword')->nullable()->after('item_type_keyword');
            $table->integer('handling_time')->nullable()->after('generic_keyword');
            $table->string('merchant_shipping_group')->nullable()->after('handling_time');
            $table->decimal('minimum_advertised_price', 10, 2)->nullable()->after('merchant_shipping_group');
            $table->decimal('list_price', 10, 2)->nullable()->after('minimum_advertised_price');
            $table->string('country_of_origin')->nullable()->after('list_price');
            $table->text('warranty_description')->nullable()->after('country_of_origin');
            $table->string('voltage')->nullable()->after('warranty_description');
            $table->string('noise_level')->nullable()->after('voltage');
            $table->string('item_dimensions')->nullable()->after('noise_level');
            $table->text('included_components')->nullable()->after('item_dimensions');
        });
    }

    public function down(): void
    {
        Schema::table('amazon_listings_raw', function (Blueprint $table) {
            $table->dropColumn([
                'color', 'material', 'style', 'size', 'model_number', 'part_number',
                'manufacturer', 'exterior_finish', 'number_of_items', 'assembly_required',
                'item_type_keyword', 'generic_keyword', 'handling_time', 'merchant_shipping_group',
                'minimum_advertised_price', 'list_price', 'country_of_origin', 'warranty_description',
                'voltage', 'noise_level', 'item_dimensions', 'included_components',
            ]);
        });
    }
};
