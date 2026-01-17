<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('amazon_sku_competitors', function (Blueprint $table) {
            if (!Schema::hasColumn('amazon_sku_competitors', 'product_title')) {
                $table->text('product_title')->nullable()->after('marketplace');
            }
            if (!Schema::hasColumn('amazon_sku_competitors', 'product_link')) {
                $table->text('product_link')->nullable()->after('product_title');
            }
            if (!Schema::hasColumn('amazon_sku_competitors', 'price')) {
                $table->decimal('price', 10, 2)->nullable()->after('product_link');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amazon_sku_competitors', function (Blueprint $table) {
            $table->dropColumn(['product_title', 'product_link', 'price']);
        });
    }
};
