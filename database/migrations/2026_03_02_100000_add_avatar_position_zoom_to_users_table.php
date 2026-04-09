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

        if (! Schema::hasColumn($tableName, 'avatar_position_x')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'avatar')) {
                    $table->unsignedTinyInteger('avatar_position_x')->default(50)->after('avatar');
                } else {
                    $table->unsignedTinyInteger('avatar_position_x')->default(50);
                }
            });
        }

        if (! Schema::hasColumn($tableName, 'avatar_position_y')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'avatar_position_x')) {
                    $table->unsignedTinyInteger('avatar_position_y')->default(50)->after('avatar_position_x');
                } else {
                    $table->unsignedTinyInteger('avatar_position_y')->default(50);
                }
            });
        }

        if (! Schema::hasColumn($tableName, 'avatar_zoom')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'avatar_position_y')) {
                    $table->unsignedSmallInteger('avatar_zoom')->default(100)->after('avatar_position_y');
                } else {
                    $table->unsignedSmallInteger('avatar_zoom')->default(100);
                }
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
            Schema::hasColumn($tableName, 'avatar_position_x') ? 'avatar_position_x' : null,
            Schema::hasColumn($tableName, 'avatar_position_y') ? 'avatar_position_y' : null,
            Schema::hasColumn($tableName, 'avatar_zoom') ? 'avatar_zoom' : null,
        ]));

        if ($columns !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
