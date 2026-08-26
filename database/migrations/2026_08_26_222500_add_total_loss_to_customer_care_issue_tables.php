<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Boards that did not inherit total_loss from the dispatch-issue extras migration. */
    private function tables(): array
    {
        return [
            'qc_and_packing_issues',
            'qc_and_packing_issue_histories',
            'label_issue_issues',
            'label_issue_issue_histories',
            'other_issue_issues',
            'other_issue_issue_histories',
            'c_care_issue_issues',
            'c_care_issue_issue_histories',
            'listing_issue_issues',
            'listing_issue_issue_histories',
        ];
    }

    public function up(): void
    {
        foreach ($this->tables() as $tbl) {
            if (! Schema::hasTable($tbl) || Schema::hasColumn($tbl, 'total_loss')) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) {
                $table->decimal('total_loss', 10, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as $tbl) {
            if (! Schema::hasTable($tbl) || ! Schema::hasColumn($tbl, 'total_loss')) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) {
                $table->dropColumn('total_loss');
            });
        }
    }
};
