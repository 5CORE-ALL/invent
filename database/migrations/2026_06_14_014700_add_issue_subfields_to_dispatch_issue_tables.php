<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the conditional sub-fields driven by the All Issues "Issue?" picker
 * (column `what_happened` in the issues / history tables):
 *
 *   - wrong_sent_sku       (varchar 128)        — when Issue? = Wrong Item Sent
 *   - issue_notes          (varchar 255, ≤200)  — when Issue? = Wrong Item Sent
 *   - qty_mismatch_type    ('less' | 'more')    — when Issue? = Wrong Quantity Sent
 *   - qty_sent             (double)             — when Issue? = Wrong Quantity Sent
 *   - qty_ordered          (double)             — when Issue? = Wrong Quantity Sent
 *
 * Mirrored on the history table so each revision captures the same shape.
 */
return new class extends Migration
{
    private array $tables = ['dispatch_issue_issues', 'dispatch_issue_issue_histories'];

    public function up(): void
    {
        foreach ($this->tables as $tbl) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (! Schema::hasColumn($tbl, 'wrong_sent_sku')) {
                    $table->string('wrong_sent_sku', 128)->nullable();
                }
                if (! Schema::hasColumn($tbl, 'issue_notes')) {
                    $table->string('issue_notes', 255)->nullable();
                }
                if (! Schema::hasColumn($tbl, 'qty_mismatch_type')) {
                    $table->string('qty_mismatch_type', 8)->nullable();
                }
                if (! Schema::hasColumn($tbl, 'qty_sent')) {
                    $table->double('qty_sent')->nullable();
                }
                if (! Schema::hasColumn($tbl, 'qty_ordered')) {
                    $table->double('qty_ordered')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tbl) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                foreach (['wrong_sent_sku', 'issue_notes', 'qty_mismatch_type', 'qty_sent', 'qty_ordered'] as $col) {
                    if (Schema::hasColumn($tbl, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
