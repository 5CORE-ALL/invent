<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('marketplace_push_logs')) {
            return;
        }

        Schema::create('marketplace_push_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 255)->index();
            $table->string('marketplace', 32); // amazon, temu, reverb, wayfair
            $table->string('status', 32); // success, failed, pending
            $table->text('error_message')->nullable();
            $table->json('response_data')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_push_logs');
    }
};
