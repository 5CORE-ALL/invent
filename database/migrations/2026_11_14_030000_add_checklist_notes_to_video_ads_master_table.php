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
            // JSON object: checkpoint number (1-20) => free-text note.
            if (! Schema::hasColumn('video_ads_master', 'checklist_notes')) {
                $table->json('checklist_notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('video_ads_master')) {
            return;
        }

        Schema::table('video_ads_master', function (Blueprint $table) {
            if (Schema::hasColumn('video_ads_master', 'checklist_notes')) {
                $table->dropColumn('checklist_notes');
            }
        });
    }
};
