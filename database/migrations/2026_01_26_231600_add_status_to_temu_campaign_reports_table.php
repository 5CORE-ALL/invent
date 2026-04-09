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
        if (! Schema::hasTable('temu_campaign_reports')) {
            return;
        }
        if (Schema::hasColumn('temu_campaign_reports', 'status')) {
            return;
        }

        Schema::table('temu_campaign_reports', function (Blueprint $table) {
            $table->string('status', 20)->nullable()->default('Not Created')->after('target');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('temu_campaign_reports')) {
            return;
        }
        if (! Schema::hasColumn('temu_campaign_reports', 'status')) {
            return;
        }

        Schema::table('temu_campaign_reports', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
