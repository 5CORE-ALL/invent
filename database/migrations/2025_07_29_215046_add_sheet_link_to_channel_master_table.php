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
        if (! Schema::hasTable('channel_master')) {
            return;
        }
        if (Schema::hasColumn('channel_master', 'sheet_link')) {
            return;
        }

        Schema::table('channel_master', function (Blueprint $table) {
            $table->string('sheet_link')->nullable()->after('channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }
        if (! Schema::hasColumn('channel_master', 'sheet_link')) {
            return;
        }

        Schema::table('channel_master', function (Blueprint $table) {
            $table->dropColumn('sheet_link');
        });
    }
};
