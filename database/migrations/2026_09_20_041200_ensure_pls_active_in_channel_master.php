<?php

use App\Models\ChannelMasterCalculatedData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Active Channel only lists channel_master rows with status Active.
     * PLS exists locally/production as Inactive and is missing from calculated_data.
     */
    public function up(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }

        $match = "LOWER(TRIM(channel)) = 'pls'";
        $exists = DB::table('channel_master')->whereRaw($match)->exists();

        $update = [
            'channel' => 'PLS',
            'status' => 'Active',
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('channel_master', 'missing_link')) {
            $update['missing_link'] = '/pls-pricing';
        }
        if (Schema::hasColumn('channel_master', 'type')) {
            $update['type'] = 'B2C';
        }

        $update = array_filter(
            $update,
            fn ($key) => Schema::hasColumn('channel_master', $key),
            ARRAY_FILTER_USE_KEY
        );

        if ($exists) {
            DB::table('channel_master')->whereRaw($match)->update($update);
        } else {
            $row = $update;
            if (Schema::hasColumn('channel_master', 'created_at')) {
                $row['created_at'] = now();
            }
            if (Schema::hasColumn('channel_master', 'nr')) {
                $row['nr'] = 0;
            }
            if (Schema::hasColumn('channel_master', 'w_ads')) {
                $row['w_ads'] = 0;
            }
            DB::table('channel_master')->insert($row);
        }

        try {
            ChannelMasterCalculatedData::bumpFastPayloadCache();
        } catch (\Throwable $e) {
            // Cache may be unavailable during migrate.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }

        DB::table('channel_master')
            ->whereRaw("LOWER(TRIM(channel)) = 'pls'")
            ->update(array_filter([
                'status' => 'Inactive',
                'updated_at' => now(),
            ], fn ($key) => Schema::hasColumn('channel_master', $key), ARRAY_FILTER_USE_KEY));
    }
};
