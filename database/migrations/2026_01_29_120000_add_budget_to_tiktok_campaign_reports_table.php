<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiktok_campaign_reports', function (Blueprint $table) {
            $table->decimal('budget', 12, 2)->nullable()->after('cost');
        });
    }

    public function down(): void
    {
        Schema::table('tiktok_campaign_reports', function (Blueprint $table) {
            $table->dropColumn('budget');
        });
    }
};
