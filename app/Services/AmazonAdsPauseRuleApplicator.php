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
            $stats['errors'][] = 'No pause/activate bands or PR Dil% rule configured — Amazon was not updated.';
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

        $this->applyChannel('sp', $sp, $rule, $metricsByName, $dryRun, $stats);
        $this->applyChannel('sb', $sb, $rule, $metricsByName, $dryRun, $stats);

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
                'campaignName' => (string) ($row->campaignName ?? ''),
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
     * @param  array<string, array{sku: string, price: ?float, dil: ?float, inv: ?float, l30: ?float}>  $metricsByName
     * @param  array{paused: int, enabled: int, unchanged: int, skipped: int, failed: int, errors: list<string>}  $stats
     */
    private function applyChannel(
        string $channel,
        array $rows,
        array $rule,
        array $metricsByName,
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
            $decision = AmazonAdsPauseRule::decide($rule, [
                'price' => $m['price'] ?? null,
                'dil' => $m['dil'] ?? null,
                'acos' => $row['acos'],
            ]);
            $desired = $decision['status'];
            $matched = ($decision['hits'] ?? []) !== [];
            if ($desired === '' || $desired === null || ! $matched) {
                $stats['unchanged']++;
                continue;
            }
            if ($desired === $status) {
                $stats['unchanged']++;
                continue;
            }
            if ($desired === AmazonAdsPauseRule::ACTION_ENABLED) {
                $sku = (string) ($m['sku'] ?? '');
                if ($sku !== '' && FbaInventoryService::blocksEnableForFbaSuffixZeroFbaInv($sku)) {
                    $stats['skipped']++;
                    continue;
                }
                $enableIds[] = $row['campaign_id'];
            } else {
                $pauseIds[] = $row['campaign_id'];
            }
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
        $chunkSize = $channel === 'sb' ? 10 : 100;
        foreach (array_chunk($campaignIds, $chunkSize) as $chunk) {
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
                [$okIds, $failMsgs] = self::parseUpdateResult($result, $chunk);
                $this->updateLocalStatus($channel, $okIds, $state);
                if ($state === AmazonAdsPauseRule::ACTION_PAUSED) {
                    $stats['paused'] += count($okIds);
                } else {
                    $stats['enabled'] += count($okIds);
                }
                $stats['failed'] += count($failMsgs);
                foreach ($failMsgs as $msg) {
                    $stats['errors'][] = $msg;
                }
            } catch (\Throwable $e) {
                $stats['failed'] += count($chunk);
                $stats['errors'][] = $e->getMessage();
                Log::error('Amazon Ads pause rule push failed', [
                    'channel' => $channel,
                    'state' => $state,
                    'count' => count($chunk),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<string>  $chunk
     * @return array{0: list<string>, 1: list<string>}
     */
    private static function parseUpdateResult(array $result, array $chunk): array
    {
        $block = $result['campaigns'] ?? $result;
        $success = is_array($block['success'] ?? null) ? $block['success'] : [];
        $errors = is_array($block['error'] ?? null) ? $block['error'] : [];
        $ok = [];
        foreach ($success as $row) {
            if (is_array($row) && isset($row['campaignId'])) {
                $ok[] = (string) $row['campaignId'];
            }
        }
        $failMsgs = [];
        foreach ($errors as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cid = (string) ($row['campaignId'] ?? '');
            $err = $row['errors'][0]['errorValue']['message'] ?? ($row['message'] ?? 'Amazon rejected campaign');
            $failMsgs[] = ($cid !== '' ? $cid.': ' : '').(is_string($err) ? $err : 'Amazon rejected campaign');
        }
        if ($ok === [] && $errors === []) {
            $ok = $chunk;
        }

        return [$ok, $failMsgs];
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
