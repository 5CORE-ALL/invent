<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('channel_master', function (Blueprint $table) {
            $table->id();
            $table->string('channel');
            $table->enum('status', ['Active', 'Inactive', 'To Onboard', 'In Progress'])->default('Active');
            $table->string('executive')->nullable();
            $table->string('b_link')->nullable();
            $table->string('s_link')->nullable();
            $table->string('user_id')->nullable();
            $table->string('action_req')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bd_channel_master');
    }
};
