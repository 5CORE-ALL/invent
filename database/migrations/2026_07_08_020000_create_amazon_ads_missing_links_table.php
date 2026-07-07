<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amazon_ads_missing_links', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->string('type', 8);              // 'PT' | 'KW'
            $table->string('campaign_id')->nullable();
            $table->string('campaign_name');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['sku', 'type']);
            $table->unique(['sku', 'type', 'campaign_name'], 'amz_missing_link_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_ads_missing_links');
    }
};
