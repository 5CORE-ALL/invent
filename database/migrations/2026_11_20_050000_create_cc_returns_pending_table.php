<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cc_returns_pending')) {
            return;
        }

        Schema::create('cc_returns_pending', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->unique();
            $table->unsignedInteger('pending_count')->default(0);
            $table->string('fetch_status', 32)->nullable();
            $table->string('fetch_note', 512)->nullable();
            $table->string('source', 64)->nullable();
            $table->timestamp('last_fetched_at')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->string('updated_by_name', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cc_returns_pending');
    }
};
