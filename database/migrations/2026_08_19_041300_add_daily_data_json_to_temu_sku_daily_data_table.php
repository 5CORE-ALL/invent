<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('temu_sku_daily_data')) {
            return;
        }
        if (Schema::hasColumn('temu_sku_daily_data', 'daily_data')) {
            return;
        }

        Schema::table('temu_sku_daily_data', function (Blueprint $table) {
            $table->json('daily_data')->nullable()->after('spend');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('temu_sku_daily_data') || ! Schema::hasColumn('temu_sku_daily_data', 'daily_data')) {
            return;
        }

        Schema::table('temu_sku_daily_data', function (Blueprint $table) {
            $table->dropColumn('daily_data');
        });
    }
};
