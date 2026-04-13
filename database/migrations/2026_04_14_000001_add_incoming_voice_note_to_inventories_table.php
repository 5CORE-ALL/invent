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
        if (! Schema::hasTable('inventories') || Schema::hasColumn('inventories', 'incoming_voice_note')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table) {
            if (Schema::hasColumn('inventories', 'incoming_images')) {
                $table->string('incoming_voice_note', 512)->nullable()->after('incoming_images');
            } else {
                $table->string('incoming_voice_note', 512)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('inventories') || ! Schema::hasColumn('inventories', 'incoming_voice_note')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('incoming_voice_note');
        });
    }
};
