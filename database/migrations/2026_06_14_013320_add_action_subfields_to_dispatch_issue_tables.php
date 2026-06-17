<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the conditional sub-fields exposed by the All Issues "Action" picker:
 *
 *   - refund_type              ('partial' | 'full')             — when Action = Refund
 *   - replacement_sku          (varchar 128)                    — when Action = Replacement / Alternate Sent
 *   - replacement_qty_sending  (numeric)                        — quantity we are sending out
 *   - outgoing_needed          (bool)                           — needs an outgoing pickup ticket
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
                if (! Schema::hasColumn($tbl, 'refund_type')) {
                    $table->string('refund_type', 16)->nullable();
                }
                if (! Schema::hasColumn($tbl, 'replacement_sku')) {
                    $table->string('replacement_sku', 128)->nullable();
                }
                if (! Schema::hasColumn($tbl, 'replacement_qty_sending')) {
                    $table->double('replacement_qty_sending')->nullable();
                }
                if (! Schema::hasColumn($tbl, 'outgoing_needed')) {
                    $table->boolean('outgoing_needed')->default(false);
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
                foreach (['refund_type', 'replacement_sku', 'replacement_qty_sending', 'outgoing_needed'] as $col) {
                    if (Schema::hasColumn($tbl, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
