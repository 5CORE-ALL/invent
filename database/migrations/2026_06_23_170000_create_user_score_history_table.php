<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lifetime score snapshots per (user, score_type).
 *
 * Powers the small history-graph dot next to each CL column score chip
 * (CL R&R / CL Mgr / CL Gen). A row is appended every time the user
 * toggles a related checkpoint so the line chart matches the user's
 * actual interactions, no cron required.
 *
 *  score_type: 'clrr' | 'clmgr' | 'clgen'
 *  percent:    0–100
 *  captured_at: indexed for fast range scans when rendering the chart.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_score_history')) {
            return;
        }

        Schema::create('user_score_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('score_type', 16); // clrr | clmgr | clgen
            $table->unsignedTinyInteger('percent')->default(0);
            $table->timestamp('captured_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'score_type', 'captured_at'], 'user_score_history_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_score_history');
    }
};
