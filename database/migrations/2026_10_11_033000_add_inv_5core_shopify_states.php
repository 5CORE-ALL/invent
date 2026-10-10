<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inv_5core_balances')) {
            Schema::table('inv_5core_balances', function (Blueprint $table) {
                if (! Schema::hasColumn('inv_5core_balances', 'qty_committed')) {
                    $table->decimal('qty_committed', 12, 2)->default(0)->after('qty_on_hand');
                }
                if (! Schema::hasColumn('inv_5core_balances', 'qty_unavailable')) {
                    $table->decimal('qty_unavailable', 12, 2)->default(0)->after('qty_committed');
                }
            });
        }

        if (Schema::hasTable('inv_5core_transactions')) {
            Schema::table('inv_5core_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('inv_5core_transactions', 'unavailable_delta')) {
                    $table->decimal('unavailable_delta', 12, 2)->nullable()->after('qty_after');
                }
                if (! Schema::hasColumn('inv_5core_transactions', 'unavailable_after')) {
                    $table->decimal('unavailable_after', 12, 2)->nullable()->after('unavailable_delta');
                }
                if (! Schema::hasColumn('inv_5core_transactions', 'committed_delta')) {
                    $table->decimal('committed_delta', 12, 2)->nullable()->after('unavailable_after');
                }
                if (! Schema::hasColumn('inv_5core_transactions', 'committed_after')) {
                    $table->decimal('committed_after', 12, 2)->nullable()->after('committed_delta');
                }
                if (! Schema::hasColumn('inv_5core_transactions', 'available_delta')) {
                    $table->decimal('available_delta', 12, 2)->nullable()->after('committed_after');
                }
                if (! Schema::hasColumn('inv_5core_transactions', 'available_after')) {
                    $table->decimal('available_after', 12, 2)->nullable()->after('available_delta');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inv_5core_transactions')) {
            Schema::table('inv_5core_transactions', function (Blueprint $table) {
                foreach (['available_after', 'available_delta', 'committed_after', 'committed_delta', 'unavailable_after', 'unavailable_delta'] as $column) {
                    if (Schema::hasColumn('inv_5core_transactions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('inv_5core_balances')) {
            Schema::table('inv_5core_balances', function (Blueprint $table) {
                foreach (['qty_unavailable', 'qty_committed'] as $column) {
                    if (Schema::hasColumn('inv_5core_balances', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
