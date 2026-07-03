<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product Master metrics persistence for Newegg, TopDawg, and TikTok Shop.
     * Without these tables Bullet Point Master UI tiles were visible but push was blocked.
     */
    public function up(): void
    {
        foreach (['newegg_metrics', 'topdawg_metrics', 'tiktok_metrics'] as $tableName) {
            if (Schema::hasTable($tableName)) {
                $this->ensureMasterColumns($tableName);

                continue;
            }

            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('sku')->nullable()->index();
                $table->string('product_id')->nullable();
                $table->string('title')->nullable();
                $table->text('bullet_points')->nullable();
                $table->text('description_master')->nullable();
                $table->longText('image_urls')->nullable();
                $table->longText('image_master_json')->nullable();
                $table->longText('video_master_json')->nullable();
                $table->timestamps();
            });
        }
    }

    private function ensureMasterColumns(string $tableName): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'sku')) {
                $table->string('sku')->nullable()->index();
            }
            if (! Schema::hasColumn($tableName, 'product_id')) {
                $table->string('product_id')->nullable();
            }
            if (! Schema::hasColumn($tableName, 'title')) {
                $table->string('title')->nullable();
            }
            if (! Schema::hasColumn($tableName, 'bullet_points')) {
                $table->text('bullet_points')->nullable();
            }
            if (! Schema::hasColumn($tableName, 'description_master')) {
                $table->text('description_master')->nullable();
            }
            if (! Schema::hasColumn($tableName, 'image_urls')) {
                $table->longText('image_urls')->nullable();
            }
            if (! Schema::hasColumn($tableName, 'image_master_json')) {
                $table->longText('image_master_json')->nullable();
            }
            if (! Schema::hasColumn($tableName, 'video_master_json')) {
                $table->longText('video_master_json')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newegg_metrics');
        Schema::dropIfExists('topdawg_metrics');
        Schema::dropIfExists('tiktok_metrics');
    }
};
