<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ebay_campaign_ads', function (Blueprint $table) {
            $table->string('campaign_id')->nullable()->change(); // allow null for non-campaign listings
        });
    }

    public function down(): void
    {
        Schema::table('ebay_campaign_ads', function (Blueprint $table) {
            $table->string('campaign_id')->nullable(false)->change();
        });
    }
};
