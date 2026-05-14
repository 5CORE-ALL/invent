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
        // Idempotent: skip if the table was already created on this server
        // (e.g. by the duplicate 2026_05_06_061224 migration or a prior deploy).
        if (Schema::hasTable('status_toggle_history')) {
            return;
        }

        Schema::create('status_toggle_history', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->enum('status', ['red', 'green'])->default('red');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamps();

            $table->index(['sku', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_toggle_history');
    }
};
