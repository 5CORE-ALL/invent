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
        Schema::create('approvals_channel_master', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->string('channel_name')->nullable();
            $table->string('regn_link')->nullable();
            $table->string('status')->nullable();
            $table->string('aa_stage')->nullable();
            $table->date('date')->nullable();
            $table->string('login_link')->nullable();
            $table->string('email_userid')->nullable();
            $table->string('password')->nullable();
            $table->date('last_date')->nullable();
            $table->string('remarks')->nullable();
            $table->date('next_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals_channel_master');
    }
};
