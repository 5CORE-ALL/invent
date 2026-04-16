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
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'claim_filed')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->boolean('claim_filed')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'claim_filed')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('claim_filed');
            });
        }
    }
};
