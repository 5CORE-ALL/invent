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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('avatar_position_x')->default(50)->after('avatar');
            $table->unsignedTinyInteger('avatar_position_y')->default(50)->after('avatar_position_x');
            $table->unsignedSmallInteger('avatar_zoom')->default(100)->after('avatar_position_y');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_position_x', 'avatar_position_y', 'avatar_zoom']);
        });
    }
};
