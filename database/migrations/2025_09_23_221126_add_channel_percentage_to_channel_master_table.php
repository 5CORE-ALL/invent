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
            $table->integer('channel_percentage')->nullable()->after('sheet_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channel_master', function (Blueprint $table) {
            $table->dropColumn('channel_percentage');
        });
    }
};
