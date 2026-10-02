<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_percentages')) {
            return;
        }

        $now = now();
        $updated = DB::table('marketplace_percentages')
            ->whereRaw('LOWER(TRIM(marketplace)) = ?', ['vinted'])
            ->update([
                'percentage' => 95,
                'updated_at' => $now,
            ]);

        if ($updated === 0) {
            DB::table('marketplace_percentages')->insert([
                'marketplace' => 'Vinted',
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
            ->whereRaw('LOWER(TRIM(marketplace)) = ?', ['vinted'])
            ->update([
                'percentage' => 87,
                'updated_at' => now(),
            ]);
    }
};
