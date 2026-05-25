<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_months')) {
            Schema::create('payroll_months', function (Blueprint $table) {
                $table->id();
                $table->string('month_label', 32)->unique();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->string('status', 20)->default('draft');
                $table->boolean('is_locked')->default(false);
                $table->string('payslip_format', 20)->default('standard');
                $table->timestamp('payslips_released_at')->nullable();
                $table->timestamp('it_statements_released_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payroll_employee_salaries')) {
            Schema::create('payroll_employee_salaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_month_id')->constrained('payroll_months')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('salary_pp', 12, 2)->default(0);
                $table->decimal('increment', 12, 2)->default(0);
                $table->decimal('other', 12, 2)->default(0);
                $table->decimal('adv_inc_other', 12, 2)->default(0);
                $table->decimal('hours_worked', 10, 2)->default(0);
                $table->decimal('gross_amount', 12, 2)->default(0);
                $table->decimal('lop_amount', 12, 2)->default(0);
                $table->decimal('arrears_amount', 12, 2)->default(0);
                $table->decimal('payments_total', 12, 2)->default(0);
                $table->decimal('deductions_total', 12, 2)->default(0);
                $table->decimal('net_amount', 12, 2)->default(0);
                $table->string('bank_1')->nullable();
                $table->string('bank_2')->nullable();
                $table->string('upi_id')->nullable();
                $table->boolean('is_new_hire')->default(false);
                $table->timestamps();
                $table->unique(['payroll_month_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('payroll_salary_components')) {
            Schema::create('payroll_salary_components', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_month_id')->constrained('payroll_months')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 20)->default('earning');
                $table->string('label');
                $table->decimal('amount', 12, 2);
                $table->boolean('is_one_time')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payroll_payment_deductions')) {
            Schema::create('payroll_payment_deductions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_month_id')->constrained('payroll_months')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('entry_type', 20);
                $table->string('label');
                $table->decimal('amount', 12, 2);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payroll_arrears')) {
            Schema::create('payroll_arrears', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('payroll_month_id')->nullable()->constrained('payroll_months')->nullOnDelete();
                $table->decimal('amount', 12, 2);
                $table->date('period_from')->nullable();
                $table->date('period_to')->nullable();
                $table->string('description')->nullable();
                $table->string('status', 20)->default('pending');
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payroll_settlements')) {
            Schema::create('payroll_settlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->date('last_working_date')->nullable();
                $table->date('settlement_date')->nullable();
                $table->json('earnings')->nullable();
                $table->json('deductions')->nullable();
                $table->decimal('net_settlement', 12, 2)->default(0);
                $table->string('status', 20)->default('draft');
                $table->text('notes')->nullable();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payroll_previous_records')) {
            Schema::create('payroll_previous_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('month_label', 32);
                $table->decimal('gross_amount', 12, 2)->default(0);
                $table->decimal('deductions_total', 12, 2)->default(0);
                $table->decimal('net_amount', 12, 2)->default(0);
                $table->text('notes')->nullable();
                $table->json('imported_data')->nullable();
                $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('imported_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'month_label']);
            });
        }

        if (! Schema::hasTable('payroll_payslips')) {
            Schema::create('payroll_payslips', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_month_id')->constrained('payroll_months')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('format', 20)->default('standard');
                $table->json('data')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();
                $table->unique(['payroll_month_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payslips');
        Schema::dropIfExists('payroll_previous_records');
        Schema::dropIfExists('payroll_settlements');
        Schema::dropIfExists('payroll_arrears');
        Schema::dropIfExists('payroll_payment_deductions');
        Schema::dropIfExists('payroll_salary_components');
        Schema::dropIfExists('payroll_employee_salaries');
        Schema::dropIfExists('payroll_months');
    }
};
