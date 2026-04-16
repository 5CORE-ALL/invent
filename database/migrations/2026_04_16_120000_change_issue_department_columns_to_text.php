<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @return list<string> */
    private function tables(): array
    {
        return [
            'dispatch_issue_issues',
            'dispatch_issue_issue_histories',
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
            if (! Schema::hasTable($tbl) || ! Schema::hasColumn($tbl, 'department')) {
                continue;
            }
            DB::statement('ALTER TABLE `'.$tbl.'` MODIFY `department` TEXT NULL');
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as $tbl) {
            if (! Schema::hasTable($tbl) || ! Schema::hasColumn($tbl, 'department')) {
                continue;
            }
            DB::statement('ALTER TABLE `'.$tbl.'` MODIFY `department` VARCHAR(100) NULL');
        }
    }
};
