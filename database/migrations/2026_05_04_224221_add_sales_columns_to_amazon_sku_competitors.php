<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('amazon_sku_competitors')) {
            return;
        }

        if (! Schema::hasColumn('amazon_sku_competitors', 'monthly_revenue')) {
            Schema::table('amazon_sku_competitors', function (Blueprint $table) {
                $table->decimal('monthly_revenue', 10, 2)->nullable()->after('price');
            });
        }

        if (! Schema::hasColumn('amazon_sku_competitors', 'monthly_units_sold')) {
            Schema::table('amazon_sku_competitors', function (Blueprint $table) {
                $table->integer('monthly_units_sold')->nullable()->after('monthly_revenue');
            });
        }

        if (! Schema::hasColumn('amazon_sku_competitors', 'buy_box_owner')) {
            Schema::table('amazon_sku_competitors', function (Blueprint $table) {
                $table->string('buy_box_owner')->nullable()->after('monthly_units_sold');
            });
        }

        if (! Schema::hasColumn('amazon_sku_competitors', 'seller_type_js')) {
            Schema::table('amazon_sku_competitors', function (Blueprint $table) {
                $table->string('seller_type_js')->nullable()->after('buy_box_owner');
            });
        }

        if (! Schema::hasColumn('amazon_sku_competitors', 'sales_data_updated_at')) {
            Schema::table('amazon_sku_competitors', function (Blueprint $table) {
                $table->timestamp('sales_data_updated_at')->nullable()->after('seller_type_js');
            });
        }
    }

    public function down()
    {
        if (! Schema::hasTable('amazon_sku_competitors')) {
            return;
        }

        $columns = array_filter([
            'monthly_revenue',
            'monthly_units_sold',
            'buy_box_owner',
            'seller_type_js',
            'sales_data_updated_at',
        ], fn (string $column): bool => Schema::hasColumn('amazon_sku_competitors', $column));

        if (! empty($columns)) {
            Schema::table('amazon_sku_competitors', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
