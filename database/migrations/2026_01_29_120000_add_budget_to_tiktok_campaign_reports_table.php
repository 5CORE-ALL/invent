<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tiktok_campaign_reports')) {
            return;
        }
        if (Schema::hasColumn('tiktok_campaign_reports', 'budget')) {
            return;
        }

        Schema::table('tiktok_campaign_reports', function (Blueprint $table) {
            $table->decimal('budget', 12, 2)->nullable()->after('cost');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tiktok_campaign_reports')) {
            return;
        }
        if (! Schema::hasColumn('tiktok_campaign_reports', 'budget')) {
            return;
        }

        Schema::table('tiktok_campaign_reports', function (Blueprint $table) {
            $table->dropColumn('budget');
        });
    }
};
