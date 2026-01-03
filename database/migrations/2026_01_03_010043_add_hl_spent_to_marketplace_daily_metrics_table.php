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
        Schema::table('marketplace_daily_metrics', function (Blueprint $table) {
            $table->decimal('hl_spent', 10, 2)->default(0)->after('pmt_spent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_daily_metrics', function (Blueprint $table) {
            $table->dropColumn('hl_spent');
        });
    }
};
