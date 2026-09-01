<?php

namespace App\Console\Commands;

use App\Services\AmazonAdsService;
use App\Support\AmazonAdsCampaignSkuSync;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PullAmazonSpProductAdsCommand extends Command
{
    protected $signature = 'amazon:ads-pull-product-ads';

    protected $description = 'Pull SP product ads + SB ads into amazon_ads_campaign_skus; fill remaining campaigns from campaign names';

    public function handle(AmazonAdsService $ads): int
    {
        $this->ensureTable();
        if (! Schema::hasTable('amazon_ads_campaign_skus')) {
            $this->error('Could not create amazon_ads_campaign_skus.');

            return 1;
        }

        $profileId = trim((string) $ads->resolvedProfileId()) ?: 'default';
        $spUpserted = 0;
        $sbUpserted = 0;
        $spFailed = false;

        $this->info('Pulling SP product ads from Amazon into amazon_ads_campaign_skus…');
        $sp = $ads->fetchAllProductAds(['ENABLED', 'PAUSED']);
        if (empty($sp['success'])) {
            $this->warn($sp['message'] ?? 'Amazon SP product-ads pull failed.');
            $spFailed = true;
            Log::warning('amazon:ads-pull-product-ads SP failed', ['message' => $sp['message'] ?? '']);
        } else {
            $rows = $sp['ads'] ?? [];
            $profileId = trim((string) ($sp['profile_id'] ?? $profileId)) ?: $profileId;
            $this->info('Fetched '.count($rows).' SP ads (profile '.$profileId.'). Saving…');
            $spUpserted = AmazonAdsCampaignSkuSync::persistSpAds($profileId, $rows);
        }

        $this->info('Pulling SB ads from Amazon…');
        $sb = $ads->fetchAllSbAds(['ENABLED', 'PAUSED']);
        if (empty($sb['success'])) {
            $this->warn($sb['message'] ?? 'Amazon SB ads pull failed (SB campaigns will use campaign-name SKUs).');
            Log::warning('amazon:ads-pull-product-ads SB failed', ['message' => $sb['message'] ?? '']);
        } else {
            $sbRows = $sb['ads'] ?? [];
            $this->info('Fetched '.count($sbRows).' SB ads. Saving mapped SKUs…');
            $sbUpserted = AmazonAdsCampaignSkuSync::persistSbAds($profileId, $sbRows);
        }

        $dropped = AmazonAdsCampaignSkuSync::dropNameDerivedWhereRealAdsExist();
        $named = AmazonAdsCampaignSkuSync::backfillCampaignNames();
        $this->info('Filling campaigns with no product-ad SKUs from campaign names…');
        $nameFilled = AmazonAdsCampaignSkuSync::backfillMissingFromCampaignNames($profileId);

        Log::info('amazon:ads-pull-product-ads finished', [
            'table' => 'amazon_ads_campaign_skus',
            'sp_upserted' => $spUpserted,
            'sb_upserted' => $sbUpserted,
            'name_filled' => $nameFilled,
            'names_filled' => $named,
            'name_rows_dropped' => $dropped,
            'sp_failed' => $spFailed,
        ]);

        $this->info("SP {$spUpserted}. SB {$sbUpserted}. Name-derived {$nameFilled}. Campaign names filled: {$named}.");

        $multi = (int) DB::table('amazon_ads_campaign_skus')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where(function ($q) {
                $q->whereNull('state')->orWhereRaw('UPPER(TRIM(state)) != ?', ['ARCHIVED']);
            })
            ->selectRaw('campaign_id, COUNT(DISTINCT sku) AS sku_cnt')
            ->groupBy('campaign_id')
            ->havingRaw('COUNT(DISTINCT sku) > 1')
            ->get()
            ->count();
        $this->info("Campaigns with more than one SKU: {$multi}.");

        if ($spFailed && $spUpserted === 0 && $sbUpserted === 0 && $nameFilled === 0) {
            $this->error('No SKUs were saved.');

            return 1;
        }

        return 0;
    }

    private function ensureTable(): void
    {
        if (Schema::hasTable('amazon_ads_campaign_skus')) {
            return;
        }
        Schema::create('amazon_ads_campaign_skus', function (Blueprint $table) {
            $table->id();
            $table->string('profile_id');
            $table->string('ad_id');
            $table->string('campaign_id')->nullable()->index();
            $table->string('campaign_name')->nullable()->index();
            $table->string('ad_group_id')->nullable();
            $table->string('sku', 255)->nullable()->index();
            $table->string('asin', 32)->nullable()->index();
            $table->string('state', 32)->nullable()->index();
            $table->timestamp('pulled_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['profile_id', 'ad_id'], 'amz_ads_campaign_skus_profile_ad_unique');
            $table->index(['campaign_id', 'sku'], 'amz_ads_campaign_skus_cid_sku');
        });
    }
}
