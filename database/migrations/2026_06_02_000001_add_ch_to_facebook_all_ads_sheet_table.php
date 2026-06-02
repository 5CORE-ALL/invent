<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a per-row `ch` (channel) column on the Meta Ads All sheet page.
 * Holds the channel the campaign runs on — "FB" or "Insta" — picked from
 * the CH dropdown. Kept a plain VARCHAR (like `ad_type`) so the option
 * list can grow later without another migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_all_ads_sheet', function (Blueprint $table) {
            $table->string('ch', 16)->nullable()->after('ad_type');
        });
    }

    public function down(): void
    {
        Schema::table('facebook_all_ads_sheet', function (Blueprint $table) {
            $table->dropColumn('ch');
        });
    }
};
