<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amazon_competitor_asins', function (Blueprint $table) {
            if (!Schema::hasColumn('amazon_competitor_asins', 'extracted_old_price')) {
                $table->decimal('extracted_old_price', 10, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('amazon_competitor_asins', 'delivery')) {
                $table->json('delivery')->nullable()->after('reviews');
            }
        });
    }

    public function down(): void
    {
        Schema::table('amazon_competitor_asins', function (Blueprint $table) {
            if (Schema::hasColumn('amazon_competitor_asins', 'extracted_old_price')) {
                $table->dropColumn('extracted_old_price');
            }
            if (Schema::hasColumn('amazon_competitor_asins', 'delivery')) {
                $table->dropColumn('delivery');
            }
        });
    }
};
