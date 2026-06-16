<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('help_desk_gurus')) {
            return;
        }

        $email = 'mgr-advertisement@5core.com';

        $exists = DB::table('help_desk_gurus')
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('help_desk_gurus')->insert([
            'name' => 'Mgr Advertisement',
            'email' => $email,
            'created_by_email' => 'system-migration',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('help_desk_gurus')) {
            return;
        }

        DB::table('help_desk_gurus')
            ->whereRaw('LOWER(email) = ?', ['mgr-advertisement@5core.com'])
            ->delete();
    }
};
