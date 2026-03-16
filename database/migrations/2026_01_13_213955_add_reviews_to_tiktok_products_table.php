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
        Schema::table('tiktok_products', function (Blueprint $table) {
            if (!Schema::hasColumn('tiktok_products', 'reviews')) {
                $table->integer('reviews')->nullable()->default(0)->comment('Number of reviews/ratings from TikTok API')->after('views');
            }
            if (!Schema::hasColumn('tiktok_products', 'rating')) {
                $table->decimal('rating', 3, 2)->nullable()->comment('Average rating (0.00-5.00) from TikTok API')->after('reviews');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_products', function (Blueprint $table) {
            if (Schema::hasColumn('tiktok_products', 'reviews')) {
                $table->dropColumn('reviews');
            }
            if (Schema::hasColumn('tiktok_products', 'rating')) {
                $table->dropColumn('rating');
            }
        });
    }
};
