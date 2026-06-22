<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wires the All Issues modal's Wrong Item Sent sub-section to the
 * /outgoing-view inventory pipeline (mirrors the existing Replacement
 * outgoing flow). Adds:
 *
 *   - wrong_sent_qty                    (double, nullable) — qty wrongly shipped
 *   - wrong_sent_outgoing_needed        (bool, default 0)  — checkbox in modal
 *   - wrong_sent_outgoing_warehouse_id  (unsigned bigint)  — warehouse to deduct from
 *   - wrong_sent_outgoing_processed_at  (timestamp)        — set when Shopify deduction succeeds
 *   - wrong_sent_outgoing_inventory_id  (unsigned bigint)  — `inventories` row created by Outgoing pipeline
 *
 * The existing `outgoing_*` columns remain dedicated to the Replacement
 * action sub-section so both flows can fire independently on the same
 * issue (replacement SKU outgoing AND the wrongly-sent SKU outgoing).
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
                if (! Schema::hasColumn($tbl, 'wrong_sent_qty')) {
                    $table->double('wrong_sent_qty')->nullable();
                }
                if (! Schema::hasColumn($tbl, 'wrong_sent_outgoing_needed')) {
                    $table->boolean('wrong_sent_outgoing_needed')->default(false);
                }
                if (! Schema::hasColumn($tbl, 'wrong_sent_outgoing_warehouse_id')) {
                    $table->unsignedBigInteger('wrong_sent_outgoing_warehouse_id')->nullable();
                }
                if (! Schema::hasColumn($tbl, 'wrong_sent_outgoing_processed_at')) {
                    $table->timestamp('wrong_sent_outgoing_processed_at')->nullable();
                }
                if (! Schema::hasColumn($tbl, 'wrong_sent_outgoing_inventory_id')) {
                    $table->unsignedBigInteger('wrong_sent_outgoing_inventory_id')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        $cols = [
            'wrong_sent_qty',
            'wrong_sent_outgoing_needed',
            'wrong_sent_outgoing_warehouse_id',
            'wrong_sent_outgoing_processed_at',
            'wrong_sent_outgoing_inventory_id',
        ];
        foreach ($this->tables as $tbl) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) use ($tbl, $cols) {
                foreach ($cols as $col) {
                    if (Schema::hasColumn($tbl, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
