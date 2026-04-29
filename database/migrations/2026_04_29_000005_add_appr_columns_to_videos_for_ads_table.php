<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos_for_ads', function (Blueprint $table) {
            $table->tinyInteger('appr_s')->default(0)->after('ads_status');
            $table->tinyInteger('appr_i')->default(0)->after('appr_s');
            $table->tinyInteger('appr_n')->default(0)->after('appr_i');
        });
    }

    public function down(): void
    {
        Schema::table('videos_for_ads', function (Blueprint $table) {
            $table->dropColumn(['appr_s', 'appr_i', 'appr_n']);
        });
    }
};
