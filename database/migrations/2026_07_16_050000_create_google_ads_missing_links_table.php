<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_ads_missing_links')) {
            return;
        }

        Schema::create('google_ads_missing_links', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16); // shopping | serp
            $table->string('sku');         // PARENT {parent}
            $table->string('campaign_id')->nullable();
            $table->string('campaign_name');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['channel', 'sku']);
            $table->unique(['channel', 'sku', 'campaign_name'], 'google_ads_missing_link_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_missing_links');
    }
};
