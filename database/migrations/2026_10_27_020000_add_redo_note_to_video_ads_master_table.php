<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('video_ads_master')) {
            return;
        }

        Schema::table('video_ads_master', function (Blueprint $table) {
            if (! Schema::hasColumn('video_ads_master', 'redo_note')) {
                $table->text('redo_note')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('video_ads_master')) {
            return;
        }

        Schema::table('video_ads_master', function (Blueprint $table) {
            if (Schema::hasColumn('video_ads_master', 'redo_note')) {
                $table->dropColumn('redo_note');
            }
        });
    }
};
