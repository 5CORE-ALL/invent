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
        // (e.g. by the duplicate 2026_05_06_061219 migration or a prior deploy).
        if (Schema::hasTable('audit_suggestion_history')) {
            return;
        }

        Schema::create('audit_suggestion_history', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->text('audit_suggestion')->nullable();
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
        Schema::dropIfExists('audit_suggestion_history');
    }
};
