<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables of the Temu 1 Data page: a copy of each Ebay1 Data table (same columns and indexes), starting empty. */
    private const TABLES = [
        'item_specifics', 'extra_columns', 'column_sources', 'column_groups',
        'settings', 'pending_specifics', 'cell_styles', 'missing_history',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            $from = 'ebay1_'.$name;
            $to = 'temu1_'.$name;
            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                DB::statement('CREATE TABLE `'.$to.'` LIKE `'.$from.'`');
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::dropIfExists('temu1_'.$name);
        }
    }
};
