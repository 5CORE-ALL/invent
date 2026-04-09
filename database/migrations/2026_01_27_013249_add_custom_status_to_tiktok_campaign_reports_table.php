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
        if (! Schema::hasTable('tiktok_campaign_reports')) {
            return;
        }
        if (Schema::hasColumn('tiktok_campaign_reports', 'custom_status')) {
            return;
        }

        Schema::table('tiktok_campaign_reports', function (Blueprint $table) {
            $table->string('custom_status')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('tiktok_campaign_reports')) {
            return;
        }
        if (! Schema::hasColumn('tiktok_campaign_reports', 'custom_status')) {
            return;
        }

        Schema::table('tiktok_campaign_reports', function (Blueprint $table) {
            $table->dropColumn('custom_status');
        });
    }
};
