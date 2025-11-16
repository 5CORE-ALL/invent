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
        Schema::table('channel_master', function (Blueprint $table) {

            $table->boolean('nr')->default(0)->after('sheet_link');
            $table->boolean('w_ads')->default(0)->after('nr');
            $table->boolean('update')->default(0)->after('w_ads');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channel_master', function (Blueprint $table) {
            $table->dropColumn(['nr', 'w_ads', 'update_flag']);
        });
    }
};
