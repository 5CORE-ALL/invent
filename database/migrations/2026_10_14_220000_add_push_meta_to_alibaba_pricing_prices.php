<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push meta for the server auto push (alibaba:sprc-dil-auto-push): last pushed price + time, and the S PRC status.
 * The blue catch-up needs these so a SKU that was just pushed is not pushed again every 10 minutes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alibaba_pricing_prices')) {
            return;
        }

        Schema::table('alibaba_pricing_prices', function (Blueprint $table) {
            if (! Schema::hasColumn('alibaba_pricing_prices', 'sprice_status')) {
                $table->string('sprice_status', 20)->nullable();
            }
            if (! Schema::hasColumn('alibaba_pricing_prices', 'sprice_status_at')) {
                $table->timestamp('sprice_status_at')->nullable();
            }
            if (! Schema::hasColumn('alibaba_pricing_prices', 'sprice_pushed_value')) {
                $table->decimal('sprice_pushed_value', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('alibaba_pricing_prices', 'sprice_pushed_at')) {
                $table->timestamp('sprice_pushed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('alibaba_pricing_prices')) {
            return;
        }

        Schema::table('alibaba_pricing_prices', function (Blueprint $table) {
            foreach (['sprice_status', 'sprice_status_at', 'sprice_pushed_value', 'sprice_pushed_at'] as $col) {
                if (Schema::hasColumn('alibaba_pricing_prices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
