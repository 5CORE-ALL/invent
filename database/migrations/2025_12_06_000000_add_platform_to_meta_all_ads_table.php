<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('meta_all_ads', function (Blueprint $table) {
            $table->string('platform', 50)->nullable()->after('campaign_id')->comment('Facebook, Instagram, or Facebook/Instagram');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meta_all_ads', function (Blueprint $table) {
            $table->dropColumn('platform');
        });
    }
};
