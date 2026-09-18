<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'dispatch_issue_issues',
        'dispatch_issue_issue_histories',
        'carrier_issue_issues',
        'carrier_issue_issue_histories',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'claim_filed_at')) {
                    $blueprint->timestamp('claim_filed_at')->nullable();
                }
                if (! Schema::hasColumn($table, 'claim_received_at')) {
                    $blueprint->timestamp('claim_received_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'claim_filed_at')) {
                    $blueprint->dropColumn('claim_filed_at');
                }
                if (Schema::hasColumn($table, 'claim_received_at')) {
                    $blueprint->dropColumn('claim_received_at');
                }
            });
        }
    }
};
