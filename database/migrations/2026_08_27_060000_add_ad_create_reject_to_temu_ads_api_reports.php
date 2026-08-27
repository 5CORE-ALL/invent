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
        if (Schema::hasColumn('temu_ads_api_reports', 'ad_create_reject')) {
            return;
        }

        Schema::table('temu_ads_api_reports', function (Blueprint $table) {
            $table->string('ad_create_reject', 500)->nullable()->after('ad_status');
            $table->timestamp('ad_create_reject_at')->nullable()->after('ad_create_reject');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('temu_ads_api_reports')) {
            return;
        }

        Schema::table('temu_ads_api_reports', function (Blueprint $table) {
            if (Schema::hasColumn('temu_ads_api_reports', 'ad_create_reject_at')) {
                $table->dropColumn('ad_create_reject_at');
            }
            if (Schema::hasColumn('temu_ads_api_reports', 'ad_create_reject')) {
                $table->dropColumn('ad_create_reject');
            }
        });
    }
};
