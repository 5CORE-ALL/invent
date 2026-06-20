<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `amt_rec` (Amount Received — claim payout amount, USD, free-text up
 * to 6 chars to mirror `amp_usd`). Used by the Carrier & Claim board's
 * "Amt Rec" column, sitting right after the existing "AMT $" input.
 *
 * Mirrors 2026_04_16_150000_add_amp_usd_to_customer_care_issue_tables — same
 * shape and the same six tables — so the new column is queryable across
 * dispatch / carrier / label issue boards and their *_histories.
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
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'amt_rec')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('amt_rec', 6)->nullable()->after('amp_usd');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'amt_rec')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('amt_rec');
            });
        }
    }
};
