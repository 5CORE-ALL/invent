<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every SKU on eBay 2 and eBay 3 is REQ (none is NRL). A SKU with no saved value already counts as REQ,
 * so only the saved NRL values need changing.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['ebay_two_data_views', 'ebay3_data_view'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->select('id', 'value')->orderBy('id')->chunkById(500, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $value = json_decode((string) $row->value, true);
                    if (! is_array($value)) {
                        $value = [];
                    }
                    if (($value['NRL'] ?? null) === 'REQ' && ! isset($value['NR']) && ! isset($value['NRP']) && ! isset($value['nr_req'])) {
                        continue;
                    }
                    unset($value['NR'], $value['NRP'], $value['nr_req']);
                    $value['NRL'] = 'REQ';
                    DB::table($table)->where('id', $row->id)->update(['value' => json_encode($value)]);
                }
            });
        }
    }

    public function down(): void
    {
        // The earlier NRL values are not kept.
    }
};
