<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_ads_master', function (Blueprint $table) {
            $table->id();
            $table->enum('target_type', ['sku', 'parent', 'group'])->nullable();
            $table->string('target_value')->nullable();
            $table->string('name')->nullable();
            $table->string('channel')->nullable();
            $table->text('audience')->nullable();
            $table->string('hook_name')->nullable();
            $table->text('hook')->nullable();
            $table->text('link')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_value']);
            $table->index('channel');
            $table->index('hook_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_ads_master');
    }
};
