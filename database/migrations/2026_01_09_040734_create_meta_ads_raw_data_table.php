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
        Schema::create('meta_ads_raw_data', function (Blueprint $table) {
            $table->id();
            $table->string('ad_id', 191)->comment('Facebook Ad ID');
            $table->string('ad_name')->nullable();
            $table->string('campaign_id', 191)->nullable()->index();
            $table->string('campaign_name')->nullable();
            $table->string('adset_id', 191)->nullable();
            $table->string('status', 50)->nullable();
            $table->text('effective_object_story_id')->nullable();
            $table->text('preview_shareable_link')->nullable();
            $table->string('source_ad_id', 191)->nullable();
            $table->json('creative_data')->nullable()->comment('Creative object data from API');
            $table->date('sync_date')->index()->comment('Date when data was synced');
            $table->timestamp('ad_created_time')->nullable();
            $table->timestamp('ad_updated_time')->nullable();
            $table->json('raw_data')->nullable()->comment('Complete raw response from API');
            $table->timestamps();
            
            // Unique constraint: one record per ad per day
            $table->unique(['ad_id', 'sync_date'], 'ad_id_sync_date_unique');
            $table->index(['campaign_id', 'sync_date']);
            $table->index(['sync_date', 'status']);
            $table->index('ad_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_ads_raw_data');
    }
};
