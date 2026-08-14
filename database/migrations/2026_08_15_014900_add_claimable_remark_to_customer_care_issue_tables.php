<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `claimable_remark` — free-text reason a Carrier Claims row is
 * not claimable (50 words max, enforced in the UI / PATCH endpoint).
 * Same six tables as the other claim columns so the note is available
 * on dispatch / carrier / label boards and their history snapshots.
 */
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
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'claimable_remark')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $column = $blueprint->string('claimable_remark', 600)->nullable();
                if (Schema::hasColumn($table, 'claimable')) {
                    $column->after('claimable');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'claimable_remark')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('claimable_remark');
            });
        }
    }
};
