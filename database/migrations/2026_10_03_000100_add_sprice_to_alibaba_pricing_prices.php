<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alibaba_pricing_prices')) {
            return;
        }
        if (Schema::hasColumn('alibaba_pricing_prices', 'sprice')) {
            return;
        }

        Schema::table('alibaba_pricing_prices', function (Blueprint $table) {
            $table->decimal('sprice', 12, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('alibaba_pricing_prices') || ! Schema::hasColumn('alibaba_pricing_prices', 'sprice')) {
            return;
        }

        Schema::table('alibaba_pricing_prices', function (Blueprint $table) {
            $table->dropColumn('sprice');
        });
    }
};
