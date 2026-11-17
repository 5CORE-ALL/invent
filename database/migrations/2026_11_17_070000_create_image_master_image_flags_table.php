<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('image_master_image_flags')) {
            return;
        }

        // One row per flagged image (red triangle in the Image Master editor). Keyed by the image URL so the
        // flag follows the image when it is re-ordered, and disappears when the image is replaced.
        Schema::create('image_master_image_flags', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 255)->index();
            $table->string('url_hash', 40);
            $table->text('url');
            $table->string('error_type', 120)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 255)->nullable();
            $table->timestamps();
            $table->unique(['sku', 'url_hash'], 'im_flags_sku_url_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_master_image_flags');
    }
};
