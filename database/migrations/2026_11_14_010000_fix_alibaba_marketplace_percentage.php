<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The "Alibaba" row in marketplace_percentages was 0, so takeHomeDecimal('Alibaba') returned 0 and every
 * /alibaba-analytics profit was (price x 0) - LP = -LP (GPFT about -100% on every row).
 * Sets 95 (the page's own default) only when the row is missing or 0; a value someone set on purpose is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_percentages')) {
            return;
        }

        $now = now();
        $rows = DB::table('marketplace_percentages')
            ->whereRaw('LOWER(TRIM(marketplace)) = ?', ['alibaba'])
            ->get(['id', 'percentage']);

        if ($rows->isEmpty()) {
            DB::table('marketplace_percentages')->insert([
                'marketplace' => 'Alibaba',
                'percentage' => 95,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('marketplace_percentages')
            ->whereIn('id', $rows->filter(fn ($r) => ! ((float) $r->percentage > 0))->pluck('id')->all())
            ->update(['percentage' => 95, 'updated_at' => $now]);
    }

    public function down(): void
    {
        // Nothing to restore: the previous value was an invalid 0.
    }
};
