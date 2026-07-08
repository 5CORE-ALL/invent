<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_ads_master_check_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_ads_master_id');
            $table->boolean('is_checked');            // the new state after this action
            $table->string('action');                 // 'checked' | 'unchecked'
            $table->string('username')->nullable();   // who performed the action
            $table->timestamp('created_at')->nullable();

            $table->index('video_ads_master_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_ads_master_check_history');
    }
};
