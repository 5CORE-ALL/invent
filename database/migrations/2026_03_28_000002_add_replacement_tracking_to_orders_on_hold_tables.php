<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders_on_hold_issues') && !Schema::hasColumn('orders_on_hold_issues', 'replacement_tracking')) {
            Schema::table('orders_on_hold_issues', function (Blueprint $table) {
                $table->string('replacement_tracking', 50)->nullable()->after('action_1_remark');
            });
        }

        if (Schema::hasTable('orders_on_hold_issue_histories') && !Schema::hasColumn('orders_on_hold_issue_histories', 'replacement_tracking')) {
            Schema::table('orders_on_hold_issue_histories', function (Blueprint $table) {
                $table->string('replacement_tracking', 50)->nullable()->after('action_1_remark');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders_on_hold_issue_histories') && Schema::hasColumn('orders_on_hold_issue_histories', 'replacement_tracking')) {
            Schema::table('orders_on_hold_issue_histories', function (Blueprint $table) {
                $table->dropColumn('replacement_tracking');
            });
        }

        if (Schema::hasTable('orders_on_hold_issues') && Schema::hasColumn('orders_on_hold_issues', 'replacement_tracking')) {
            Schema::table('orders_on_hold_issues', function (Blueprint $table) {
                $table->dropColumn('replacement_tracking');
            });
        }
    }
};
