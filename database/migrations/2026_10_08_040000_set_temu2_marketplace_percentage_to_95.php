<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The "Temu 2" row sat at 100 (zero Temu commission), so every Temu 2 GPFT$ was
 * computed with no marketplace fee. Temu 2 takes the same cut as Temu 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_percentages')) {
            return;
        }

        $now = now();
        $updated = DB::table('marketplace_percentages')
            ->whereRaw('LOWER(TRIM(marketplace)) IN (?, ?, ?)', ['temu 2', 'temutwo', 'temu2'])
            ->update([
                'percentage' => 95,
                'updated_at' => $now,
            ]);

        if ($updated === 0) {
            DB::table('marketplace_percentages')->insert([
                'marketplace' => 'Temu 2',
                'percentage' => 95,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_percentages')) {
            return;
        }

        DB::table('marketplace_percentages')
            ->whereRaw('LOWER(TRIM(marketplace)) IN (?, ?, ?)', ['temu 2', 'temutwo', 'temu2'])
            ->update([
                'percentage' => 100,
                'updated_at' => now(),
            ]);
    }
};
