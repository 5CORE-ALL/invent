<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['dispatch_issue_issues', 'dispatch_issue_issue_histories'] as $tbl) {
            if (! Schema::hasTable($tbl) || Schema::hasColumn($tbl, 'issue_link')) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) {
                $table->string('issue_link', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['dispatch_issue_issues', 'dispatch_issue_issue_histories'] as $tbl) {
            if (! Schema::hasTable($tbl) || ! Schema::hasColumn($tbl, 'issue_link')) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) {
                $table->dropColumn('issue_link');
            });
        }
    }
};
