<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'temu_lmp';

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'lmp_entries')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'lmp_link_2')) {
                $table->json('lmp_entries')->nullable()->after('lmp_link_2');
            } else {
                $table->json('lmp_entries')->nullable();
            }
        });
    }

    public function down(): void
    {
        $tableName = 'temu_lmp';

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'lmp_entries')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('lmp_entries');
        });
    }
};
