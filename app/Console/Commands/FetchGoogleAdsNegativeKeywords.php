<?php

namespace App\Console\Commands;

use App\Models\GoogleAdsNegativeKeyword;
use App\Services\GoogleAdsSbidService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Fetch Google Ads negative keywords at every level and store them in
 * google_ads_negative_keywords:
 *   - CAMPAIGN   : campaign_criterion (negative = TRUE, type = KEYWORD)
 *   - AD_GROUP   : ad_group_criterion (negative = TRUE, type = KEYWORD)
 *   - SHARED_SET : shared_criterion rows of NEGATIVE_KEYWORDS shared sets
 *
 * Rows are upserted in batches keyed by criterion_resource_name (globally unique
 * per criterion). With --prune, rows no longer present in the API for the levels
 * that were fetched are deleted so removed negatives don't linger.
 */
class FetchGoogleAdsNegativeKeywords extends Command
{
    protected $signature = 'app:fetch-google-ads-negative-keywords
        {--level=all : Which levels to fetch: all|campaign|ad_group|shared_set}
        {--prune : Delete stored rows for the fetched levels that are no longer returned by the API}';

    protected $description = 'Fetch Google Ads negative keywords (campaign, ad group, and shared negative keyword lists) and store them';

    /**
     * Columns updated on an upsert conflict (everything except the unique key + timestamps
     * Laravel manages automatically).
     *
     * @var list<string>
     */
    private const UPSERT_COLUMNS = [
        'level',
        'customer_id',
        'campaign_id',
        'campaign_name',
        'ad_group_id',
        'ad_group_name',
        'shared_set_id',
        'shared_set_name',
        'criterion_id',
        'keyword_text',
        'match_type',
        'criterion_type',
        'status',
    ];

    private const BATCH_SIZE = 500;

    protected GoogleAdsSbidService $googleAdsService;

    public function __construct(GoogleAdsSbidService $googleAdsService)
    {
        parent::__construct();
        $this->googleAdsService = $googleAdsService;
    }

    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');

        $customerId = config('services.google_ads.login_customer_id');
        if (empty($customerId)) {
            $this->error('Google Ads Customer ID is not configured');
            Log::error('Google Ads Customer ID is not configured (negative keywords fetch)');

            return 1;
        }
        $customerId = str_replace('-', '', $customerId);

        $level = strtolower((string) $this->option('level'));
        $prune = (bool) $this->option('prune');
        $levels = $this->resolveLevels($level);
        if ($levels === []) {
            $this->error("Invalid --level '{$level}'. Use one of: all, campaign, ad_group, shared_set");

            return 1;
        }

        $this->info('Fetching Google Ads negative keywords for levels: '.implode(', ', $levels));

        $seenResourceNames = [];
        $totals = ['upserted' => 0, 'errors' => 0];

        try {
            if (in_array(GoogleAdsNegativeKeyword::LEVEL_CAMPAIGN, $levels, true)) {
                $this->fetchCampaignNegatives($customerId, $seenResourceNames, $totals);
            }
            if (in_array(GoogleAdsNegativeKeyword::LEVEL_AD_GROUP, $levels, true)) {
                $this->fetchAdGroupNegatives($customerId, $seenResourceNames, $totals);
            }
            if (in_array(GoogleAdsNegativeKeyword::LEVEL_SHARED_SET, $levels, true)) {
                $this->fetchSharedSetNegatives($customerId, $seenResourceNames, $totals);
            }
        } catch (\Throwable $e) {
            $this->error('Error fetching negative keywords: '.$e->getMessage());
            Log::error('Error fetching Google Ads negative keywords: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }

        $this->info("Upserted {$totals['upserted']} negative keyword(s). Errors: {$totals['errors']}.");

        if ($prune) {
            $deleted = $this->pruneStale($levels, $seenResourceNames);
            $this->info("Pruned {$deleted} stale negative keyword row(s).");
        }

        Log::info('Google Ads negative keywords fetch completed', [
            'levels' => $levels,
            'upserted' => $totals['upserted'],
            'errors' => $totals['errors'],
        ]);

        return 0;
    }

    /**
     * @return list<string>
     */
    private function resolveLevels(string $level): array
    {
        return match ($level) {
            '', 'all' => [
                GoogleAdsNegativeKeyword::LEVEL_CAMPAIGN,
                GoogleAdsNegativeKeyword::LEVEL_AD_GROUP,
                GoogleAdsNegativeKeyword::LEVEL_SHARED_SET,
            ],
            'campaign' => [GoogleAdsNegativeKeyword::LEVEL_CAMPAIGN],
            'ad_group', 'adgroup' => [GoogleAdsNegativeKeyword::LEVEL_AD_GROUP],
            'shared_set', 'sharedset', 'list' => [GoogleAdsNegativeKeyword::LEVEL_SHARED_SET],
            default => [],
        };
    }

    /**
     * @param  array<string, true>  $seen
     * @param  array{upserted: int, errors: int}  $totals
     */
    private function fetchCampaignNegatives(string $customerId, array &$seen, array &$totals): void
    {
        $query = "
            SELECT
                campaign.id,
                campaign.name,
                campaign_criterion.criterion_id,
                campaign_criterion.resource_name,
                campaign_criterion.type,
                campaign_criterion.status,
                campaign_criterion.negative,
                campaign_criterion.keyword.text,
                campaign_criterion.keyword.match_type
            FROM campaign_criterion
            WHERE campaign_criterion.type = 'KEYWORD'
              AND campaign_criterion.negative = TRUE
        ";

        $batch = [];
        $count = 0;
        $this->googleAdsService->streamQuery($customerId, $query, function (array $row) use ($customerId, &$batch, &$count, &$seen, &$totals) {
            $campaign = $row['campaign'] ?? [];
            $criterion = $row['campaignCriterion'] ?? [];
            $keyword = $criterion['keyword'] ?? [];
            $resource = $criterion['resourceName'] ?? null;
            if (empty($resource)) {
                return;
            }

            $batch[] = [
                'level' => GoogleAdsNegativeKeyword::LEVEL_CAMPAIGN,
                'customer_id' => $customerId,
                'campaign_id' => (string) ($campaign['id'] ?? ''),
                'campaign_name' => $campaign['name'] ?? null,
                'ad_group_id' => null,
                'ad_group_name' => null,
                'shared_set_id' => null,
                'shared_set_name' => null,
                'criterion_id' => isset($criterion['criterionId']) ? (string) $criterion['criterionId'] : null,
                'criterion_resource_name' => $resource,
                'keyword_text' => $keyword['text'] ?? null,
                'match_type' => $keyword['matchType'] ?? null,
                'criterion_type' => $criterion['type'] ?? 'KEYWORD',
                'status' => $criterion['status'] ?? null,
            ];
            $count++;
            $this->flushIfFull($batch, $seen, $totals);
        });
        $this->flush($batch, $seen, $totals);
        $this->info("  Campaign-level negatives: {$count}");
    }

    /**
     * @param  array<string, true>  $seen
     * @param  array{upserted: int, errors: int}  $totals
     */
    private function fetchAdGroupNegatives(string $customerId, array &$seen, array &$totals): void
    {
        $query = "
            SELECT
                campaign.id,
                campaign.name,
                ad_group.id,
                ad_group.name,
                ad_group_criterion.criterion_id,
                ad_group_criterion.resource_name,
                ad_group_criterion.type,
                ad_group_criterion.status,
                ad_group_criterion.negative,
                ad_group_criterion.keyword.text,
                ad_group_criterion.keyword.match_type
            FROM ad_group_criterion
            WHERE ad_group_criterion.type = 'KEYWORD'
              AND ad_group_criterion.negative = TRUE
        ";

        $batch = [];
        $count = 0;
        $this->googleAdsService->streamQuery($customerId, $query, function (array $row) use ($customerId, &$batch, &$count, &$seen, &$totals) {
            $campaign = $row['campaign'] ?? [];
            $adGroup = $row['adGroup'] ?? [];
            $criterion = $row['adGroupCriterion'] ?? [];
            $keyword = $criterion['keyword'] ?? [];
            $resource = $criterion['resourceName'] ?? null;
            if (empty($resource)) {
                return;
            }

            $batch[] = [
                'level' => GoogleAdsNegativeKeyword::LEVEL_AD_GROUP,
                'customer_id' => $customerId,
                'campaign_id' => (string) ($campaign['id'] ?? ''),
                'campaign_name' => $campaign['name'] ?? null,
                'ad_group_id' => (string) ($adGroup['id'] ?? ''),
                'ad_group_name' => $adGroup['name'] ?? null,
                'shared_set_id' => null,
                'shared_set_name' => null,
                'criterion_id' => isset($criterion['criterionId']) ? (string) $criterion['criterionId'] : null,
                'criterion_resource_name' => $resource,
                'keyword_text' => $keyword['text'] ?? null,
                'match_type' => $keyword['matchType'] ?? null,
                'criterion_type' => $criterion['type'] ?? 'KEYWORD',
                'status' => $criterion['status'] ?? null,
            ];
            $count++;
            $this->flushIfFull($batch, $seen, $totals);
        });
        $this->flush($batch, $seen, $totals);
        $this->info("  Ad group-level negatives: {$count}");
    }

    /**
     * Negative keyword lists (shared sets). Each keyword lives in a shared_criterion
     * row and is stored once regardless of how many campaigns reference the list.
     *
     * @param  array<string, true>  $seen
     * @param  array{upserted: int, errors: int}  $totals
     */
    private function fetchSharedSetNegatives(string $customerId, array &$seen, array &$totals): void
    {
        $query = "
            SELECT
                shared_set.id,
                shared_set.name,
                shared_criterion.criterion_id,
                shared_criterion.resource_name,
                shared_criterion.type,
                shared_criterion.keyword.text,
                shared_criterion.keyword.match_type
            FROM shared_criterion
            WHERE shared_set.type = 'NEGATIVE_KEYWORDS'
              AND shared_set.status != 'REMOVED'
              AND shared_criterion.type = 'KEYWORD'
        ";

        $batch = [];
        $count = 0;
        $this->googleAdsService->streamQuery($customerId, $query, function (array $row) use ($customerId, &$batch, &$count, &$seen, &$totals) {
            $sharedSet = $row['sharedSet'] ?? [];
            $criterion = $row['sharedCriterion'] ?? [];
            $keyword = $criterion['keyword'] ?? [];
            $resource = $criterion['resourceName'] ?? null;
            if (empty($resource)) {
                return;
            }

            $batch[] = [
                'level' => GoogleAdsNegativeKeyword::LEVEL_SHARED_SET,
                'customer_id' => $customerId,
                'campaign_id' => null,
                'campaign_name' => null,
                'ad_group_id' => null,
                'ad_group_name' => null,
                'shared_set_id' => (string) ($sharedSet['id'] ?? ''),
                'shared_set_name' => $sharedSet['name'] ?? null,
                'criterion_id' => isset($criterion['criterionId']) ? (string) $criterion['criterionId'] : null,
                'criterion_resource_name' => $resource,
                'keyword_text' => $keyword['text'] ?? null,
                'match_type' => $keyword['matchType'] ?? null,
                'criterion_type' => $criterion['type'] ?? 'KEYWORD',
                'status' => null,
            ];
            $count++;
            $this->flushIfFull($batch, $seen, $totals);
        });
        $this->flush($batch, $seen, $totals);
        $this->info("  Shared-list negatives: {$count}");
    }

    /**
     * Flush the batch when it reaches BATCH_SIZE, resetting it in place.
     *
     * @param  list<array<string, mixed>>  $batch
     * @param  array<string, true>  $seen
     * @param  array{upserted: int, errors: int}  $totals
     */
    private function flushIfFull(array &$batch, array &$seen, array &$totals): void
    {
        if (count($batch) >= self::BATCH_SIZE) {
            $this->flush($batch, $seen, $totals);
        }
    }

    /**
     * Batch upsert the accumulated rows by criterion_resource_name and reset the batch.
     *
     * @param  list<array<string, mixed>>  $batch
     * @param  array<string, true>  $seen
     * @param  array{upserted: int, errors: int}  $totals
     */
    private function flush(array &$batch, array &$seen, array &$totals): void
    {
        if ($batch === []) {
            return;
        }

        try {
            GoogleAdsNegativeKeyword::upsert($batch, ['criterion_resource_name'], self::UPSERT_COLUMNS);
            $totals['upserted'] += count($batch);
        } catch (\Throwable $e) {
            $totals['errors'] += count($batch);
            Log::error('Failed to upsert negative keyword batch', [
                'batch_size' => count($batch),
                'error' => $e->getMessage(),
            ]);
        }

        foreach ($batch as $r) {
            $seen[$r['criterion_resource_name']] = true;
        }

        $batch = [];
    }

    /**
     * Delete stored rows for the fetched levels whose criterion is no longer returned.
     *
     * @param  list<string>  $levels
     * @param  array<string, true>  $seen
     */
    private function pruneStale(array $levels, array $seen): int
    {
        $deleted = 0;
        GoogleAdsNegativeKeyword::whereIn('level', $levels)
            ->select('id', 'criterion_resource_name')
            ->chunkById(1000, function ($rows) use ($seen, &$deleted) {
                $staleIds = [];
                foreach ($rows as $row) {
                    if (! isset($seen[$row->criterion_resource_name])) {
                        $staleIds[] = $row->id;
                    }
                }
                if ($staleIds !== []) {
                    $deleted += GoogleAdsNegativeKeyword::whereIn('id', $staleIds)->delete();
                }
            });

        return $deleted;
    }
}
