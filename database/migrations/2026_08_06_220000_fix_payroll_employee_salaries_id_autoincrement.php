<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_employee_salaries')) {
            return;
        }

        // id lost PRIMARY KEY / AUTO_INCREMENT, so new sheet rows (e.g. China
        // candidates without prior hours) cannot be inserted.
        $hasPrimary = collect(DB::select('SHOW INDEX FROM payroll_employee_salaries'))
            ->contains(fn ($idx) => $idx->Key_name === 'PRIMARY');

        if (! $hasPrimary) {
            DB::statement('ALTER TABLE payroll_employee_salaries ADD PRIMARY KEY (id)');
        }

        DB::statement('ALTER TABLE payroll_employee_salaries MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        $max = (int) DB::table('payroll_employee_salaries')->max('id');
        DB::statement('ALTER TABLE payroll_employee_salaries AUTO_INCREMENT = '.max($max + 1, 1));
    }

    public function down(): void
    {
        // Non-destructive: leave AUTO_INCREMENT in place.
    }
};
