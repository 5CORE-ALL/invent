<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'amazon_sp_campaign_reports' => 'amz_sp_camp_range_cid_idx',
            'amazon_sb_campaign_reports' => 'amz_sb_camp_range_cid_idx',
        ] as $table => $index) {
            if (! Schema::hasTable($table) || Schema::hasIndex($table, $index)) {
                continue;
            }

            DB::statement('SET SESSION net_read_timeout = 3600');
            DB::statement('SET SESSION net_write_timeout = 3600');
            DB::statement('SET SESSION wait_timeout = 3600');

            // Full VARCHAR(255) columns exceed this server's 1000-byte index limit.
            // Prefixes cover L30 / YYYY-MM-DD, Amazon campaign ids, and ad type labels.
            DB::statement(
                "ALTER TABLE `{$table}` ADD INDEX `{$index}` (`report_date_range`(12), `campaign_id`(40), `ad_type`(24), `id`)"
            );
        }
    }

    public function down(): void
    {
        foreach ([
            'amazon_sp_campaign_reports' => 'amz_sp_camp_range_cid_idx',
            'amazon_sb_campaign_reports' => 'amz_sb_camp_range_cid_idx',
        ] as $table => $index) {
            if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $index)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }
};
