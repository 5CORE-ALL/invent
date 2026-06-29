<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_videos')) {
            Schema::create('product_videos', function (Blueprint $table) {
                $table->id();
                $table->string('sku', 255)->index();
                $table->string('video_path', 500);
                $table->text('cdn_url')->nullable();
                $table->string('cdn_file_id', 255)->nullable();
                $table->string('original_name', 255)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('product_master')) {
            return;
        }

        // TEXT columns (like image7–image12) — off-page storage, avoids MySQL 65535 row-size limit.
        for ($i = 1; $i <= 10; $i++) {
            $col = 'video'.$i;
            if (! Schema::hasColumn('product_master', $col)) {
                Schema::table('product_master', function (Blueprint $table) use ($col) {
                    $table->text($col)->nullable();
                });
            }
        }

        if (! Schema::hasColumn('product_master', 'main_video')) {
            Schema::table('product_master', function (Blueprint $table) {
                $table->text('main_video')->nullable();
            });
        }

        if (! Schema::hasColumn('product_master', 'video_main_by_marketplace_json')) {
            Schema::table('product_master', function (Blueprint $table) {
                $table->longText('video_main_by_marketplace_json')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_videos');

        if (! Schema::hasTable('product_master')) {
            return;
        }

        for ($i = 1; $i <= 10; $i++) {
            $col = 'video'.$i;
            if (Schema::hasColumn('product_master', $col)) {
                Schema::table('product_master', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }

        if (Schema::hasColumn('product_master', 'main_video')) {
            Schema::table('product_master', function (Blueprint $table) {
                $table->dropColumn('main_video');
            });
        }

        if (Schema::hasColumn('product_master', 'video_main_by_marketplace_json')) {
            Schema::table('product_master', function (Blueprint $table) {
                $table->dropColumn('video_main_by_marketplace_json');
            });
        }
    }
};
