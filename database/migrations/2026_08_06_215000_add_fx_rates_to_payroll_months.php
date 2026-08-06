<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_months', function (Blueprint $table) {
            $table->decimal('inr_usd_rate', 12, 4)->nullable()->after('notes');
            $table->decimal('inr_cny_rate', 12, 4)->nullable()->after('inr_usd_rate');
            $table->timestamp('fx_rates_fetched_at')->nullable()->after('inr_cny_rate');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_months', function (Blueprint $table) {
            $table->dropColumn(['inr_usd_rate', 'inr_cny_rate', 'fx_rates_fetched_at']);
        });
    }
};
