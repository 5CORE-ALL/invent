<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user progress on a designation_rr_items row.
 *
 * Users that share a designation share the same list of items, but each
 * user owns their own progress (status + note). The unique constraint
 * keeps it idempotent so the modal can upsert on every toggle.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_rr_progress')) {
            return;
        }

        Schema::create('user_rr_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('designation_rr_item_id')
                ->constrained('designation_rr_items')
                ->cascadeOnDelete();
            // pending | in_progress | done
            $table->string('status', 20)->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'designation_rr_item_id'], 'user_rr_progress_user_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_rr_progress');
    }
};
