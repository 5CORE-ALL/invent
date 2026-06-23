<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkpoints nested under a designation R&R item.
 *
 * Each row is one checkbox-style item the user is expected to demonstrate
 * for the parent R&R. AI seeds the initial list (with weightages 1–10 to
 * indicate importance), team members can then add / delete / re-weight.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('designation_rr_checkpoints')) {
            return;
        }

        Schema::create('designation_rr_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designation_rr_item_id')
                ->constrained('designation_rr_items')
                ->cascadeOnDelete();
            $table->string('title', 500);
            $table->text('description')->nullable();
            // Weightage 1–10 = importance contribution to the item / overall score.
            $table->unsignedTinyInteger('weightage')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('source', 20)->default('manual'); // 'ai' on first seed
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['designation_rr_item_id', 'sort_order'], 'designation_rr_checkpoints_item_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designation_rr_checkpoints');
    }
};
