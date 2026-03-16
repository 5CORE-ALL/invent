<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reverb_sync_settings', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace', 32)->default('reverb');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique('marketplace');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reverb_sync_settings');
    }
};
