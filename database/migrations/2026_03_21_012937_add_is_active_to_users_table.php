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
        $tableName = 'users';

        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'is_active')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('is_active')->default(true);
            });
        }

        if (! Schema::hasColumn($tableName, 'deactivated_at')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('deactivated_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'users';

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn($tableName, 'is_active') ? 'is_active' : null,
            Schema::hasColumn($tableName, 'deactivated_at') ? 'deactivated_at' : null,
        ]));

        if ($columns !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
