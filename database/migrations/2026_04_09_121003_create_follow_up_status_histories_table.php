<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('follow_up_status_histories')) {
            return;
        }

        Schema::create('follow_up_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follow_up_id')->constrained('follow_ups')->cascadeOnDelete();
            $table->string('old_status', 32)->nullable();
            $table->string('new_status', 32);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['follow_up_id', 'created_at']);
            $table->index('changed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_status_histories');
    }
};
