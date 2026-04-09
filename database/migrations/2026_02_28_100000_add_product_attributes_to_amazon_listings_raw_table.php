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
        $tableName = 'amazon_listings_raw';

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $specs = [
            ['color', 'asin1', fn (Blueprint $t) => $t->string('color')->nullable()],
            ['material', 'color', fn (Blueprint $t) => $t->string('material')->nullable()],
            ['style', 'material', fn (Blueprint $t) => $t->string('style')->nullable()],
            ['size', 'style', fn (Blueprint $t) => $t->string('size')->nullable()],
            ['model_number', 'size', fn (Blueprint $t) => $t->string('model_number')->nullable()],
            ['part_number', 'model_number', fn (Blueprint $t) => $t->string('part_number')->nullable()],
            ['manufacturer', 'part_number', fn (Blueprint $t) => $t->string('manufacturer')->nullable()],
            ['exterior_finish', 'manufacturer', fn (Blueprint $t) => $t->string('exterior_finish')->nullable()],
            ['number_of_items', 'exterior_finish', fn (Blueprint $t) => $t->integer('number_of_items')->nullable()],
            ['assembly_required', 'number_of_items', fn (Blueprint $t) => $t->boolean('assembly_required')->default(false)],
            ['item_type_keyword', 'assembly_required', fn (Blueprint $t) => $t->string('item_type_keyword')->nullable()],
            ['generic_keyword', 'item_type_keyword', fn (Blueprint $t) => $t->text('generic_keyword')->nullable()],
            ['handling_time', 'generic_keyword', fn (Blueprint $t) => $t->integer('handling_time')->nullable()],
            ['merchant_shipping_group', 'handling_time', fn (Blueprint $t) => $t->string('merchant_shipping_group')->nullable()],
            ['minimum_advertised_price', 'merchant_shipping_group', fn (Blueprint $t) => $t->decimal('minimum_advertised_price', 10, 2)->nullable()],
            ['list_price', 'minimum_advertised_price', fn (Blueprint $t) => $t->decimal('list_price', 10, 2)->nullable()],
            ['country_of_origin', 'list_price', fn (Blueprint $t) => $t->string('country_of_origin')->nullable()],
            ['warranty_description', 'country_of_origin', fn (Blueprint $t) => $t->text('warranty_description')->nullable()],
            ['voltage', 'warranty_description', fn (Blueprint $t) => $t->string('voltage')->nullable()],
            ['noise_level', 'voltage', fn (Blueprint $t) => $t->string('noise_level')->nullable()],
            ['item_dimensions', 'noise_level', fn (Blueprint $t) => $t->string('item_dimensions')->nullable()],
            ['included_components', 'item_dimensions', fn (Blueprint $t) => $t->text('included_components')->nullable()],
        ];

        foreach ($specs as [$column, $after, $add]) {
            if (Schema::hasColumn($tableName, $column)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $after, $add) {
                $col = $add($table);
                if ($after !== null && Schema::hasColumn($tableName, $after)) {
                    $col->after($after);
                }
            });
        }
    }

    public function down(): void
    {
        $tableName = 'amazon_listings_raw';

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $names = [
            'included_components', 'item_dimensions', 'noise_level', 'voltage', 'warranty_description',
            'country_of_origin', 'list_price', 'minimum_advertised_price', 'merchant_shipping_group',
            'handling_time', 'generic_keyword', 'item_type_keyword', 'assembly_required', 'number_of_items',
            'exterior_finish', 'manufacturer', 'part_number', 'model_number', 'size', 'style', 'material', 'color',
        ];

        $toDrop = array_values(array_filter($names, fn (string $n) => Schema::hasColumn($tableName, $n)));

        if ($toDrop !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }
    }
};
