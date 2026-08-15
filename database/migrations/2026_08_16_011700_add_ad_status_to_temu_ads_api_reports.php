<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('temu_ads_api_reports')) {
            return;
        }
        if (Schema::hasColumn('temu_ads_api_reports', 'ad_status')) {
            return;
        }

        Schema::table('temu_ads_api_reports', function (Blueprint $table) {
            $table->string('ad_status', 32)->nullable()->after('acos')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('temu_ads_api_reports') || ! Schema::hasColumn('temu_ads_api_reports', 'ad_status')) {
            return;
        }

        Schema::table('temu_ads_api_reports', function (Blueprint $table) {
            $table->dropColumn('ad_status');
        });
    }
};
