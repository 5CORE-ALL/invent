<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * All Marketplace Master "Sheet" column uses channel_master.sheet_link — point TopDawg at the sales dashboard.
     */
    public function up(): void
    {
        if (! Schema::hasTable('channel_master') || ! Schema::hasColumn('channel_master', 'sheet_link')) {
            return;
        }

        $dashboardPath = '/topdawg/sales-dashboard';
        $now = now();

        $exists = DB::table('channel_master')->where('channel', 'TopDawg')->exists();

        if ($exists) {
            DB::table('channel_master')
                ->where('channel', 'TopDawg')
                ->where(function ($q) {
                    $q->whereNull('sheet_link')->orWhere('sheet_link', '');
                })
                ->update([
                    'sheet_link' => $dashboardPath,
                    'updated_at' => $now,
                ]);

            return;
        }

        $row = [
            'channel' => 'TopDawg',
            'status' => 'Active',
            'sheet_link' => $dashboardPath,
            'created_at' => $now,
            'updated_at' => $now,
        ];

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
        if (! Schema::hasTable('channel_master') || ! Schema::hasColumn('channel_master', 'sheet_link')) {
            return;
        }

        DB::table('channel_master')
            ->where('channel', 'TopDawg')
            ->where('sheet_link', '/topdawg/sales-dashboard')
            ->update(['sheet_link' => null, 'updated_at' => now()]);
    }
};
