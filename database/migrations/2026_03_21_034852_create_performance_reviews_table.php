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
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('designation_id')->constrained('designations')->onDelete('cascade');
            $table->string('review_period'); // Weekly, Monthly, Custom
            $table->date('review_date');
            $table->date('period_start_date')->nullable();
            $table->date('period_end_date')->nullable();
            $table->decimal('total_score', 5, 2)->default(0); // Calculated weighted score
            $table->decimal('normalized_score', 5, 2)->default(0); // Score normalized to 5 scale
            $table->string('performance_level')->nullable(); // Excellent, Good, Average, Needs Improvement
            $table->text('overall_feedback')->nullable();
            $table->text('ai_feedback')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['employee_id', 'review_date']);
            $table->index(['reviewer_id']);
            $table->index('review_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};
