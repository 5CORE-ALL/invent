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

        Schema::table('temu_ads_api_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('temu_ads_api_reports', 'pause_run_ok')) {
                $table->boolean('pause_run_ok')->nullable()->after('ad_create_reject_at');
            }
            if (! Schema::hasColumn('temu_ads_api_reports', 'pause_run_error')) {
                $table->string('pause_run_error', 500)->nullable()->after('pause_run_ok');
            }
            if (! Schema::hasColumn('temu_ads_api_reports', 'pause_run_at')) {
                $table->timestamp('pause_run_at')->nullable()->after('pause_run_error');
            }
            if (! Schema::hasColumn('temu_ads_api_reports', 'pause_run_history')) {
                $table->json('pause_run_history')->nullable()->after('pause_run_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('temu_ads_api_reports')) {
            return;
        }

        Schema::table('temu_ads_api_reports', function (Blueprint $table) {
            foreach (['pause_run_history', 'pause_run_at', 'pause_run_error', 'pause_run_ok'] as $col) {
                if (Schema::hasColumn('temu_ads_api_reports', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
