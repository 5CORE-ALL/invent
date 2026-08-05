<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-text note when Responsible Dept includes "Other" (special cases that
 * don't fit a named department). Mirrored on history for revision capture.
 */
return new class extends Migration
{
    private array $tables = ['dispatch_issue_issues', 'dispatch_issue_issue_histories'];

    public function up(): void
    {
        foreach ($this->tables as $tbl) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (! Schema::hasColumn($tbl, 'department_other_note')) {
                    $table->string('department_other_note', 255)->nullable()->after('department');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tbl) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (Schema::hasColumn($tbl, 'department_other_note')) {
                    $table->dropColumn('department_other_note');
                }
            });
        }
    }
};
