<?php

namespace App\Services;

use App\Http\Controllers\Campaigns\AmazonSbBudgetController;
use App\Support\AmazonAdsApiRetry;
use App\Support\AmazonAdsEnabledCampaignSync;
use RuntimeException;

/**
 * Live GET of Amazon campaign daily budget and keyword/target bid.
 * Does not write report tables — callers persist only after verification.
 */
class AmazonAdsLiveValuePuller
{
    public function __construct(
        private AmazonAdsService $ads,
        private AmazonSbBudgetController $sb,
        private mixed $sleeper = null,
    ) {}

    /**
     * @param  'sp'|'sb'  $channel
     * @param  list<string>  $campaignIds
     * @return array<string, float|null>
     */
    public function pullBudgets(string $channel, array $campaignIds): array
    {
        $ids = self::normalizeIds($campaignIds);
        $out = array_fill_keys($ids, null);
        if ($ids === []) {
            return $out;
        }

        $run = AmazonAdsApiRetry::run(
            function () use ($channel, $ids): array {
                $campaigns = $channel === 'sb'
                    ? $this->ads->listSbCampaignsByIds($ids, ['ENABLED', 'PAUSED'])
                    : $this->ads->listSpCampaignsByIds($ids, ['ENABLED', 'PAUSED']);
                $map = [];
                foreach ($campaigns as $campaign) {
                    if (! is_array($campaign)) {
                        continue;
                    }
                    $cid = trim((string) ($campaign['campaignId'] ?? $campaign['campaign_id'] ?? ''));
                    if ($cid === '') {
                        continue;
                    }
                    $map[$cid] = AmazonAdsEnabledCampaignSync::budgetAmount($campaign);
                }

                return $map;
            },
            5,
            [400, 800, 1600, 3200, 6400],
            is_callable($this->sleeper) ? $this->sleeper : null
        );

        if (! $run['ok']) {
            throw new RuntimeException('Live BGT pull failed: '.($run['error'] ?? 'unknown'));
        }

        foreach ($run['value'] ?? [] as $cid => $bgt) {
            $out[(string) $cid] = $bgt !== null && is_numeric($bgt) ? (float) $bgt : null;
        }

        return $out;
    }

    /**
     * @param  'sp'|'sb'  $channel
     * @param  list<string>  $campaignIds
     * @return array<string, float|null>
     */
    public function pullBids(string $channel, array $campaignIds): array
    {
        $ids = self::normalizeIds($campaignIds);
        $out = array_fill_keys($ids, null);
        if ($ids === []) {
            return $out;
        }

        $run = AmazonAdsApiRetry::run(
            function () use ($channel, $ids): array {
                return $channel === 'sb'
                    ? $this->pullSbBids($ids)
                    : $this->pullSpBids($ids);
            },
            5,
            [400, 800, 1600, 3200, 6400],
            is_callable($this->sleeper) ? $this->sleeper : null
        );

        if (! $run['ok']) {
            throw new RuntimeException('Live BID pull failed: '.($run['error'] ?? 'unknown'));
        }

        foreach ($run['value'] ?? [] as $cid => $bid) {
            $out[(string) $cid] = $bid !== null && is_numeric($bid) ? (float) $bid : null;
        }

        return $out;
    }

    /**
     * @param  list<string>  $campaignIds
     * @return array<string, float|null>
     */
    private function pullSpBids(array $campaignIds): array
    {
        $out = array_fill_keys($campaignIds, null);
        $keywords = [];
        $targets = [];
        foreach (array_chunk($campaignIds, 8) as $chunk) {
            $keywords = array_merge($keywords, $this->ads->listKeywordsByCampaignIds($chunk));
            $targets = array_merge($targets, $this->ads->listTargetsByCampaignIds($chunk));
        }

        foreach (array_merge($keywords, $targets) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cid = trim((string) ($row['campaignId'] ?? $row['campaign_id'] ?? ''));
            $bid = self::entityBid($row);
            if ($cid === '' || $bid === null) {
                continue;
            }
            $out[$cid] = max($out[$cid] ?? 0, $bid);
        }

        return $out;
    }

    /**
     * @param  list<string>  $campaignIds
     * @return array<string, float|null>
     */
    private function pullSbBids(array $campaignIds): array
    {
        return $this->sb->getMaxKeywordBidForCampaigns($campaignIds);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function entityBid(array $row): ?float
    {
        $bid = $row['bid'] ?? null;
        if (is_array($bid)) {
            $bid = $bid['amount'] ?? $bid['value'] ?? $bid['bid'] ?? null;
        }
        if ($bid === null || $bid === '' || ! is_numeric($bid)) {
            return null;
        }
        $n = (float) $bid;

        return ($n > 0 && is_finite($n)) ? $n : null;
    }

    /**
     * @param  list<mixed>  $campaignIds
     * @return list<string>
     */
    public static function normalizeIds(array $campaignIds): array
    {
        $out = [];
        foreach ($campaignIds as $id) {
            $s = trim((string) $id);
            if ($s !== '') {
                $out[$s] = true;
            }
        }

        return array_keys($out);
    }
}
