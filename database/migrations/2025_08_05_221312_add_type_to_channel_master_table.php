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
        if (! Schema::hasTable('channel_master') || Schema::hasColumn('channel_master', 'type')) {
            return;
        }

        Schema::table('channel_master', function (Blueprint $table) {
            $table->string('type')->nullable()->after('channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('channel_master') || ! Schema::hasColumn('channel_master', 'type')) {
            return;
        }

        Schema::table('channel_master', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
