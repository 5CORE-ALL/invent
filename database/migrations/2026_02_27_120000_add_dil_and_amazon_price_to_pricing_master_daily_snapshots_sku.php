<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pricing_master_daily_snapshots_sku', function (Blueprint $table) {
            $table->decimal('dil_percent', 10, 2)->nullable()->after('avg_cvr');
            $table->decimal('amazon_price', 12, 2)->nullable()->after('dil_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pricing_master_daily_snapshots_sku', function (Blueprint $table) {
            $table->dropColumn(['dil_percent', 'amazon_price']);
        });
    }
};
