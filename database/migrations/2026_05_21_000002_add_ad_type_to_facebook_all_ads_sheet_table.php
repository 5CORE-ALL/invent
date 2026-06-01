<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a per-row `ad_type` enum-ish column on the Facebook All Ads Sheet
 * page. The 4 allowed values (GROUP VIDEO / GROUP CAROUSAL / PARENT VIDEO
 * / PARENT CAROUSAL) are hardcoded on the frontend dropdown — we keep this
 * a plain VARCHAR so the user can extend the list later without another
 * migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_all_ads_sheet', function (Blueprint $table) {
            $table->string('ad_type', 32)->nullable()->after('row_data');
        });
    }

    public function down(): void
    {
        Schema::table('facebook_all_ads_sheet', function (Blueprint $table) {
            $table->dropColumn('ad_type');
        });
    }
};
