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
            $table->decimal('total_pft', 12, 2)->default(0)->after('pt_spend');
            $table->decimal('total_sales', 12, 2)->default(0)->after('total_pft');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
            $table->dropColumn(['total_pft', 'total_sales']);
        });
    }
};
