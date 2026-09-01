<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('faire_products_sheets')) {
            Schema::create('faire_products_sheets', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->unique()->nullable();
                $table->string('product_name', 500)->nullable();
                $table->string('type', 191)->nullable();
                $table->integer('f_l30')->nullable();
                $table->integer('f_l60')->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->integer('views')->nullable();
                $table->unsignedInteger('views_l1')->nullable();
                $table->unsignedInteger('views_l7')->nullable();
                $table->unsignedInteger('orders')->nullable();
                $table->unsignedInteger('units_sold')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('faire_products_sheets', function (Blueprint $table) {
            if (! Schema::hasColumn('faire_products_sheets', 'product_name')) {
                $table->string('product_name', 500)->nullable()->after('sku');
            }
            if (! Schema::hasColumn('faire_products_sheets', 'type')) {
                $table->string('type', 191)->nullable()->after('product_name');
            }
            if (! Schema::hasColumn('faire_products_sheets', 'orders')) {
                $table->unsignedInteger('orders')->nullable();
            }
            if (! Schema::hasColumn('faire_products_sheets', 'units_sold')) {
                $table->unsignedInteger('units_sold')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('faire_products_sheets')) {
            return;
        }

        $drop = array_values(array_filter(
            ['product_name', 'type', 'orders', 'units_sold'],
            fn ($col) => Schema::hasColumn('faire_products_sheets', $col)
        ));
        if ($drop === []) {
            return;
        }

        Schema::table('faire_products_sheets', function (Blueprint $table) use ($drop) {
            $table->dropColumn($drop);
        });
    }
};
