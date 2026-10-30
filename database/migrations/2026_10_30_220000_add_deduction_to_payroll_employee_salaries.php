<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_employee_salaries', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_employee_salaries', 'deduction')) {
                $table->decimal('deduction', 12, 2)->default(0)->after('incentive');
            }
            if (! Schema::hasColumn('payroll_employee_salaries', 'deduction_reason')) {
                $table->string('deduction_reason', 500)->nullable()->after('deduction');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_employee_salaries', function (Blueprint $table) {
            foreach (['deduction_reason', 'deduction'] as $column) {
                if (Schema::hasColumn('payroll_employee_salaries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
