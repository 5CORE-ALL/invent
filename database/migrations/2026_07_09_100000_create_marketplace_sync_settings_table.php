<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_sync_settings')) {
            return;
        }

        Schema::create('marketplace_sync_settings', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace', 64)->unique();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_sync_settings');
    }
};
