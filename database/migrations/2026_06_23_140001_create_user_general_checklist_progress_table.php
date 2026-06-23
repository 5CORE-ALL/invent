<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user check state for one general_checklist_items row.
 *
 * One row per (user, item). Upserted from the CL Gen modal whenever a
 * checkbox is toggled. Used together with weightages to compute the
 * General score in TaskController::buildGeneralChecklistPayload().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_general_checklist_progress')) {
            return;
        }

        Schema::create('user_general_checklist_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('general_checklist_item_id')
                ->constrained('general_checklist_items')
                ->cascadeOnDelete();
            $table->boolean('checked')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'general_checklist_item_id'], 'user_general_checklist_progress_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_general_checklist_progress');
    }
};
