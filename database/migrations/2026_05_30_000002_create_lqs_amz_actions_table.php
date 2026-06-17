<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lqs_amz_actions', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->string('action', 100);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('sku');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lqs_amz_actions');
    }
};
