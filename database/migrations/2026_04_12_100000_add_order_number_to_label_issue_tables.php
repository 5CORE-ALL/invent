<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Issue boards except dispatch (which already has order_number). */
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
        ];
    }

    public function up(): void
    {
        foreach ($this->tables() as $tbl) {
            if (! Schema::hasTable($tbl) || Schema::hasColumn($tbl, 'order_number')) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) {
                $table->string('order_number')->nullable()->after('sku');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as $tbl) {
            if (! Schema::hasTable($tbl) || ! Schema::hasColumn($tbl, 'order_number')) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) {
                $table->dropColumn('order_number');
            });
        }
    }
};
