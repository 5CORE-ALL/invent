<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TikTok Marketing API (Ads Manager) data.
     *
     * Separate from tiktok_campaign_reports (manual Seller Center uploads) so the API sync can
     * never overwrite the uploaded L1/L7/L30 data the existing pages read.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tiktok_ads_connections')) {
            Schema::create('tiktok_ads_connections', function (Blueprint $table) {
                $table->id();
                $table->string('advertiser_id', 32)->unique();
                $table->string('advertiser_name')->nullable();
                $table->string('currency', 8)->nullable();
                $table->text('access_token'); // stored encrypted (model cast)
                $table->json('scope')->nullable();
                $table->timestamp('authorized_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tiktok_ads_daily_reports')) {
            Schema::create('tiktok_ads_daily_reports', function (Blueprint $table) {
                $table->id();
                $table->string('advertiser_id', 32);
                $table->string('campaign_id', 32);
                $table->string('campaign_name')->nullable();
                $table->date('stat_date');
                $table->decimal('spend', 14, 2)->default(0);
                $table->unsignedBigInteger('impressions')->default(0);
                $table->unsignedBigInteger('clicks')->default(0);
                $table->decimal('ctr', 10, 4)->nullable();
                $table->decimal('cpc', 12, 4)->nullable();
                $table->decimal('cpm', 12, 4)->nullable();
                $table->unsignedBigInteger('conversions')->default(0);
                $table->decimal('cost_per_conversion', 14, 4)->nullable();
                $table->decimal('conversion_rate', 10, 4)->nullable();
                $table->json('raw')->nullable();
                $table->timestamps();

                $table->unique(['advertiser_id', 'campaign_id', 'stat_date'], 'tiktok_ads_daily_unique');
                $table->index('stat_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_ads_daily_reports');
        Schema::dropIfExists('tiktok_ads_connections');
    }
};
