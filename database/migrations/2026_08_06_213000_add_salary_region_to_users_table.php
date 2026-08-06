<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('salary_region', 32)->default('default')->after('show_in_salary');
        });

        $chinaEmails = array_map('strtolower', config('payroll.china_emails', []));
        if ($chinaEmails !== []) {
            DB::table('users')
                ->whereIn(DB::raw('LOWER(email)'), $chinaEmails)
                ->update(['salary_region' => 'china']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('salary_region');
        });
    }
};
