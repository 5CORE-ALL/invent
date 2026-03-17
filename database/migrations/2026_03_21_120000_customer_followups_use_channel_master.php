<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Follow-ups originally referenced active_channels; production uses channel_master
 * (same source as /all-marketplace-master). Migrate existing tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_followups') || !Schema::hasTable('channel_master')) {
            return;
        }

        if (Schema::hasColumn('customer_followups', 'channel_master_id')) {
            return;
        }

        if (Schema::hasColumn('customer_followups', 'active_channel_id')) {
            try {
                Schema::table('customer_followups', function (Blueprint $table) {
                    $table->dropForeign(['active_channel_id']);
                });
            } catch (\Throwable $e) {
                $db = Schema::getConnection()->getDatabaseName();
                $row = DB::selectOne(
                    'SELECT CONSTRAINT_NAME AS n FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
                    [$db, 'customer_followups', 'active_channel_id']
                );
                if ($row && !empty($row->n)) {
                    DB::statement('ALTER TABLE `customer_followups` DROP FOREIGN KEY `' . $row->n . '`');
                }
            }

            Schema::table('customer_followups', function (Blueprint $table) {
                $table->dropColumn('active_channel_id');
            });
        }

        Schema::table('customer_followups', function (Blueprint $table) {
            $table->foreignId('channel_master_id')
                ->nullable()
                ->after('order_id')
                ->constrained('channel_master')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customer_followups')) {
            return;
        }

        if (!Schema::hasColumn('customer_followups', 'channel_master_id')) {
            return;
        }

        Schema::table('customer_followups', function (Blueprint $table) {
            $table->dropForeign(['channel_master_id']);
        });

        Schema::table('customer_followups', function (Blueprint $table) {
            $table->dropColumn('channel_master_id');
        });

        if (Schema::hasTable('active_channels')) {
            Schema::table('customer_followups', function (Blueprint $table) {
                $table->foreignId('active_channel_id')
                    ->nullable()
                    ->after('order_id')
                    ->constrained('active_channels')
                    ->nullOnDelete();
            });
        }
    }
};
