<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_employee_salaries', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_employee_salaries', 'salary_pp_overridden')) {
                $table->boolean('salary_pp_overridden')->default(false)->after('salary_pp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_employee_salaries', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_employee_salaries', 'salary_pp_overridden')) {
                $table->dropColumn('salary_pp_overridden');
            }
        });
    }
};
