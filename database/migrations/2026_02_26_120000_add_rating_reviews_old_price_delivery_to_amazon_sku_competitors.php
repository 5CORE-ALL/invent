<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amazon_sku_competitors', function (Blueprint $table) {
            if (!Schema::hasColumn('amazon_sku_competitors', 'rating')) {
                $table->decimal('rating', 3, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('amazon_sku_competitors', 'reviews')) {
                $table->unsignedInteger('reviews')->nullable()->after('rating');
            }
            if (!Schema::hasColumn('amazon_sku_competitors', 'extracted_old_price')) {
                $table->decimal('extracted_old_price', 10, 2)->nullable()->after('reviews');
            }
            if (!Schema::hasColumn('amazon_sku_competitors', 'delivery')) {
                $table->json('delivery')->nullable()->after('extracted_old_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('amazon_sku_competitors', function (Blueprint $table) {
            $cols = ['rating', 'reviews', 'extracted_old_price', 'delivery'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('amazon_sku_competitors', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
