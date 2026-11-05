<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ration_video_uploads')) {
            return;
        }

        Schema::create('ration_video_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('ratio', 10); // nine (9:16) | sixteen (16:9)
            $table->string('sku', 191)->index();
            $table->string('original_name', 255);
            $table->string('path', 500);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime', 100)->nullable();
            $table->string('uploaded_by', 191)->nullable();
            $table->timestamps();

            $table->index(['ratio', 'created_at'], 'rvu_ratio_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ration_video_uploads');
    }
};
