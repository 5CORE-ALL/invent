<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cc_returns_channel_next')) {
            Schema::create('cc_returns_channel_next', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id')->unique();   // one value per channel
                $table->unsignedTinyInteger('next_value')->nullable(); // 1..9
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->string('updated_by_name', 191)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cc_returns_channel_next');
    }
};
