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
        Schema::create('team_logger_hours', function (Blueprint $table) {
            $table->id();
            $table->string('employee_email')->index();
            $table->string('month', 20); // e.g., "May 2026"
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('productive_hours')->default(0);
            $table->decimal('total_hours', 8, 2)->default(0);
            $table->decimal('idle_hours', 8, 2)->default(0);
            $table->decimal('active_hours', 8, 2)->default(0);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            
            // Unique constraint to prevent duplicate entries for same employee and month
            $table->unique(['employee_email', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_logger_hours');
    }
};
