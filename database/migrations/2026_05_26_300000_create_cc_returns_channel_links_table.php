<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cc_returns_channel_links')) {
            Schema::create('cc_returns_channel_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id')->unique();
                $table->string('r_link', 2048)->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->string('updated_by_name', 191)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cc_returns_channel_links');
    }
};
