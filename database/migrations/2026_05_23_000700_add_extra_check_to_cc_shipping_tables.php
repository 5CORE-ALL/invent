<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Shipping page's checklist has FOUR items instead of three:
 *   1. All cancellation done
 *   2. All Labels created
 *   3. All Labels Sent to Dispatch
 *   4. All labels purchased @ lowest price possible
 *
 * The first three reuse the existing positional columns
 * (messages_resolved, unresolved_messages_followup, activity_documented).
 * The fourth needs a new boolean column on both shipping checklist
 * tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['cc_shipping_checklists', 'cc_shipping_returns_checklists'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'extra_check')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('extra_check')->default(false)->after('activity_documented');
            });
        }
    }

    public function down(): void
    {
        foreach (['cc_shipping_checklists', 'cc_shipping_returns_checklists'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'extra_check')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('extra_check');
            });
        }
    }
};
