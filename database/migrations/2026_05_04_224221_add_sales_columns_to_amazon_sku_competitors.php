<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('amazon_sku_competitors', function (Blueprint $table) {
            $table->decimal('monthly_revenue', 10, 2)->nullable()->after('price');
            $table->integer('monthly_units_sold')->nullable()->after('monthly_revenue');
            $table->string('buy_box_owner')->nullable()->after('monthly_units_sold');
            $table->string('seller_type_js')->nullable()->after('buy_box_owner');
            $table->timestamp('sales_data_updated_at')->nullable()->after('seller_type_js');
        });
    }

    public function down()
    {
        Schema::table('amazon_sku_competitors', function (Blueprint $table) {
            $table->dropColumn([
                'monthly_revenue',
                'monthly_units_sold', 
                'buy_box_owner',
                'seller_type_js',
                'sales_data_updated_at'
            ]);
        });
    }
};
