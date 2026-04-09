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

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'designation')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'role')) {
                $table->string('designation')->nullable()->after('role');
            } else {
                $table->string('designation')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'users';

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'designation')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('designation');
        });
    }
};
