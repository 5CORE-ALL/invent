<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lqs_master_entries')) {
            return;
        }

        Schema::create('lqs_master_entries', function (Blueprint $table) {
            $table->id();
            $table->string('channel_key');
            $table->string('channel');
            $table->decimal('lqs', 4, 1);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('channel_key');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lqs_master_entries');
    }
};
