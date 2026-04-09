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
        if (! Schema::hasTable('ebay_priority_reports')) {
            return;
        }
        if (Schema::hasColumn('ebay_priority_reports', 'apprSbid')) {
            return;
        }

        Schema::table('ebay_priority_reports', function (Blueprint $table) {
            $table->string('apprSbid')->nullable()->after('sbid_m');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('ebay_priority_reports')) {
            return;
        }
        if (! Schema::hasColumn('ebay_priority_reports', 'apprSbid')) {
            return;
        }

        Schema::table('ebay_priority_reports', function (Blueprint $table) {
            $table->dropColumn('apprSbid');
        });
    }
};
