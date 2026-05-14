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
        // Duplicate of 2026_05_06_042233_create_status_toggle_history_table.
        // Made idempotent so the second run on the same server is a safe no-op.
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
        // Intentionally a no-op — see notes in 061219 duplicate.
    }
};
