<?php

namespace App\Console\Commands;

use App\Services\AmazonAdsService;
use App\Support\AmazonAdsTargetCounts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Store Sponsored Brands / Display keyword and target counts.
 * The All grid reads these so SB campaigns (HEAD / HL) are not shown as M
 * just because they are absent from the SP targeting report.
 */
class AmazonAdsSyncTargetCounts extends Command
{
    protected $signature = 'app:amazon-ads-target-counts {--product=sb,sd : sb and/or sd}';

    protected $description = 'Sync SB/SD keyword and target counts used by the Targets column';

    public function handle(AmazonAdsService $ads): int
    {
        if (! Schema::hasTable(AmazonAdsTargetCounts::TABLE)) {
            $this->error('Table amazon_ads_target_counts is missing. Run migrations first.');

            return 1;
        }

        $products = array_values(array_filter(array_map(
            static fn ($p) => strtolower(trim((string) $p)),
            explode(',', (string) $this->option('product'))
        )));
        if ($products === []) {
            $products = ['sb', 'sd'];
        }

        $failed = 0;
        foreach ($products as $product) {
            $table = match ($product) {
                'sb' => 'amazon_sb_campaign_reports',
                'sd' => 'amazon_sd_campaign_reports',
                default => null,
            };
            if ($table === null || ! Schema::hasTable($table)) {
                $this->warn("Skip {$product}: no report table.");

                continue;
            }
            $ids = AmazonAdsTargetCounts::normalizeIds(
                DB::table($table)->whereNotNull('campaign_id')->distinct()->pluck('campaign_id')->all()
            );
            $this->info(strtoupper($product).' campaigns: '.count($ids));
            foreach (array_chunk($ids, 20) as $chunk) {
                if (! $this->syncTargets($ads, $product, $chunk)) {
                    $failed++;
                }
                if ($product === 'sb' && ! $this->syncSbNegatives($ads, $chunk)) {
                    $failed++;
                }
            }
        }

        return $failed > 0 ? 1 : 0;
    }

    /**
     * @param  list<string>  $campaignIds
     */
    private function syncTargets(AmazonAdsService $ads, string $product, array $campaignIds): bool
    {
        $counts = array_fill_keys($campaignIds, 0);
        $seen = [];
        $loaded = 0;

        $lists = match ($product) {
            'sb' => [
                fn () => $ads->listSbKeywordsByCampaignIds($campaignIds),
                fn () => $ads->listSbTargetsByCampaignIds($campaignIds),
            ],
            'sd' => [
                fn () => $ads->listSdTargetsByCampaignIds($campaignIds),
            ],
            default => [],
        };

        foreach ($lists as $load) {
            try {
                AmazonAdsTargetCounts::tally($load(), $counts, $seen);
                $loaded++;
            } catch (\Throwable $e) {
                $this->warn(strtoupper($product).' target list failed: '.$e->getMessage());
            }
        }

        if ($loaded > 0) {
            AmazonAdsTargetCounts::upsert($product, $counts, 'targets');
            $this->line(strtoupper($product).' stored '.count($counts).' target counts.');
        }

        return $loaded === count($lists);
    }

    /**
     * @param  list<string>  $campaignIds
     */
    private function syncSbNegatives(AmazonAdsService $ads, array $campaignIds): bool
    {
        $counts = array_fill_keys($campaignIds, 0);
        $seen = [];
        try {
            AmazonAdsTargetCounts::tally($ads->listSbNegativeKeywordsByCampaignIds($campaignIds), $counts, $seen);
        } catch (\Throwable $e) {
            $this->warn('SB negative keyword list failed: '.$e->getMessage());

            return false;
        }

        AmazonAdsTargetCounts::upsert('sb', $counts, 'n_targets');

        return true;
    }
}
