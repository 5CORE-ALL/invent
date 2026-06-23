<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manager / senior-level checklist, per-designation.
 *
 * Distinct from designation_rr_checkpoints (which evaluates execution of
 * R&R items) — this set captures leadership duties: training, auditing,
 * monitoring, assigning tasks, follow-ups to juniors, on-time delivery
 * tracking, etc. Each designation gets its own AI-seeded list so a
 * "Sales Manager" can have different items than an "Engineering Manager".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('designation_mgr_checkpoints')) {
            return;
        }

        Schema::create('designation_mgr_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('designation', 191)->index();
            $table->string('category', 100)->nullable(); // e.g. Training / Auditing / Delivery
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('weightage')->default(1); // 1-10
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('source', 20)->default('manual'); // 'ai' on first seed
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['designation', 'sort_order'], 'designation_mgr_checkpoints_des_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designation_mgr_checkpoints');
    }
};
