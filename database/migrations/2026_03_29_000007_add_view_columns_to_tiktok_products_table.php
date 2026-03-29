<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tiktok_products', function (Blueprint $table) {
            if (!Schema::hasColumn('tiktok_products', 'video_views')) {
                $table->unsignedBigInteger('video_views')->default(0)->after('views');
            }
            if (!Schema::hasColumn('tiktok_products', 'ads_views')) {
                $table->unsignedBigInteger('ads_views')->default(0)->after('video_views');
            }
            if (!Schema::hasColumn('tiktok_products', 'affl_views')) {
                $table->unsignedBigInteger('affl_views')->default(0)->after('ads_views');
            }
        });

        // Backfill video_views from legacy "views" column where available.
        DB::table('tiktok_products')
            ->whereNotNull('views')
            ->update([
                'video_views' => DB::raw('COALESCE(views, 0)')
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_products', function (Blueprint $table) {
            if (Schema::hasColumn('tiktok_products', 'video_views')) {
                $table->dropColumn('video_views');
            }
            if (Schema::hasColumn('tiktok_products', 'ads_views')) {
                $table->dropColumn('ads_views');
            }
            if (Schema::hasColumn('tiktok_products', 'affl_views')) {
                $table->dropColumn('affl_views');
            }
        });
    }
};
