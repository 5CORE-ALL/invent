<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->index(['report_date_range', 'campaign_id', 'ad_type', 'id'], $index);
            });
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
            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropIndex($index);
            });
        }
    }
};
