<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcements')) {
            return;
        }

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('message')->nullable();
            $table->json('images')->nullable();
            $table->date('announced_on');
            $table->timestamp('posted_at')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['announced_on', 'id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
