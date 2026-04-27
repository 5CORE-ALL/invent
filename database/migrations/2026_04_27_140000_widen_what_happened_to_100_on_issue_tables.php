<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'qc_and_packing_issues',
        'qc_and_packing_issue_histories',
        'orders_on_hold_issues',
        'orders_on_hold_issue_histories',
        'carrier_issue_issues',
        'carrier_issue_issue_histories',
        'label_issue_issues',
        'label_issue_issue_histories',
        'dispatch_issue_issues',
        'dispatch_issue_issue_histories',
        'listing_issue_issues',
        'listing_issue_issue_histories',
        'c_care_issue_issues',
        'c_care_issue_issue_histories',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tbl) {
            if (! Schema::hasTable($tbl) || ! Schema::hasColumn($tbl, 'what_happened')) {
                continue;
            }
            DB::statement('ALTER TABLE `' . str_replace('`', '``', $tbl) . '` MODIFY `what_happened` VARCHAR(100) NULL');
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tbl) {
            if (! Schema::hasTable($tbl) || ! Schema::hasColumn($tbl, 'what_happened')) {
                continue;
            }
            DB::statement('ALTER TABLE `' . str_replace('`', '``', $tbl) . '` MODIFY `what_happened` VARCHAR(50) NULL');
        }
    }
};
