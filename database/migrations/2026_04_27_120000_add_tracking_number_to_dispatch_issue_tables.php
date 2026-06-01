<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Original shipment / carrier tracking (distinct from replacement_tracking "Track" column).
     */
    public function up(): void
    {
        foreach (['dispatch_issue_issues', 'dispatch_issue_issue_histories'] as $tbl) {
            if (! Schema::hasTable($tbl) || Schema::hasColumn($tbl, 'tracking_number')) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (Schema::hasColumn($tbl, 'replacement_tracking')) {
                    $table->string('tracking_number', 50)->nullable()->before('replacement_tracking');
                } else {
                    $table->string('tracking_number', 50)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['dispatch_issue_issues', 'dispatch_issue_issue_histories'] as $tbl) {
            if (! Schema::hasTable($tbl) || ! Schema::hasColumn($tbl, 'tracking_number')) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) {
                $table->dropColumn('tracking_number');
            });
        }
    }
};
