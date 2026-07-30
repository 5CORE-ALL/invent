<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('amazon_sprice_cvr_daily_stats')) {
            return;
        }
        if (Schema::hasColumn('amazon_sprice_cvr_daily_stats', 'rule_pies')) {
            return;
        }

        Schema::table('amazon_sprice_cvr_daily_stats', function (Blueprint $table) {
            $table->json('rule_pies')->nullable()->after('high_cvr');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('amazon_sprice_cvr_daily_stats')) {
            return;
        }
        if (! Schema::hasColumn('amazon_sprice_cvr_daily_stats', 'rule_pies')) {
            return;
        }

        Schema::table('amazon_sprice_cvr_daily_stats', function (Blueprint $table) {
            $table->dropColumn('rule_pies');
        });
    }
};
