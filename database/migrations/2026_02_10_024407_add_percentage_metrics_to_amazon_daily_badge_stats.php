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
            $table->decimal('gpft_pct', 8, 2)->default(0)->after('total_spend');
            $table->decimal('npft_pct', 8, 2)->default(0)->after('gpft_pct');
            $table->decimal('groi_pct', 8, 2)->default(0)->after('npft_pct');
            $table->decimal('nroi_pct', 8, 2)->default(0)->after('groi_pct');
            $table->decimal('tcos_pct', 8, 2)->default(0)->after('nroi_pct');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
            $table->dropColumn(['gpft_pct', 'npft_pct', 'groi_pct', 'nroi_pct', 'tcos_pct']);
        });
    }
};
