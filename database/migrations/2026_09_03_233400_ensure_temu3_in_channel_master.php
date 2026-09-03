<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * All Marketplace Master lists Active channel_master rows.
     * Ensure Temu 3 exists and is Active so sheet sales can appear.
     */
    public function up(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }

        $match = "LOWER(REPLACE(REPLACE(REPLACE(channel, ' ', ''), '-', ''), '_', '')) IN ('temu3', 'temuthree')";

        $exists = DB::table('channel_master')->whereRaw($match)->exists();

        if ($exists) {
            $update = [
                'status' => 'Active',
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('channel_master', 'missing_link')) {
                $update['missing_link'] = '/temu3-decrease';
            }
            $update = array_filter(
                $update,
                fn ($key) => Schema::hasColumn('channel_master', $key),
                ARRAY_FILTER_USE_KEY
            );
            DB::table('channel_master')->whereRaw($match)->update($update);

            return;
        }

        $percentage = 100;
        if (Schema::hasTable('marketplace_percentages')) {
            $percentage = DB::table('marketplace_percentages')
                ->whereIn('marketplace', ['Temu 3', 'Temu3', 'Temu'])
                ->orderByRaw("CASE WHEN marketplace IN ('Temu 3', 'Temu3') THEN 0 ELSE 1 END")
                ->value('percentage') ?? 100;
        }

        $row = [
            'channel' => 'Temu 3',
            'type' => 'B2C',
            'status' => 'Active',
            'channel_percentage' => $percentage,
            'missing_link' => '/temu3-decrease',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $row = array_filter(
            $row,
            fn ($key) => Schema::hasColumn('channel_master', $key),
            ARRAY_FILTER_USE_KEY
        );

        DB::table('channel_master')->insert($row);
    }

    public function down(): void
    {
        // Keep the channel row; it may have been created by hand.
    }
};
