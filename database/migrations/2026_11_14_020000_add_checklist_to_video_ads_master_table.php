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
            // JSON array of the checklist checkpoint numbers (1-20) that are ticked.
            if (! Schema::hasColumn('video_ads_master', 'checklist')) {
                $table->json('checklist')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('video_ads_master')) {
            return;
        }

        Schema::table('video_ads_master', function (Blueprint $table) {
            if (Schema::hasColumn('video_ads_master', 'checklist')) {
                $table->dropColumn('checklist');
            }
        });
    }
};
