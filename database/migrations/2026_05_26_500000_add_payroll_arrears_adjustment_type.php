<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_arrears')) {
            return;
        }

        if (! Schema::hasColumn('payroll_arrears', 'adjustment_type')) {
            Schema::table('payroll_arrears', function (Blueprint $table) {
                $table->string('adjustment_type', 10)->default('add')->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll_arrears', 'adjustment_type')) {
            Schema::table('payroll_arrears', function (Blueprint $table) {
                $table->dropColumn('adjustment_type');
            });
        }
    }
};
