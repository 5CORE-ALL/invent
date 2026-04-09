<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ready_to_ship', function (Blueprint $table) {
            $table->string('zone_x', 128)->nullable()->after('area');
        });
    }

    public function down(): void
    {
        Schema::table('ready_to_ship', function (Blueprint $table) {
            $table->dropColumn('zone_x');
        });
    }
};
