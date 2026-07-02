<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_employee_salaries', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_employee_salaries', 'incentive_document_path')) {
                $table->string('incentive_document_path')->nullable()->after('incentive');
                $table->string('incentive_document_name')->nullable()->after('incentive_document_path');
                $table->string('bill_document_path')->nullable()->after('incentive_document_name');
                $table->string('bill_document_name')->nullable()->after('bill_document_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_employee_salaries', function (Blueprint $table) {
            foreach (['incentive_document_path', 'incentive_document_name', 'bill_document_path', 'bill_document_name'] as $column) {
                if (Schema::hasColumn('payroll_employee_salaries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
