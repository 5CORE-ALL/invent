<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'temu_orders' => 'temu_orders_parent_tracking_idx',
            'temu2_orders' => 'temu2_orders_parent_tracking_idx',
        ] as $tableName => $indexName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'tracking_number')) {
                    $table->string('tracking_number', 128)->nullable()->after('order_shipping_time');
                }
                if (! Schema::hasColumn($tableName, 'carrier')) {
                    $table->string('carrier', 128)->nullable()->after('tracking_number');
                }
                if (! Schema::hasColumn($tableName, 'package_sn')) {
                    $table->string('package_sn', 128)->nullable()->after('carrier');
                }
                if (! Schema::hasColumn($tableName, 'tracking_fetched_at')) {
                    $table->timestamp('tracking_fetched_at')->nullable()->after('package_sn');
                }
            });

            try {
                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->index(['parent_order_sn', 'tracking_number'], $indexName);
                });
            } catch (\Throwable) {
                // Index already exists.
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'temu_orders' => 'temu_orders_parent_tracking_idx',
            'temu2_orders' => 'temu2_orders_parent_tracking_idx',
        ] as $tableName => $indexName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            try {
                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            } catch (\Throwable) {
                // ignore
            }

            $cols = [];
            foreach (['tracking_fetched_at', 'package_sn', 'carrier', 'tracking_number'] as $col) {
                if (Schema::hasColumn($tableName, $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($cols) {
                    $table->dropColumn($cols);
                });
            }
        }
    }
};
