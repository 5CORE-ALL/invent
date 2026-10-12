<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * eBay 1 / 2 / 3 no longer have a Dil / Sprc Dil rule. Deletes the stored slabs
 * ({channel}_dil_vs_groi) and the Std-vs-Dil switch ({channel}_sprice_active_rule).
 * The rows are dumped to storage/app/backups first, so they can be restored by hand.
 */
return new class extends Migration
{
    private const CHANNELS = ['ebay1', 'ebay2', 'ebay3'];

    private const SUFFIXES = ['_dil_vs_groi', '_sprice_active_rule'];

    public function up(): void
    {
        if (! Schema::hasTable('channel_tabulator_column_settings')) {
            return;
        }

        $names = [];
        foreach (self::CHANNELS as $channel) {
            foreach (self::SUFFIXES as $suffix) {
                $names[] = $channel.$suffix;
            }
        }

        $rows = DB::table('channel_tabulator_column_settings')->whereIn('channel_name', $names)->get();
        if ($rows->isEmpty()) {
            return;
        }

        Storage::disk('local')->put(
            'backups/ebay_sprc_dil_rule_data_'.date('Ymd_His').'.json',
            $rows->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        DB::table('channel_tabulator_column_settings')->whereIn('channel_name', $names)->delete();
    }

    public function down(): void
    {
        // Deleted rows are not restored. Use the JSON backup in storage/app/backups if needed.
    }
};
