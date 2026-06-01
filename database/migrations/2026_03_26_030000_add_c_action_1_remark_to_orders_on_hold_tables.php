<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['orders_on_hold_issues', 'orders_on_hold_issue_histories'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'c_action_1_remark')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'c_action_1')) {
                    $table->string('c_action_1_remark')->nullable()->after('c_action_1');
                } else {
                    $table->string('c_action_1_remark')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['orders_on_hold_issue_histories', 'orders_on_hold_issues'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'c_action_1_remark')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('c_action_1_remark');
            });
        }
    }
};
