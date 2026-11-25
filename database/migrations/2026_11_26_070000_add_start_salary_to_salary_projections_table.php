<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remember the employee's salary at the moment the future salary was set, so the Salary icon can show
 * progress from that starting point to the target.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salary_projections') && ! Schema::hasColumn('salary_projections', 'start_salary')) {
            Schema::table('salary_projections', function (Blueprint $table) {
                $table->decimal('start_salary', 12, 2)->nullable()->after('future_salary');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('salary_projections', 'start_salary')) {
            Schema::table('salary_projections', function (Blueprint $table) {
                $table->dropColumn('start_salary');
            });
        }
    }
};
