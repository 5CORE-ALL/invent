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
        if (Schema::hasTable('performance_review_items')) {
            return;
        }

        Schema::create('performance_review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('performance_reviews')->onDelete('cascade');
            $table->foreignId('checklist_item_id')->constrained('checklist_items')->onDelete('cascade');
            $table->integer('rating')->default(1); // 1-5 scale
            $table->text('comment')->nullable();
            $table->decimal('weighted_score', 5, 2)->default(0); // rating * weight
            $table->timestamps();
            
            $table->index('review_id');
            $table->index('checklist_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_review_items');
    }
};
