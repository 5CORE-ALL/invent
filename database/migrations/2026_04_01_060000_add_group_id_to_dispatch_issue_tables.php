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
                if (!Schema::hasColumn($tbl, 'group_id')) {
                    $table->string('group_id', 36)->nullable()->after('id')->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['dispatch_issue_issues', 'dispatch_issue_issue_histories'] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (Schema::hasColumn($tbl, 'group_id')) {
                    $table->dropIndex(['group_id']);
                    $table->dropColumn('group_id');
                }
            });
        }
    }
};
