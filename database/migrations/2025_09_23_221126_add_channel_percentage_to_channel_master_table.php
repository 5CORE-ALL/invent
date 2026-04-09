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
        if (! Schema::hasTable('channel_master') || Schema::hasColumn('channel_master', 'channel_percentage')) {
            return;
        }

        $afterSheetLink = Schema::hasColumn('channel_master', 'sheet_link');

        Schema::table('channel_master', function (Blueprint $table) use ($afterSheetLink): void {
            if ($afterSheetLink) {
                $table->integer('channel_percentage')->nullable()->after('sheet_link');
            } else {
                $table->integer('channel_percentage')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('channel_master') || ! Schema::hasColumn('channel_master', 'channel_percentage')) {
            return;
        }

        Schema::table('channel_master', function (Blueprint $table) {
            $table->dropColumn('channel_percentage');
        });
    }
};
