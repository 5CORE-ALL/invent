<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the reason of the last failed Alibaba price push in the database (it used to live only in the cache, so a
 * cache clear or expiry turned a dead listing into a permanent blue triangle that was retried every 2 hours).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alibaba_pricing_prices') || Schema::hasColumn('alibaba_pricing_prices', 'sprice_error')) {
            return;
        }

        Schema::table('alibaba_pricing_prices', function (Blueprint $table) {
            $table->text('sprice_error')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('alibaba_pricing_prices') && Schema::hasColumn('alibaba_pricing_prices', 'sprice_error')) {
            Schema::table('alibaba_pricing_prices', function (Blueprint $table) {
                $table->dropColumn('sprice_error');
            });
        }
    }
};
