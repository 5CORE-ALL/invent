<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Dispatch already has department (2026_04_02 migration). */
    private function tables(): array
    {
        return [
            'label_issue_issues',
            'label_issue_issue_histories',
            'carrier_issue_issues',
            'carrier_issue_issue_histories',
            'other_issue_issues',
            'other_issue_issue_histories',
            'c_care_issue_issues',
            'c_care_issue_issue_histories',
            'listing_issue_issues',
            'listing_issue_issue_histories',
            'qc_and_packing_issues',
            'qc_and_packing_issue_histories',
        ];
    }

    public function up(): void
    {
        foreach ($this->tables() as $tbl) {
            if (! Schema::hasTable($tbl) || Schema::hasColumn($tbl, 'department')) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) {
                $table->string('department', 100)->nullable()->after('c_action_1_remark');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as $tbl) {
            if (! Schema::hasTable($tbl) || ! Schema::hasColumn($tbl, 'department')) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) {
                $table->dropColumn('department');
            });
        }
    }
};
