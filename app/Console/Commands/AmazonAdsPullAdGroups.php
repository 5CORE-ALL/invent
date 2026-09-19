<?php

namespace App\Console\Commands;

use App\Models\AmazonAdsAdGroup;
use App\Services\AmazonAdsService;
use App\Support\AmazonAdsAdGroupSync;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AmazonAdsPullAdGroups extends Command
{
    protected $signature = 'amazon:ads-pull-ad-groups {--prune : delete ad groups no longer returned by Amazon}';

    protected $description = 'Download SP + SB ad groups for each campaign into the Amazon Ads sheet';

    public function handle(AmazonAdsService $ads): int
    {
        $this->ensureTable();
        if (! Schema::hasTable('amazon_ads_ad_groups')) {
            $this->error('Could not create amazon_ads_ad_groups.');

            return 1;
        }

        $profileId = trim((string) $ads->resolvedProfileId()) ?: 'default';
        $names = AmazonAdsAdGroupSync::namesFromReports();

        $this->info('Listing live Amazon campaigns…');
        $spCampaigns = [];
        $sbCampaigns = [];
        try {
            $spCampaigns = $ads->listAllSpCampaigns(['ENABLED', 'PAUSED']);
        } catch (\Throwable $e) {
            $this->warn('SP campaign list failed: '.$e->getMessage());
        }
        try {
            $sbCampaigns = $ads->listAllSbCampaigns(['ENABLED', 'PAUSED']);
        } catch (\Throwable $e) {
            $this->warn('SB campaign list failed: '.$e->getMessage());
        }
        $names = array_merge($names, AmazonAdsAdGroupSync::namesFromCampaigns($spCampaigns));
        $names = array_merge($names, AmazonAdsAdGroupSync::namesFromCampaigns($sbCampaigns));

        $spIds = $this->campaignIds($spCampaigns);
        $sbIds = $this->campaignIds($sbCampaigns);
        $this->info('SP campaigns: '.count($spIds).'. SB campaigns: '.count($sbIds).'.');

        $this->info('Downloading SP ad groups…');
        $sp = $spIds === []
            ? $ads->fetchAllSpAdGroups(['ENABLED', 'PAUSED'])
            : $ads->fetchAllSpAdGroups(['ENABLED', 'PAUSED'], $spIds);
        $spUpserted = 0;
        $spSeen = [];
        if (empty($sp['success'])) {
            $this->warn($sp['message'] ?? 'Amazon SP ad-group pull failed.');
            Log::warning('amazon:ads-pull-ad-groups SP failed', ['message' => $sp['message'] ?? '']);
        } else {
            $profileId = trim((string) ($sp['profile_id'] ?? $profileId)) ?: $profileId;
            $rows = AmazonAdsAdGroupSync::rowsFromAmazon(
                $profileId,
                AmazonAdsAdGroup::AD_TYPE_SP,
                $sp['adGroups'] ?? [],
                $names
            );
            $spSeen = array_values(array_unique(array_column($rows, 'ad_group_id')));
            $spUpserted = AmazonAdsAdGroupSync::persist($rows);
            $this->info('Saved '.$spUpserted.' SP ad groups.');
        }

        $this->info('Downloading SB ad groups…');
        $sb = $sbIds === []
            ? $ads->fetchAllSbAdGroups(['ENABLED', 'PAUSED'])
            : $ads->fetchAllSbAdGroups(['ENABLED', 'PAUSED'], $sbIds);
        $sbUpserted = 0;
        $sbSeen = [];
        if (empty($sb['success'])) {
            $this->warn($sb['message'] ?? 'Amazon SB ad-group pull failed.');
            Log::warning('amazon:ads-pull-ad-groups SB failed', ['message' => $sb['message'] ?? '']);
        } else {
            $profileId = trim((string) ($sb['profile_id'] ?? $profileId)) ?: $profileId;
            $rows = AmazonAdsAdGroupSync::rowsFromAmazon(
                $profileId,
                AmazonAdsAdGroup::AD_TYPE_SB,
                $sb['adGroups'] ?? [],
                $names
            );
            $sbSeen = array_values(array_unique(array_column($rows, 'ad_group_id')));
            $sbUpserted = AmazonAdsAdGroupSync::persist($rows);
            $this->info('Saved '.$sbUpserted.' SB ad groups.');
        }

        $pruned = 0;
        if ((bool) $this->option('prune')) {
            if (! empty($sp['success'])) {
                $pruned += AmazonAdsAdGroupSync::prune($profileId, AmazonAdsAdGroup::AD_TYPE_SP, $spSeen);
            }
            if (! empty($sb['success'])) {
                $pruned += AmazonAdsAdGroupSync::prune($profileId, AmazonAdsAdGroup::AD_TYPE_SB, $sbSeen);
            }
            $this->info('Pruned stale ad groups: '.$pruned.'.');
        }

        Log::info('amazon:ads-pull-ad-groups finished', [
            'sp_upserted' => $spUpserted,
            'sb_upserted' => $sbUpserted,
            'sp_campaigns' => count($spIds),
            'sb_campaigns' => count($sbIds),
            'pruned' => $pruned,
        ]);

        if ($spUpserted === 0 && $sbUpserted === 0 && empty($sp['success']) && empty($sb['success'])) {
            $this->error('No ad groups were saved.');

            return 1;
        }

        $this->info("Done. SP {$spUpserted}. SB {$sbUpserted}.");

        return 0;
    }

    /**
     * @param  list<array<string, mixed>>  $campaigns
     * @return list<string>
     */
    private function campaignIds(array $campaigns): array
    {
        $ids = [];
        foreach ($campaigns as $campaign) {
            if (! is_array($campaign)) {
                continue;
            }
            $id = preg_replace('/\D+/', '', trim((string) ($campaign['campaignId'] ?? $campaign['campaign_id'] ?? ''))) ?: '';
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function ensureTable(): void
    {
        if (Schema::hasTable('amazon_ads_ad_groups')) {
            return;
        }
        Schema::create('amazon_ads_ad_groups', function (Blueprint $table) {
            $table->id();
            $table->string('profile_id');
            $table->string('ad_type', 32);
            $table->string('ad_group_id');
            $table->string('campaign_id')->nullable()->index();
            $table->string('campaignName')->nullable()->index();
            $table->string('adGroupName')->nullable()->index();
            $table->string('state', 32)->nullable()->index();
            $table->decimal('defaultBid', 10, 2)->nullable();
            $table->timestamp('pulled_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['profile_id', 'ad_type', 'ad_group_id'], 'amz_ads_ad_groups_profile_type_ag_unique');
            $table->index(['profile_id', 'ad_type'], 'amz_ads_ad_groups_profile_type_idx');
        });
    }
}
