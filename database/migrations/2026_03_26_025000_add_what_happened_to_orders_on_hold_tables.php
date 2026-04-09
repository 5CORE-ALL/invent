<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['orders_on_hold_issues', 'orders_on_hold_issue_histories'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'what_happened')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'marketplace_2')) {
                    $table->string('what_happened', 50)->nullable()->after('marketplace_2');
                } else {
                    $table->string('what_happened', 50)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['orders_on_hold_issue_histories', 'orders_on_hold_issues'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'what_happened')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('what_happened');
            });
        }
    }
};
