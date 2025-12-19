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
            $table->decimal('base', 10, 2)->nullable()->default(0)->after('channel_percentage');
            $table->decimal('target', 10, 2)->nullable()->default(0)->after('base');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channel_master', function (Blueprint $table) {
            $table->dropColumn(['base', 'target']);
        });
    }
};
