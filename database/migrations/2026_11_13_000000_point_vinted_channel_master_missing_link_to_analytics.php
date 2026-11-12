<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * /vinted/pricing was removed — Active Channels (channel_master) Vinted Missing link
     * now opens /vinted/analytics.
     */
    public function up(): void
    {
        if (! Schema::hasTable('channel_master') || ! Schema::hasColumn('channel_master', 'missing_link')) {
            return;
        }

        $updates = ['missing_link' => '/vinted/analytics'];
        if (Schema::hasColumn('channel_master', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        DB::table('channel_master')
            ->whereRaw('LOWER(TRIM(channel)) = ?', ['vinted'])
            ->update($updates);
    }

    public function down(): void
    {
        // no-op
    }
};
