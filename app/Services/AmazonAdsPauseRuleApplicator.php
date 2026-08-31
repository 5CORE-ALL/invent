<?php

namespace App\Services;

use App\Models\AmazonSbCampaignReport;
use App\Models\AmazonSpCampaignReport;
use App\Services\FbaInventoryService;
use App\Support\AmazonAcosSbgtRule;
use App\Support\AmazonAdsCampaignSkuMetrics;
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
        if (! AmazonAdsPauseRule::hasBands($rule)) {
            $stats['errors'][] = 'No pause/activate bands, PR Dil%, or Reviews rule configured — Amazon was not updated.';
            Log::warning('amazon:ads-pause-rule skipped: empty pause rule (no bands / PR off). Refusing to enable/pause campaigns.');

            return $stats;
        }
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

        return $stats;
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
