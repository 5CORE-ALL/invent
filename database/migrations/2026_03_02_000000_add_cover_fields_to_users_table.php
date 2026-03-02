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
            $table->string('cover_image')->nullable()->after('avatar');
            $table->unsignedTinyInteger('cover_position_x')->default(50)->after('cover_image'); // 0-100, percent
            $table->unsignedTinyInteger('cover_position_y')->default(50)->after('cover_position_x');
            $table->unsignedSmallInteger('cover_zoom')->default(100)->after('cover_position_y'); // 50-200 percent
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cover_image', 'cover_position_x', 'cover_position_y', 'cover_zoom']);
        });
    }
};
