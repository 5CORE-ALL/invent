<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcement_comment_reactions')) {
            return;
        }

        Schema::create('announcement_comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('announcement_comments')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('emoji', 32);
            $table->timestamps();

            $table->unique(['comment_id', 'user_id', 'emoji']);
            $table->index('comment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_comment_reactions');
    }
};
