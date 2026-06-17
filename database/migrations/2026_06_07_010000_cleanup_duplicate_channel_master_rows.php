<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cleans up duplicate / stray channel_master rows that prevented logos from
 * being saved & displayed on the Active Channel Master page:
 *   - Faire / Purchasing Power had a logo-less ACTIVE row plus an inactive
 *     duplicate that held the logo. Keep the active row, move the logo onto it,
 *     and drop the duplicate(s).
 *   - Remove the extra "Depop.com" account (canonical channel is "Depop").
 *
 * Name-based (not id-based) so it runs safely on any environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('channel_master')) {
            return;
        }

        $hasStatus = Schema::hasColumn('channel_master', 'status');
        $hasLogo   = Schema::hasColumn('channel_master', 'logo');

        // 1. De-duplicate Faire and Purchasing Power — keep the active row,
        //    ensure it has a logo, delete the remaining duplicate(s).
        foreach (['Faire', 'Purchasing Power'] as $name) {
            $query = DB::table('channel_master')->where('channel', $name);

            if ($hasStatus) {
                $query->orderByRaw("CASE WHEN LOWER(TRIM(status)) = 'active' THEN 0 ELSE 1 END");
            }
            $rows = $query->orderBy('id', 'asc')->get();

            if ($rows->count() <= 1) {
                continue; // nothing to clean up
            }

            $keep = $rows->first();

            // Move a logo onto the kept row if it doesn't have one.
            if ($hasLogo && empty($keep->logo)) {
                foreach ($rows as $row) {
                    if (!empty($row->logo)) {
                        DB::table('channel_master')
                            ->where('id', $keep->id)
                            ->update(['logo' => $row->logo]);
                        break;
                    }
                }
            }

            $deleteIds = $rows->slice(1)->pluck('id')->all();
            if (!empty($deleteIds)) {
                DB::table('channel_master')->whereIn('id', $deleteIds)->delete();
            }
        }

        // 2. Remove the extra "Depop.com" account, but only if the canonical
        //    "Depop" row exists (so we never delete the only Depop record).
        $hasDepop = DB::table('channel_master')
            ->whereRaw("LOWER(TRIM(channel)) = ?", ['depop'])
            ->exists();

        if ($hasDepop) {
            DB::table('channel_master')
                ->whereRaw("LOWER(TRIM(channel)) = ?", ['depop.com'])
                ->delete();
        }
    }

    public function down(): void
    {
        // Data cleanup is not reversible (removed duplicate rows cannot be
        // reconstructed). Intentionally left as a no-op.
    }
};
