<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('amazon_sp_keyword_reports')) {
            return;
        }
        if (Schema::hasIndex('amazon_sp_keyword_reports', 'amz_sp_kw_l30_count_idx')) {
            return;
        }

        Schema::table('amazon_sp_keyword_reports', function (Blueprint $table) {
            $table->index(['report_date_range', 'campaign_id', 'keyword_id'], 'amz_sp_kw_l30_count_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('amazon_sp_keyword_reports')) {
            return;
        }
        if (! Schema::hasIndex('amazon_sp_keyword_reports', 'amz_sp_kw_l30_count_idx')) {
            return;
        }

        Schema::table('amazon_sp_keyword_reports', function (Blueprint $table) {
            $table->dropIndex('amz_sp_kw_l30_count_idx');
        });
    }
};
