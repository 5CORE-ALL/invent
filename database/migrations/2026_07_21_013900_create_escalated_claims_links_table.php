<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('escalated_claims_links')) {
            return;
        }

        Schema::create('escalated_claims_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->unique();
            $table->string('link', 2048)->nullable();
            $table->timestamps();

            $table->foreign('channel_id')
                ->references('id')
                ->on('channel_master')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalated_claims_links');
    }
};
