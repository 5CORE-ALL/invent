<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = Schema::hasTable('mfrg_progresses') ? 'mfrg_progresses' : 'mfrg_progress';

        if (!Schema::hasColumn($tableName, 'pkg_inst')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('pkg_inst', 10)->nullable()->default('No');
            });
        }
        if (!Schema::hasColumn($tableName, 'u_manual')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('u_manual', 10)->nullable()->default('No');
            });
        }
        if (!Schema::hasColumn($tableName, 'compliance')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('compliance', 10)->nullable()->default('No');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = Schema::hasTable('mfrg_progresses') ? 'mfrg_progresses' : 'mfrg_progress';
        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn(['pkg_inst', 'u_manual', 'compliance']);
        });
    }
};
