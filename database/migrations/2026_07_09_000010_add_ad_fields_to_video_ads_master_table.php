<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_ads_master', function (Blueprint $table) {
            $table->boolean('ad_checked')->default(false)->after('checked_at');
            $table->string('ad_checked_by')->nullable()->after('ad_checked');
            $table->timestamp('ad_checked_at')->nullable()->after('ad_checked_by');
        });
    }

    public function down(): void
    {
        Schema::table('video_ads_master', function (Blueprint $table) {
            $table->dropColumn(['ad_checked', 'ad_checked_by', 'ad_checked_at']);
        });
    }
};
