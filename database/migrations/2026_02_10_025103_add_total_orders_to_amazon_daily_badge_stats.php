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
        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
            $table->integer('total_l30_orders')->default(0)->after('tcos_pct');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
            $table->dropColumn('total_l30_orders');
        });
    }
};
