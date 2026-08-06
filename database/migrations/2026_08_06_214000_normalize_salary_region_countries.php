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
        // One-time default: everyone without a country becomes India.
        DB::table('users')
            ->whereNull('salary_region')
            ->orWhereIn('salary_region', ['', 'default'])
            ->update(['salary_region' => 'india']);

        $chinaEmails = array_map('strtolower', config('payroll.china_emails', []));
        if ($chinaEmails !== []) {
            DB::table('users')
                ->whereIn(DB::raw('LOWER(email)'), $chinaEmails)
                ->update(['salary_region' => 'china']);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('salary_region', 32)->default('india')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->where('salary_region', 'india')
            ->update(['salary_region' => 'default']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('salary_region', 32)->default('default')->change();
        });
    }
};
