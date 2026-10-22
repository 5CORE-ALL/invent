<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LT CPC on /google/shopping/google-shopping: today's average CPC (spend ÷ clicks) read live from Google Ads.
 */
return new class extends Migration
{
    private const TABLE = 'google_shopping_live_syncs';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || Schema::hasColumn(self::TABLE, 'live_cpc')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->decimal('live_cpc', 12, 4)->nullable();
            $table->unsignedInteger('live_cpc_clicks')->nullable();
            $table->decimal('live_cpc_cost', 12, 2)->nullable();
            $table->boolean('cpc_fetch_ok')->default(false);
            $table->string('cpc_fetch_error', 180)->nullable();
            $table->timestamp('cpc_fetched_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, 'live_cpc')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn(['live_cpc', 'live_cpc_clicks', 'live_cpc_cost', 'cpc_fetch_ok', 'cpc_fetch_error', 'cpc_fetched_at']);
        });
    }
};
