<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * First overdue value saved on each day. Later snapshots of the same day overwrite `overdue`, but never this,
 * so the O-Due arrow can show a change inside the first day (before an earlier day exists to compare with).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_overdue_history') || Schema::hasColumn('task_overdue_history', 'opening_overdue')) {
            return;
        }

        Schema::table('task_overdue_history', function (Blueprint $table) {
            $table->unsignedInteger('opening_overdue')->nullable()->after('overdue');
        });

        DB::table('task_overdue_history')->whereNull('opening_overdue')->update(['opening_overdue' => DB::raw('overdue')]);
    }

    public function down(): void
    {
        if (Schema::hasTable('task_overdue_history') && Schema::hasColumn('task_overdue_history', 'opening_overdue')) {
            Schema::table('task_overdue_history', function (Blueprint $table) {
                $table->dropColumn('opening_overdue');
            });
        }
    }
};
