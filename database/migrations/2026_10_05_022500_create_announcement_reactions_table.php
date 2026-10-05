<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcement_reactions')) {
            return;
        }

        Schema::create('announcement_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('emoji', 32);
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id', 'emoji']);
            $table->index('announcement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reactions');
    }
};
