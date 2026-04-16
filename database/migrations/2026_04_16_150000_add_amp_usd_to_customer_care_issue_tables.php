<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'carrier_issue_issues',
        'label_issue_issues',
        'dispatch_issue_issues',
        'carrier_issue_issue_histories',
        'label_issue_issue_histories',
        'dispatch_issue_issue_histories',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'amp_usd')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('amp_usd', 6)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'amp_usd')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('amp_usd');
            });
        }
    }
};
