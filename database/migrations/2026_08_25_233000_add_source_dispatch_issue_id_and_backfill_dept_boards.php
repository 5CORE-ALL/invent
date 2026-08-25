<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ISSUE_TABLES = [
        'listing_issue_issues',
        'label_issue_issues',
        'qc_and_packing_issues',
        'c_care_issue_issues',
        'other_issue_issues',
    ];

    private const HISTORY_TABLES = [
        'listing_issue_issue_histories',
        'label_issue_issue_histories',
        'qc_and_packing_issue_histories',
        'c_care_issue_issue_histories',
        'other_issue_issue_histories',
    ];

    public function up(): void
    {
        foreach (array_merge(self::ISSUE_TABLES, self::HISTORY_TABLES) as $table) {
            $this->ensureAutoIncrementId($table);
        }

        foreach (self::ISSUE_TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'source_dispatch_issue_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('source_dispatch_issue_id')->nullable()->after('id');
                $blueprint->index('source_dispatch_issue_id');
            });
        }

        // Backfill is done on board page load (ensureForDepartments), not here —
        // this migration must stay fast and must not fail a deploy.
    }

    public function down(): void
    {
        foreach (self::ISSUE_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'source_dispatch_issue_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['source_dispatch_issue_id']);
                $blueprint->dropColumn('source_dispatch_issue_id');
            });
        }
    }

    private function ensureAutoIncrementId(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
            return;
        }

        $col = DB::selectOne(
            'SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, 'id']
        );
        $extra = strtolower((string) ($col->EXTRA ?? $col->extra ?? ''));
        if (str_contains($extra, 'auto_increment')) {
            return;
        }

        DB::statement('ALTER TABLE `'.$table.'` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }
};
