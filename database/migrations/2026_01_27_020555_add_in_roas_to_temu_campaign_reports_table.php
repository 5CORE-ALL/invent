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
        if (Schema::hasColumn('temu_campaign_reports', 'in_roas')) {
            return;
        }

        Schema::table('temu_campaign_reports', function (Blueprint $table) {
            $table->decimal('in_roas', 10, 2)->nullable()->after('roas');
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
        if (! Schema::hasColumn('temu_campaign_reports', 'in_roas')) {
            return;
        }

        Schema::table('temu_campaign_reports', function (Blueprint $table) {
            $table->dropColumn('in_roas');
        });
    }
};
