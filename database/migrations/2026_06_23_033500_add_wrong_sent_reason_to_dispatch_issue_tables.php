<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `wrong_sent_reason` to the dispatch issue tables. Powers the new
 * "Why it happened" dropdown inside the Wrong Item Sent sub-section of the
 * All Issues modal — built-in options (Picker error / Label swap / Mis-scan
 * / Look-alike SKU / Listing image mismatch / Other) plus custom user-added
 * options stored in `customer_care_issue_dropdown_options` under
 * `field_type = 'wrong_sent_reason'`.
 *
 * Mirrored on the history table so each revision captures the same shape.
 */
return new class extends Migration
{
    private array $tables = ['dispatch_issue_issues', 'dispatch_issue_issue_histories'];

    public function up(): void
    {
        foreach ($this->tables as $tbl) {
            if (! Schema::hasTable($tbl) || Schema::hasColumn($tbl, 'wrong_sent_reason')) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) {
                $table->string('wrong_sent_reason', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tbl) {
            if (! Schema::hasTable($tbl) || ! Schema::hasColumn($tbl, 'wrong_sent_reason')) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) {
                $table->dropColumn('wrong_sent_reason');
            });
        }
    }
};
