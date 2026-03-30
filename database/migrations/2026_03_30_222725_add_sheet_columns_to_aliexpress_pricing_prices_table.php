<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aliexpress_pricing_prices', function (Blueprint $table) {
            $table->string('product_id')->nullable()->after('sku');
            $table->string('product_name')->nullable()->after('product_id');
            $table->string('sku_id')->nullable()->after('product_name');
            $table->unsignedInteger('ae_stock')->default(0)->after('price');
            $table->string('sales_attributes')->nullable()->after('ae_stock');
        });
    }

    public function down(): void
    {
        Schema::table('aliexpress_pricing_prices', function (Blueprint $table) {
            $table->dropColumn(['product_id', 'product_name', 'sku_id', 'ae_stock', 'sales_attributes']);
        });
    }
};
