<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wires the All Issues modal's "Outgoing needed?" checkbox to the
 * /outgoing-view inventory pipeline. When an issue is saved with
 * outgoing_needed = true, the controller calls into OutgoingController
 * to adjust Shopify inventory and create an `inventories` row of
 * type='outgoing'. The columns below track that linkage and prevent
 * double-deduction on re-save.
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
                if (! Schema::hasColumn($tbl, 'outgoing_warehouse_id')) {
                    $table->unsignedBigInteger('outgoing_warehouse_id')->nullable();
                }
                if (! Schema::hasColumn($tbl, 'outgoing_processed_at')) {
                    $table->timestamp('outgoing_processed_at')->nullable();
                }
                if (! Schema::hasColumn($tbl, 'outgoing_inventory_id')) {
                    $table->unsignedBigInteger('outgoing_inventory_id')->nullable();
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
                foreach (['outgoing_warehouse_id', 'outgoing_processed_at', 'outgoing_inventory_id'] as $col) {
                    if (Schema::hasColumn($tbl, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
