<?php

namespace App\Console\Commands;

use App\Models\AmazonAdsCampaignSku;
use App\Services\AmazonAdsService;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PullAmazonSpProductAdsCommand extends Command
{
    protected $signature = 'amazon:ads-pull-product-ads';

    protected $description = 'Pull all SP product ads from Amazon into amazon_ads_campaign_skus (SKU per campaign)';

    public function handle(AmazonAdsService $ads): int
    {
        $this->ensureTable();
        if (! Schema::hasTable('amazon_ads_campaign_skus')) {
            $this->error('Could not create amazon_ads_campaign_skus.');

            return 1;
        }

        $this->info('Pulling SP product ads from Amazon into amazon_ads_campaign_skus…');
        $result = $ads->fetchAllProductAds(['ENABLED', 'PAUSED']);
        if (empty($result['success'])) {
            $this->error($result['message'] ?? 'Amazon product-ads pull failed.');

            return 1;
        }

        $rows = $result['ads'] ?? [];
        $profileId = trim((string) ($result['profile_id'] ?? '')) ?: 'default';
        $this->info('Fetched '.count($rows).' ads (profile '.$profileId.'). Saving…');

        $now = now();
        $payload = [];
        $skipped = 0;

        foreach ($rows as $ad) {
            if (! is_array($ad)) {
                $skipped++;
                continue;
            }
            $adId = preg_replace('/\D+/', '', trim((string) ($ad['adId'] ?? $ad['ad_id'] ?? ''))) ?: '';
            $campaignId = preg_replace('/\D+/', '', trim((string) ($ad['campaignId'] ?? $ad['campaign_id'] ?? ''))) ?: '';
            $sku = trim((string) ($ad['sku'] ?? ''));
            if ($adId === '' || $sku === '') {
                $skipped++;
                continue;
            }

            $payload[] = [
                'profile_id' => $profileId,
                'ad_id' => $adId,
                'campaign_id' => $campaignId !== '' ? $campaignId : null,
                'campaign_name' => null,
                'ad_group_id' => trim((string) ($ad['adGroupId'] ?? $ad['ad_group_id'] ?? '')) ?: null,
                'sku' => $sku,
                'asin' => strtoupper(trim((string) ($ad['asin'] ?? ''))) ?: null,
                'state' => strtoupper(trim((string) ($ad['state'] ?? ''))) ?: null,
                'pulled_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $upserted = 0;
        foreach (array_chunk($payload, 200) as $chunk) {
            AmazonAdsCampaignSku::upsert(
                $chunk,
                ['profile_id', 'ad_id'],
                ['campaign_id', 'ad_group_id', 'sku', 'asin', 'state', 'pulled_at', 'updated_at']
            );
            $upserted += count($chunk);
        }

        $named = $this->backfillCampaignNames();

        Log::info('amazon:ads-pull-product-ads finished', [
            'table' => 'amazon_ads_campaign_skus',
            'fetched' => count($rows),
            'upserted' => $upserted,
            'skipped' => $skipped,
            'names_filled' => $named,
        ]);

        $this->info("Saved {$upserted} into amazon_ads_campaign_skus. Skipped {$skipped}. Campaign names filled: {$named}.");

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

        return 0;
    }

    private function backfillCampaignNames(): int
    {
        if (! Schema::hasTable('amazon_sp_campaign_reports')) {
            return 0;
        }

        $cids = AmazonAdsCampaignSku::query()
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->distinct()
            ->pluck('campaign_id')
            ->all();
        if ($cids === []) {
            return 0;
        }

        $updated = 0;
        foreach (array_chunk($cids, 300) as $chunk) {
            $names = DB::table('amazon_sp_campaign_reports')
                ->whereIn('campaign_id', $chunk)
                ->orderByDesc('id')
                ->get(['campaign_id', 'campaignName']);
            $byCid = [];
            foreach ($names as $row) {
                $cid = (string) ($row->campaign_id ?? '');
                if ($cid === '' || isset($byCid[$cid])) {
                    continue;
                }
                $byCid[$cid] = trim((string) ($row->campaignName ?? ''));
            }
            foreach ($byCid as $cid => $name) {
                if ($name === '') {
                    continue;
                }
                $updated += AmazonAdsCampaignSku::query()
                    ->where('campaign_id', $cid)
                    ->where(function ($q) use ($name) {
                        $q->whereNull('campaign_name')->orWhere('campaign_name', '!=', $name);
                    })
                    ->update(['campaign_name' => $name]);
            }
        }

        return $updated;
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
