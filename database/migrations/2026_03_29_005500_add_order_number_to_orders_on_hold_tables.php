<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders_on_hold_issues') && !Schema::hasColumn('orders_on_hold_issues', 'order_number')) {
            Schema::table('orders_on_hold_issues', function (Blueprint $table) {
                $table->string('order_number', 25)->nullable()->after('order_qty');
            });
        }

        if (Schema::hasTable('orders_on_hold_issue_histories') && !Schema::hasColumn('orders_on_hold_issue_histories', 'order_number')) {
            Schema::table('orders_on_hold_issue_histories', function (Blueprint $table) {
                $table->string('order_number', 25)->nullable()->after('order_qty');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders_on_hold_issue_histories') && Schema::hasColumn('orders_on_hold_issue_histories', 'order_number')) {
            Schema::table('orders_on_hold_issue_histories', function (Blueprint $table) {
                $table->dropColumn('order_number');
            });
        }

        if (Schema::hasTable('orders_on_hold_issues') && Schema::hasColumn('orders_on_hold_issues', 'order_number')) {
            Schema::table('orders_on_hold_issues', function (Blueprint $table) {
                $table->dropColumn('order_number');
            });
        }
    }
};
