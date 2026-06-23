<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user check state for a designation_rr_checkpoints row.
 *
 * One row per (user, checkpoint). Toggled from the CL R&R modal; used to
 * compute per-R&R-item scores and the overall user score via weightages.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_rr_checkpoint_progress')) {
            return;
        }

        Schema::create('user_rr_checkpoint_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('designation_rr_checkpoint_id')
                ->constrained('designation_rr_checkpoints')
                ->cascadeOnDelete();
            $table->boolean('checked')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'designation_rr_checkpoint_id'], 'user_rr_checkpoint_progress_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_rr_checkpoint_progress');
    }
};
