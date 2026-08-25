<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'listing_issue_issues',
        'label_issue_issues',
        'c_care_issue_issues',
        'other_issue_issues',
        'qc_and_packing_issues',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'source_dispatch_issue_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('source_dispatch_issue_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'source_dispatch_issue_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('source_dispatch_issue_id');
            });
        }
    }
};
