<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('topdawg_sync_settings')) {
            return;
        }

        Schema::create('topdawg_sync_settings', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace', 32)->default('topdawg');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique('marketplace');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topdawg_sync_settings');
    }
};
