<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_employee_salaries', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_employee_salaries', 'edited_by')) {
                $table->string('edited_by')->nullable()->after('upi_id');
            }
            if (! Schema::hasColumn('payroll_employee_salaries', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('edited_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_employee_salaries', function (Blueprint $table) {
            foreach (['edited_by', 'edited_at'] as $column) {
                if (Schema::hasColumn('payroll_employee_salaries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
