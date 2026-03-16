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
        Schema::create('setup_account_channel_master', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->string('channel_name')->nullable();
            $table->string('status')->nullable();
            $table->string('login_link')->nullable();
            $table->string('email_userid')->nullable();
            $table->string('password')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setup_account_channel_master');
    }
};
