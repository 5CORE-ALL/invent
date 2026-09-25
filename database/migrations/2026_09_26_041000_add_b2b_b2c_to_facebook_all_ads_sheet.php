<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2B / B2C tag on each Facebook sheet row, plus the saved dropdown options.
 * Built-in choices are B2B and B2C. Extra names live in facebook_b2b_b2c_options.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('facebook_all_ads_sheet') && ! Schema::hasColumn('facebook_all_ads_sheet', 'b2b_b2c')) {
            Schema::table('facebook_all_ads_sheet', function (Blueprint $table) {
                $table->string('b2b_b2c', 32)->nullable()->after('ch');
            });
        }

        if (! Schema::hasTable('facebook_b2b_b2c_options')) {
            Schema::create('facebook_b2b_b2c_options', function (Blueprint $table) {
                $table->id();
                $table->string('name', 32)->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_b2b_b2c_options');

        if (Schema::hasTable('facebook_all_ads_sheet') && Schema::hasColumn('facebook_all_ads_sheet', 'b2b_b2c')) {
            Schema::table('facebook_all_ads_sheet', function (Blueprint $table) {
                $table->dropColumn('b2b_b2c');
            });
        }
    }
};
