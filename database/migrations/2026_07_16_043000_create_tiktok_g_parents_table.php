<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tiktok_g_parents')) {
            return;
        }

        Schema::create('tiktok_g_parents', function (Blueprint $table) {
            $table->id();
            $table->string('parent');
            $table->string('g_parent');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique('parent', 'tiktok_g_parents_parent_unique');
            $table->index('g_parent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_g_parents');
    }
};
