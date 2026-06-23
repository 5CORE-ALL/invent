<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user check state for one designation_mgr_checkpoints row.
 *
 * Upserted from the CL Mgr modal. Combined with weightages to compute
 * each manager's own CL Mgr score (before juniors are added).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_mgr_checkpoint_progress')) {
            return;
        }

        Schema::create('user_mgr_checkpoint_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('designation_mgr_checkpoint_id')
                ->constrained('designation_mgr_checkpoints')
                ->cascadeOnDelete();
            $table->boolean('checked')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'designation_mgr_checkpoint_id'], 'user_mgr_checkpoint_progress_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_mgr_checkpoint_progress');
    }
};
