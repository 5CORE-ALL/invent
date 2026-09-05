<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra Replacement / Alternate Sent SKU rows from the All Issues modal.
 * The first replacement SKU stays on replacement_sku / replacement_qty_sending
 * / outgoing_*. Additional lines (SKU, qty, tracking, outgoing) live here as
 * JSON so each extra SKU can have its own "Outgoing needed?" checkbox.
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
                if (! Schema::hasColumn($tbl, 'replacement_extra_lines')) {
                    $table->json('replacement_extra_lines')->nullable();
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
                if (Schema::hasColumn($tbl, 'replacement_extra_lines')) {
                    $table->dropColumn('replacement_extra_lines');
                }
            });
        }
    }
};
