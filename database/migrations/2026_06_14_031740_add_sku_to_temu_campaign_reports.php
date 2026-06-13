<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temu_campaign_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('temu_campaign_reports', 'sku')) {
                $table->string('sku')->nullable()->index()->after('goods_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('temu_campaign_reports', function (Blueprint $table) {
            if (Schema::hasColumn('temu_campaign_reports', 'sku')) {
                $table->dropColumn('sku');
            }
        });
    }
};
