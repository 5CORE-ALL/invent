<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['orders_on_hold_issues', 'orders_on_hold_issue_histories'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'issue_remark')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'issue')) {
                    $table->string('issue_remark')->nullable()->after('issue');
                } else {
                    $table->string('issue_remark')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['orders_on_hold_issue_histories', 'orders_on_hold_issues'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'issue_remark')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('issue_remark');
            });
        }
    }
};
