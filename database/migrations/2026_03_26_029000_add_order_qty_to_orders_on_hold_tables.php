<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['orders_on_hold_issues', 'orders_on_hold_issue_histories'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'order_qty')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'qty')) {
                    $table->decimal('order_qty', 12, 2)->nullable()->after('qty');
                } else {
                    $table->decimal('order_qty', 12, 2)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['orders_on_hold_issue_histories', 'orders_on_hold_issues'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'order_qty')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('order_qty');
            });
        }
    }
};
