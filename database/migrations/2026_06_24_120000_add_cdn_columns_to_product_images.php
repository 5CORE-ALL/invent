<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_images')) {
            return;
        }
        Schema::table('product_images', function (Blueprint $table) {
            if (! Schema::hasColumn('product_images', 'cdn_url')) {
                $table->string('cdn_url', 1024)->nullable()->after('image_path');
            }
            if (! Schema::hasColumn('product_images', 'cdn_file_id')) {
                $table->string('cdn_file_id')->nullable()->after('cdn_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_images')) {
            return;
        }
        Schema::table('product_images', function (Blueprint $table) {
            foreach (['cdn_url', 'cdn_file_id'] as $col) {
                if (Schema::hasColumn('product_images', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
