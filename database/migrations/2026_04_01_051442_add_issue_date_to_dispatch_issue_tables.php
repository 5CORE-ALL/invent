<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'dispatch_issue_issues',
            'dispatch_issue_issue_histories',
            'carrier_issue_issues',
            'carrier_issue_issue_histories',
            'label_issue_issues',
            'label_issue_issue_histories',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'issue_date')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->string('issue_date', 100)->nullable()->after('sku');
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'dispatch_issue_issues',
            'dispatch_issue_issue_histories',
            'carrier_issue_issues',
            'carrier_issue_issue_histories',
            'label_issue_issues',
            'label_issue_issue_histories',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'issue_date')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('issue_date');
                });
            }
        }
    }
};
