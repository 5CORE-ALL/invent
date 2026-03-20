<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
            $table->decimal('kw_spend', 12, 2)->default(0)->after('ub7_ub1_count');
            $table->decimal('hl_spend', 12, 2)->default(0)->after('kw_spend');
            $table->decimal('pt_spend', 12, 2)->default(0)->after('hl_spend');
        });
    }

    public function down(): void
    {
        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
            $table->dropColumn(['kw_spend', 'hl_spend', 'pt_spend']);
        });
    }
};
