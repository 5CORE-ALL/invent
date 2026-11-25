<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Future (target) salary the company has planned for an employee. Set by president@5core.com from the
 * floating "Salary" icon and read-only for the employee. The salary is meant to grow by roughly
 * 500-1000 per month based on performance until it reaches this target.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salary_projections')) {
            return;
        }

        Schema::create('salary_projections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->decimal('future_salary', 12, 2);
            $table->string('set_by', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_projections');
    }
};
