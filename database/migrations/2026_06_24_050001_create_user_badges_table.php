<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot — which badges have been awarded to which users.
 *
 * One row per (user, badge) pair. Tracks who awarded it and when, plus
 * an optional note (e.g. "for the Q2 launch").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_badges')) {
            return;
        }

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users', 'id', 'ub_user_id_fk')
                ->cascadeOnDelete();
            $table->foreignId('badge_id')
                ->constrained('badges', 'id', 'ub_badge_id_fk')
                ->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('awarded_by_user_id')->nullable();
            $table->timestamp('awarded_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'badge_id'], 'user_badges_user_badge_unique');
            $table->index('awarded_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
    }
};
