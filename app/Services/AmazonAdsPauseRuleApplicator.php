<?php

namespace App\Services;

use App\Models\AmazonAdsCampaignSku;
use App\Models\AmazonSbCampaignReport;
use App\Models\AmazonSpCampaignReport;
use App\Services\FbaInventoryService;
use App\Support\AmazonAcosSbgtRule;
use App\Support\AmazonAdsCampaignSkuMetrics;
use App\Support\AmazonAdsCampaignSkuSync;
use App\Support\AmazonAdsPauseRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluate the Amazon Ads pause rule against latest SP/SB campaigns and push
 * ENABLED / PAUSED to Amazon. Campaigns stay ENABLED unless a Pause band matches.
 */
class AmazonAdsPauseRuleApplicator
{
    public function __construct(
        private readonly AmazonAdsService $ads
    ) {}

    /**
     * @return array{
     *     paused: int,
     *     enabled: int,
     *     unchanged: int,
     *     skipped: int,
     *     failed: int,
     *     errors: list<string>
     * }
     */
    public function applyAll(bool $dryRun = false): array
    {
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '512M');

        $stats = [
            'paused' => 0,
            'enabled' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $rule = AmazonAdsPauseRule::resolvedRule();
        $hasCampaign = AmazonAdsPauseRule::hasCampaignBands($rule);
        $hasReviews = AmazonAdsPauseRule::reviewsEnabled($rule);
        if (! $hasCampaign && ! $hasReviews) {
            $stats['errors'][] = 'No pause/activate bands, PR Dil%, or Reviews rule configured — Amazon was not updated.';
            Log::warning('amazon:ads-pause-rule skipped: empty pause rule (no bands / PR off / Reviews off).');

            return $stats;
        }
        if ($hasCampaign) {
            $sp = $this->collectLatestCampaigns('sp');
            $sb = $this->collectLatestCampaigns('sb');
            $names = array_values(array_unique(array_filter(array_merge(
                array_column($sp, 'campaignName'),
                array_column($sb, 'campaignName')
            ), static fn ($n) => is_string($n) && trim($n) !== '')));
            $metricsByName = AmazonAdsCampaignSkuMetrics::mapForCampaignNames($names);
            $ratingsByCid = AmazonAdsCampaignSkuMetrics::minRatingForCampaignIds(array_merge(
                array_column($sp, 'campaign_id'),
                array_column($sb, 'campaign_id')
            ));

            $this->applyChannel('sp', $sp, $rule, $metricsByName, $ratingsByCid, $dryRun, $stats);
            $this->applyChannel('sb', $sb, $rule, $metricsByName, $ratingsByCid, $dryRun, $stats);
        }
        if ($hasReviews) {
            $adStats = $this->applyReviewsProductAds($dryRun);
            $stats['paused'] += (int) ($adStats['paused'] ?? 0);
            $stats['enabled'] += (int) ($adStats['enabled'] ?? 0);
            $stats['unchanged'] += (int) ($adStats['unchanged'] ?? 0);
            $stats['skipped'] += (int) ($adStats['skipped'] ?? 0);
            $stats['failed'] += (int) ($adStats['failed'] ?? 0);
            foreach ($adStats['errors'] ?? [] as $err) {
                $stats['errors'][] = $err;
            }
            $stats['ads_paused'] = (int) ($adStats['paused'] ?? 0);
        }

        return $stats;
    }

    /**
     * Pause SP or SB campaigns (e.g. grid SBGT is 0 and cannot be pushed as daily budget).
     *
     * @param  'sp'|'sb'  $channel
     * @param  list<string>  $campaignIds
     * @return array{paused: int, enabled: int, unchanged: int, skipped: int, failed: int, errors: list<string>}
     */
    public function pauseCampaigns(string $channel, array $campaignIds): array
    {
        $stats = [
            'paused' => 0,
            'enabled' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => trim((string) $id), $campaignIds),
            static fn (string $id): bool => $id !== ''
        )));
        if ($ids === [] || ! in_array($channel, ['sp', 'sb'], true)) {
            return $stats;
        }
        $this->pushState($channel, $ids, AmazonAdsPauseRule::ACTION_PAUSED, $stats);

        return $stats;
    }

    /**
     * Pause product ads (SP) / SB ads whose SKU rating is below the Reviews threshold.
     * Campaigns stay ENABLED.
     *
     * @return array{paused: int, enabled: int, unchanged: int, skipped: int, failed: int, errors: list<string>, scope: string}
     */
    public function applyReviewsProductAds(bool $dryRun = false): array
    {
        $stats = [
            'paused' => 0,
            'enabled' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
            'scope' => 'product_ads',
        ];
        $rule = AmazonAdsPauseRule::resolvedRule();
        if (! AmazonAdsPauseRule::reviewsEnabled($rule)) {
            $stats['errors'][] = 'Reviews rule is off — product ads were not updated.';

            return $stats;
        }
        if (! Schema::hasTable('amazon_ads_campaign_skus')) {
            $stats['errors'][] = 'Campaign SKU table is missing. Run amazon:ads-pull-product-ads.';

            return $stats;
        }

        $rows = AmazonAdsCampaignSku::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull('ad_id')
            ->where('ad_id', '!=', '')
            ->where('ad_id', 'not like', AmazonAdsCampaignSkuSync::NAME_AD_PREFIX.'%')
            ->get(['ad_id', 'campaign_id', 'sku', 'state']);

        $reviews = AmazonAdsCampaignSkuMetrics::reviewsBySkus($rows->pluck('sku')->all());
        $spPause = [];
        $sbPause = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            $key = strtoupper(trim(str_replace("\xC2\xA0", ' ', $sku)));
            $hit = $reviews[$key] ?? null;
            $rating = is_array($hit) && $hit['rating'] !== null ? (float) $hit['rating'] : null;
            if (! AmazonAdsPauseRule::ratingBelowReviewsThreshold($rule, $rating)) {
                if ($rating === null) {
                    $stats['skipped']++;
                } else {
                    $stats['unchanged']++;
                }
                continue;
            }
            $ref = AmazonAdsCampaignSkuSync::amazonAdRef((string) ($row->ad_id ?? ''));
            if ($ref['channel'] === null || $ref['ad_id'] === '') {
                $stats['skipped']++;
                continue;
            }
            $state = strtoupper(trim((string) ($row->state ?? '')));
            if ($state === AmazonAdsPauseRule::ACTION_PAUSED) {
                $stats['unchanged']++;
                continue;
            }
            if ($state === 'ARCHIVED') {
                $stats['skipped']++;
                continue;
            }
            if ($ref['channel'] === 'sb') {
                $sbPause[$ref['ad_id']] = true;
            } else {
                $spPause[$ref['ad_id']] = true;
            }
        }

        $spIds = array_keys($spPause);
        $sbIds = array_keys($sbPause);
        if ($dryRun) {
            $stats['paused'] = count($spIds) + count($sbIds);

            return $stats;
        }

        $this->pushAdState('sp', $spIds, AmazonAdsPauseRule::ACTION_PAUSED, $stats);
        $this->pushAdState('sb', $sbIds, AmazonAdsPauseRule::ACTION_PAUSED, $stats);

        return $stats;
    }

    /**
     * @param  'sp'|'sb'  $channel
     * @param  list<string>  $adIds
     * @param  array{paused: int, enabled: int, unchanged: int, skipped: int, failed: int, errors: list<string>}  $stats
     */
    private function pushAdState(string $channel, array $adIds, string $state, array &$stats): void
    {
        $adIds = array_values(array_unique(array_filter($adIds, static fn ($id) => trim((string) $id) !== '')));
        if ($adIds === []) {
            return;
        }
        foreach (array_chunk($adIds, 10) as $chunk) {
            $this->pushAdStateChunk($channel, $chunk, $state, $stats, true);
        }
    }

    /**
     * @param  'sp'|'sb'  $channel
     * @param  list<string>  $chunk
     * @param  array{paused: int, enabled: int, unchanged: int, skipped: int, failed: int, errors: list<string>}  $stats
     */
    private function pushAdStateChunk(string $channel, array $chunk, string $state, array &$stats, bool $retrySingles): void
    {
        try {
            $payload = [];
            foreach ($chunk as $id) {
                $payload[] = [
                    'adId' => (string) $id,
                    'state' => $state,
                ];
            }
            $result = $channel === 'sb'
                ? $this->ads->updateSbAds($payload)
                : $this->ads->updateProductAds($payload);
            $this->recordAdPushResult($channel, $chunk, $state, $result, $stats);
        } catch (\Throwable $e) {
            if ($retrySingles && count($chunk) > 1) {
                foreach ($chunk as $id) {
                    $this->pushAdStateChunk($channel, [$id], $state, $stats, false);
                }

                return;
            }
            $decoded = self::decodeThrownAmazonBody($e);
            if (is_array($decoded)) {
                $this->recordAdPushResult($channel, $chunk, $state, $decoded, $stats);

                return;
            }
            $stats['failed'] += count($chunk);
            $stats['errors'][] = self::shortAmazonError($e->getMessage());
            Log::error('Amazon Ads reviews product-ad push failed', [
                'channel' => $channel,
                'state' => $state,
                'count' => count($chunk),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  'sp'|'sb'  $channel
     * @param  list<string>  $chunk
     * @param  array<string, mixed>  $result
     * @param  array{paused: int, enabled: int, unchanged: int, skipped: int, failed: int, errors: list<string>}  $stats
     */
    private function recordAdPushResult(string $channel, array $chunk, string $state, array $result, array &$stats): void
    {
        $blockKey = $channel === 'sb' ? 'ads' : 'productAds';
        $block = is_array($result[$blockKey] ?? null) ? $result[$blockKey] : $result;
        $success = is_array($block['success'] ?? null) ? $block['success'] : [];
        $errors = is_array($block['error'] ?? null) ? $block['error'] : (is_array($block['errors'] ?? null) ? $block['errors'] : []);
        $ok = [];
        foreach ($success as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (isset($row['adId']) && (string) $row['adId'] !== '') {
                $ok[] = (string) $row['adId'];
            } elseif (isset($row['index']) && isset($chunk[(int) $row['index']])) {
                $ok[] = (string) $chunk[(int) $row['index']];
            }
        }
        $failMsgs = [];
        foreach ($errors as $row) {
            if (! is_array($row)) {
                if (is_string($row) && $row !== '') {
                    $failMsgs[] = $row;
                }
                continue;
            }
            $aid = trim((string) ($row['adId'] ?? ''));
            if ($aid === '' && isset($row['index']) && isset($chunk[(int) $row['index']])) {
                $aid = (string) $chunk[(int) $row['index']];
            }
            $err = data_get($row, 'errors.0.errorValue.message')
                ?? data_get($row, 'errors.0.message')
                ?? data_get($row, 'errorValue.message')
                ?? ($row['message'] ?? null);
            $err = is_string($err) && $err !== '' ? $err : 'Amazon rejected product ad';
            if (self::isAlreadyInDesiredStateMessage($err, $state)) {
                $ok[] = $aid !== '' ? $aid : ($chunk[0] ?? '');
                continue;
            }
            $failMsgs[] = $aid !== '' ? $aid.': '.$err : $err;
        }
        $ok = array_values(array_unique(array_filter($ok)));
        if ($ok === [] && $failMsgs === []) {
            $ok = $chunk;
        }
        $this->updateLocalAdStatus($channel, $ok, $state);
        if ($state === AmazonAdsPauseRule::ACTION_PAUSED) {
            $stats['paused'] += count($ok);
        } else {
            $stats['enabled'] += count($ok);
        }
        $stats['failed'] += count($failMsgs);
        foreach ($failMsgs as $msg) {
            $stats['errors'][] = $msg;
        }
    }

    /**
     * @param  'sp'|'sb'  $channel
     * @param  list<string>  $adIds
     */
    private function updateLocalAdStatus(string $channel, array $adIds, string $state): void
    {
        if ($adIds === [] || ! Schema::hasTable('amazon_ads_campaign_skus')) {
            return;
        }
        try {
            foreach ($adIds as $id) {
                $id = trim((string) $id);
                if ($id === '') {
                    continue;
                }
                $q = AmazonAdsCampaignSku::query();
                if ($channel === 'sb') {
                    $q->where('ad_id', 'like', AmazonAdsCampaignSkuSync::SB_AD_PREFIX.$id.':%');
                } else {
                    $q->where('ad_id', $id);
                }
                $q->update(['state' => $state]);
            }
        } catch (\Throwable $e) {
            Log::warning('Amazon Ads reviews: local product-ad status update failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  'sp'|'sb'  $channel
     * @return list<array{campaign_id: string, campaignName: string, campaignStatus: string, acos: ?float}>
     */
    private function collectLatestCampaigns(string $channel): array
    {
        $table = $channel === 'sb' ? 'amazon_sb_campaign_reports' : 'amazon_sp_campaign_reports';
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'campaign_id')) {
            return [];
        }
        $cols = ['campaign_id', 'campaignName', 'campaignStatus', 'cost'];
        if (Schema::hasColumn($table, 'spend')) {
            $cols[] = 'spend';
        }
        if (Schema::hasColumn($table, 'sales30d')) {
            $cols[] = 'sales30d';
        }
        if (Schema::hasColumn($table, 'sales')) {
            $cols[] = 'sales';
        }
        $query = $channel === 'sb'
            ? AmazonSbCampaignReport::query()
            : AmazonSpCampaignReport::query();
        if (Schema::hasColumn($table, 'report_date_range')) {
            $query->where('report_date_range', 'L30');
        }
        $profileId = trim($this->ads->resolvedProfileId());
        if ($profileId !== '' && Schema::hasColumn($table, 'profile_id')) {
            $query->where(function ($q) use ($profileId) {
                $q->where('profile_id', $profileId)
                    ->orWhereNull('profile_id')
                    ->orWhere('profile_id', '');
            });
        }
        $rows = $query
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->orderByDesc('id')
            ->get($cols);

        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $cid = trim((string) ($row->campaign_id ?? ''));
            if ($cid === '' || isset($seen[$cid])) {
                continue;
            }
            $seen[$cid] = true;
            $status = strtoupper(trim((string) ($row->campaignStatus ?? '')));
            $out[] = [
                'campaign_id' => $cid,
                'campaignName' => trim((string) ($row->campaignName ?? '')),
                'campaignStatus' => $status,
                'acos' => AmazonAcosSbgtRule::acosPercentForSbgtFromReportRow($row->toArray()),
            ];
        }

        return $out;
    }

    /**
     * @param  'sp'|'sb'  $channel
     * @param  list<array{campaign_id: string, campaignName: string, campaignStatus: string, acos: ?float}>  $rows
     * @param  array<string, mixed>  $rule
     * @param  array<string, array{sku: string, price: ?float, dil: ?float, inv: ?float, l30: ?float, rating?: ?float}>  $metricsByName
     * @param  array<string, array{rating: float|null, review_count: int|null, sku: string}>  $ratingsByCid
     * @param  array{paused: int, enabled: int, unchanged: int, skipped: int, failed: int, errors: list<string>}  $stats
     */
    private function applyChannel(
        string $channel,
        array $rows,
        array $rule,
        array $metricsByName,
        array $ratingsByCid,
        bool $dryRun,
        array &$stats
    ): void {
        $pauseIds = [];
        $enableIds = [];
        foreach ($rows as $row) {
            $status = $row['campaignStatus'];
            if ($status === 'ARCHIVED') {
                $stats['skipped']++;
                continue;
            }
            $m = $metricsByName[$row['campaignName']] ?? ['price' => null, 'dil' => null, 'sku' => ''];
            $gm = AmazonAdsCampaignSkuMetrics::gridMetricsForPause($m);
            $cidRating = $ratingsByCid[$row['campaign_id']]['rating'] ?? null;
            $decision = AmazonAdsPauseRule::decide($rule, [
                'price' => $gm['price'],
                'dil' => $gm['dil'],
                'acos' => $row['acos'],
                'rating' => $cidRating ?? $gm['rating'] ?? null,
            ]);
            $desired = $decision['status'];
            $hits = $decision['hits'] ?? [];
            if ($desired === AmazonAdsPauseRule::ACTION_PAUSED) {
                if ($status === AmazonAdsPauseRule::ACTION_PAUSED) {
                    $stats['unchanged']++;
                    continue;
                }
                $pauseIds[] = $row['campaign_id'];
                continue;
            }
            if ($desired === AmazonAdsPauseRule::ACTION_ENABLED && $hits !== [] && $status !== AmazonAdsPauseRule::ACTION_ENABLED) {
                $sku = (string) ($m['sku'] ?? '');
                if ($sku !== '' && FbaInventoryService::blocksEnableForFbaSuffixZeroFbaInv($sku)) {
                    $stats['skipped']++;
                    continue;
                }
                $enableIds[] = $row['campaign_id'];
                continue;
            }
            $stats['unchanged']++;
        }

        if ($dryRun) {
            $stats['paused'] += count($pauseIds);
            $stats['enabled'] += count($enableIds);

            return;
        }

        $this->pushState($channel, $pauseIds, AmazonAdsPauseRule::ACTION_PAUSED, $stats);
        $this->pushState($channel, $enableIds, AmazonAdsPauseRule::ACTION_ENABLED, $stats);
    }

    /**
     * @param  'sp'|'sb'  $channel
     * @param  list<string>  $campaignIds
     * @param  array{paused: int, enabled: int, unchanged: int, skipped: int, failed: int, errors: list<string>}  $stats
     */
    private function pushState(string $channel, array $campaignIds, string $state, array &$stats): void
    {
        $campaignIds = array_values(array_unique(array_filter($campaignIds, static fn ($id) => trim((string) $id) !== '')));
        if ($campaignIds === []) {
            return;
        }
        $chunkSize = $channel === 'sb' ? 10 : 10;
        foreach (array_chunk($campaignIds, $chunkSize) as $chunk) {
            $this->pushStateChunk($channel, $chunk, $state, $stats, true);
        }
    }

    /**
     * @param  'sp'|'sb'  $channel
     * @param  list<string>  $chunk
     * @param  array{paused: int, enabled: int, unchanged: int, skipped: int, failed: int, errors: list<string>}  $stats
     */
    private function pushStateChunk(string $channel, array $chunk, string $state, array &$stats, bool $retrySingles): void
    {
        try {
            $payload = [];
            foreach ($chunk as $id) {
                $payload[] = [
                    'campaignId' => (string) $id,
                    'state' => $state,
                ];
            }
            $result = $channel === 'sb'
                ? $this->ads->updateSbCampaigns($payload)
                : $this->ads->updateCampaigns($payload);
            $this->recordPushResult($channel, $chunk, $state, $result, $stats);
        } catch (\Throwable $e) {
            if ($retrySingles && count($chunk) > 1) {
                Log::warning('Amazon Ads pause rule batch failed; retrying one campaign at a time', [
                    'channel' => $channel,
                    'state' => $state,
                    'count' => count($chunk),
                    'error' => $e->getMessage(),
                ]);
                foreach ($chunk as $id) {
                    $this->pushStateChunk($channel, [$id], $state, $stats, false);
                }

                return;
            }
            $decoded = self::decodeThrownAmazonBody($e);
            if (is_array($decoded)) {
                $this->recordPushResult($channel, $chunk, $state, $decoded, $stats);

                return;
            }
            $stats['failed'] += count($chunk);
            $stats['errors'][] = self::shortAmazonError($e->getMessage());
            Log::error('Amazon Ads pause rule push failed', [
                'channel' => $channel,
                'state' => $state,
                'count' => count($chunk),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  'sp'|'sb'  $channel
     * @param  list<string>  $chunk
     * @param  array<string, mixed>  $result
     * @param  array{paused: int, enabled: int, unchanged: int, skipped: int, failed: int, errors: list<string>}  $stats
     */
    private function recordPushResult(string $channel, array $chunk, string $state, array $result, array &$stats): void
    {
        [$okIds, $failMsgs] = self::parseUpdateResult($result, $chunk);
        $alreadyOk = [];
        $realFails = [];
        foreach ($failMsgs as $msg) {
            if (self::isAlreadyInDesiredStateMessage($msg, $state)) {
                $cid = self::campaignIdPrefixFromError($msg);
                if ($cid !== '') {
                    $alreadyOk[] = $cid;
                }
            } else {
                $realFails[] = $msg;
            }
        }
        $okIds = array_values(array_unique(array_merge($okIds, $alreadyOk)));
        if ($okIds === [] && $realFails === [] && $failMsgs !== []) {
            $okIds = $chunk;
            $realFails = [];
        }
        $this->updateLocalStatus($channel, $okIds, $state);
        if ($state === AmazonAdsPauseRule::ACTION_PAUSED) {
            $stats['paused'] += count($okIds);
        } else {
            $stats['enabled'] += count($okIds);
        }
        $stats['failed'] += count($realFails);
        foreach ($realFails as $msg) {
            $stats['errors'][] = $msg;
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<string>  $chunk
     * @return array{0: list<string>, 1: list<string>}
     */
    private static function parseUpdateResult(array $result, array $chunk): array
    {
        $block = is_array($result['campaigns'] ?? null) ? $result['campaigns'] : $result;
        $success = is_array($block['success'] ?? null) ? $block['success'] : [];
        $errors = [];
        if (is_array($block['error'] ?? null)) {
            $errors = $block['error'];
        } elseif (is_array($block['errors'] ?? null)) {
            $errors = $block['errors'];
        }
        $ok = [];
        foreach ($success as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (isset($row['campaignId']) && (string) $row['campaignId'] !== '') {
                $ok[] = (string) $row['campaignId'];
            } elseif (isset($row['index']) && isset($chunk[(int) $row['index']])) {
                $ok[] = (string) $chunk[(int) $row['index']];
            }
        }
        $failMsgs = [];
        foreach ($errors as $row) {
            if (! is_array($row)) {
                if (is_string($row) && $row !== '') {
                    $failMsgs[] = $row;
                }
                continue;
            }
            $cid = trim((string) ($row['campaignId'] ?? ''));
            if ($cid === '' && isset($row['index']) && isset($chunk[(int) $row['index']])) {
                $cid = (string) $chunk[(int) $row['index']];
            }
            $err = data_get($row, 'errors.0.errorValue.message')
                ?? data_get($row, 'errors.0.message')
                ?? data_get($row, 'errorValue.message')
                ?? ($row['message'] ?? null);
            $err = is_string($err) && $err !== '' ? $err : 'Amazon rejected campaign';
            $failMsgs[] = $cid !== '' ? $cid.': '.$err : $err;
        }
        if ($ok === [] && $failMsgs === []) {
            $top = $result['message'] ?? $result['details'] ?? $result['code'] ?? $block['message'] ?? null;
            if (is_string($top) && trim($top) !== '') {
                $msg = trim($top);
                foreach ($chunk as $id) {
                    $failMsgs[] = $id.': '.$msg;
                }
            } else {
                $ok = $chunk;
            }
        }

        return [$ok, $failMsgs];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeThrownAmazonBody(\Throwable $e): ?array
    {
        if (! $e instanceof \GuzzleHttp\Exception\RequestException || ! $e->hasResponse()) {
            return null;
        }
        $decoded = json_decode((string) $e->getResponse()->getBody(), true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function shortAmazonError(string $msg): string
    {
        $msg = trim((string) preg_replace('/\s+/', ' ', $msg));
        if (strlen($msg) > 280) {
            return substr($msg, 0, 277).'...';
        }

        return $msg;
    }

    private static function isAlreadyInDesiredStateMessage(string $msg, string $state): bool
    {
        $m = strtolower($msg);
        $st = strtolower($state);

        return str_contains($m, 'already')
            && (str_contains($m, $st) || str_contains($m, 'specified state') || str_contains($m, 'current state'));
    }

    private static function campaignIdPrefixFromError(string $msg): string
    {
        if (preg_match('/^(\d+)\s*:/', $msg, $m) === 1) {
            return $m[1];
        }

        return '';
    }

    /**
     * @param  'sp'|'sb'  $channel
     * @param  list<string>  $campaignIds
     */
    private function updateLocalStatus(string $channel, array $campaignIds, string $state): void
    {
        if ($campaignIds === []) {
            return;
        }
        $table = $channel === 'sb' ? 'amazon_sb_campaign_reports' : 'amazon_sp_campaign_reports';
        $payload = ['campaignStatus' => $state];
        if (Schema::hasColumn($table, 'pink_dil_paused_at')) {
            $payload['pink_dil_paused_at'] = $state === AmazonAdsPauseRule::ACTION_PAUSED ? now() : null;
        }
        try {
            if ($channel === 'sb') {
                AmazonSbCampaignReport::query()->whereIn('campaign_id', $campaignIds)->update($payload);
            } else {
                AmazonSpCampaignReport::query()->whereIn('campaign_id', $campaignIds)->update($payload);
            }
        } catch (\Throwable $e) {
            Log::warning('Amazon Ads pause rule: local status update failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
