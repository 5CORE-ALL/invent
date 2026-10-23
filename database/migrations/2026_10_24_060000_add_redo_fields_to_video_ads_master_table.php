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
            if (! Schema::hasColumn('video_ads_master', 'redo_checked')) {
                $table->boolean('redo_checked')->default(false);
                $table->string('redo_checked_by')->nullable();
                $table->timestamp('redo_checked_at')->nullable();
            }
            if (! Schema::hasColumn('video_ads_master', 'redone_checked')) {
                $table->boolean('redone_checked')->default(false);
                $table->string('redone_checked_by')->nullable();
                $table->timestamp('redone_checked_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('video_ads_master')) {
            return;
        }

        Schema::table('video_ads_master', function (Blueprint $table) {
            foreach (['redo_checked', 'redo_checked_by', 'redo_checked_at', 'redone_checked', 'redone_checked_by', 'redone_checked_at'] as $col) {
                if (Schema::hasColumn('video_ads_master', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
