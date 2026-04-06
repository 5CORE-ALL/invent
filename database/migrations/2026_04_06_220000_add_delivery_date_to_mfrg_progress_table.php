<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = Schema::hasTable('mfrg_progresses') ? 'mfrg_progresses' : 'mfrg_progress';

        if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'delivery_date')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->date('delivery_date')->nullable()->after('del_date');
        });
    }

    public function down(): void
    {
        $tableName = Schema::hasTable('mfrg_progresses') ? 'mfrg_progresses' : 'mfrg_progress';

        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'delivery_date')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('delivery_date');
        });
    }
};
