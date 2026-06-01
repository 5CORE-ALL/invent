<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_employee_salaries', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_employee_salaries', 'hours_overridden')) {
                $table->boolean('hours_overridden')->default(false)->after('hours_worked');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_employee_salaries', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_employee_salaries', 'hours_overridden')) {
                $table->dropColumn('hours_overridden');
            }
        });
    }
};
