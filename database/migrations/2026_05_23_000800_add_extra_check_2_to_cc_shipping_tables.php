<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping page checklist gained a fifth item: "All Split/Combo
 * Messages Sent". Add an `extra_check_2` boolean to both shipping
 * checklist tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['cc_shipping_checklists', 'cc_shipping_returns_checklists'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'extra_check_2')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('extra_check_2')->default(false)->after('extra_check');
            });
        }
    }

    public function down(): void
    {
        foreach (['cc_shipping_checklists', 'cc_shipping_returns_checklists'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'extra_check_2')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('extra_check_2');
            });
        }
    }
};
