<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos_for_ads', function (Blueprint $table) {
            $table->text('video_thumbnail')->nullable()->after('category');
            $table->text('video_url')->nullable()->after('video_thumbnail');
        });
    }

    public function down(): void
    {
        Schema::table('videos_for_ads', function (Blueprint $table) {
            $table->dropColumn(['video_thumbnail', 'video_url']);
        });
    }
};
