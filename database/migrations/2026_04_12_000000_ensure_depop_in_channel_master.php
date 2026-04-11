<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * All Marketplace Master lists rows from channel_master; ensure Depop appears like other channels.
     */
    public function up(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }

        $exists = DB::table('channel_master')
            ->whereRaw('LOWER(TRIM(channel)) = ?', ['depop'])
            ->exists();

        if ($exists) {
            return;
        }

        $now = now();
        $row = [
            'channel' => 'Depop',
            'status' => 'Active',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('channel_master', 'sheet_link')) {
            $row['sheet_link'] = '/depop/sheet';
        }
        if (Schema::hasColumn('channel_master', 'type')) {
            $row['type'] = 'B2C';
        }
        if (Schema::hasColumn('channel_master', 'nr')) {
            $row['nr'] = 0;
        }
        if (Schema::hasColumn('channel_master', 'w_ads')) {
            $row['w_ads'] = 0;
        }
        if (Schema::hasColumn('channel_master', 'update')) {
            $row['update'] = 0;
        }

        DB::table('channel_master')->insert($row);
    }

    public function down(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }

        DB::table('channel_master')->whereRaw('LOWER(TRIM(channel)) = ?', ['depop'])->delete();
    }
};
