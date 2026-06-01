<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['dispatch_issue_issues', 'dispatch_issue_issue_histories'] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (!Schema::hasColumn($tbl, 'department')) {
                    $table->string('department', 100)->nullable()->after('c_action_1_remark');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['dispatch_issue_issues', 'dispatch_issue_issue_histories'] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (Schema::hasColumn($tbl, 'department')) {
                    $table->dropColumn('department');
                }
            });
        }
    }
};
