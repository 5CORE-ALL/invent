<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ads_link_campaigns')) {
            return;
        }

        Schema::create('ads_link_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->string('sku_norm')->index();
            $table->string('campaign_name');
            $table->string('campaign_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['sku_norm', 'campaign_name']);
            $table->index('campaign_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_link_campaigns');
    }
};
