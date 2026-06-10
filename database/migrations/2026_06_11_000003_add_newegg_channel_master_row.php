<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('channel_master')) {
            return;
        }

        $exists = DB::table('channel_master')
            ->whereRaw("LOWER(REPLACE(channel, ' ', '')) = 'newegg'")
            ->exists();

        if ($exists) {
            return;
        }

        // Pull the configured Newegg percentage so the channel row matches the
        // marketplace_percentages master (Neweggb2c).
        $percentage = DB::table('marketplace_percentages')
            ->where('marketplace', 'Neweggb2c')
            ->value('percentage') ?? 100;

        $row = [
            'channel'            => 'Newegg',
            'type'               => 'B2C',
            'status'             => 'active',
            'channel_percentage' => $percentage,
            'created_at'         => now(),
            'updated_at'         => now(),
        ];

        // Only set columns that actually exist on this install.
        $row = array_filter(
            $row,
            fn ($key) => Schema::hasColumn('channel_master', $key),
            ARRAY_FILTER_USE_KEY
        );

        DB::table('channel_master')->insert($row);
    }

    public function down(): void
    {
        if (!Schema::hasTable('channel_master')) {
            return;
        }

        DB::table('channel_master')
            ->whereRaw("LOWER(REPLACE(channel, ' ', '')) = 'newegg'")
            ->where('channel', 'Newegg')
            ->delete();
    }
};
