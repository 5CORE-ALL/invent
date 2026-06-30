<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_payroll_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('hourly_rate', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('attendance_payroll_period_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedInteger('manual_seconds')->default(0);
            $table->decimal('hourly_rate', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->decimal('adjustment', 12, 2)->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'period_from', 'period_to'], 'att_payroll_period_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_payroll_period_lines');
        Schema::dropIfExists('attendance_payroll_profiles');
    }
};
