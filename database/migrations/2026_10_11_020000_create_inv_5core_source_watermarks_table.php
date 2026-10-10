<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inv_5core_source_watermarks')) {
            return;
        }

        Schema::create('inv_5core_source_watermarks', function (Blueprint $table) {
            $table->id();
            $table->string('source', 64)->unique();
            $table->unsignedBigInteger('watermark_id')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_5core_source_watermarks');
    }
};
