<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_employee_salaries', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_employee_salaries', 'incentive')) {
                $table->decimal('incentive', 12, 2)->default(0)->after('adv_inc_other');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_employee_salaries', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_employee_salaries', 'incentive')) {
                $table->dropColumn('incentive');
            }
        });
    }
};
