<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global, team-wide checklist (CL Gen).
 *
 * Unlike designation_rr_checkpoints (which lives under a specific R&R item
 * for a specific designation), this table holds a single shared list that
 * applies to every team member regardless of designation — attendance,
 * communication, ETC vs ATC, overdues, TAT averages, etc.
 *
 * AI seeds the first set; team can add / update / delete / re-weight.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('general_checklist_items')) {
            return;
        }

        Schema::create('general_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->string('category', 100)->nullable(); // e.g. Attendance / Communication / Productivity
            $table->string('title', 500);
            $table->text('description')->nullable();
            // Weightage 1–10 = importance contribution to the General score.
            $table->unsignedTinyInteger('weightage')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('source', 20)->default('manual'); // 'ai' on first seed
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('sort_order');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_checklist_items');
    }
};
