<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_message_archives')) {
            return;
        }

        Schema::create('chat_message_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('message_id');
            $table->timestamps();
            $table->unique(['user_id', 'message_id']);
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_archives');
    }
};
