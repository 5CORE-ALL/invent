<?php

namespace App\Services;

use App\Http\Controllers\Campaigns\AmazonSbBudgetController;
use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use App\Http\Controllers\MarketPlace\ACOSControl\AmazonACOSController;
use App\Models\AmazonAdsLiveSyncState;
use App\Models\AmazonAdsPushLog;
use App\Support\AmazonAdsApiRetry;
use App\Support\AmazonAdsLiveSyncStatus;
use App\Support\AmazonAdsSbgt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pull live Amazon BGT/BID, compare to rule SBGT/SBID, push only on mismatch,
 * then pull again and persist only after the live value matches.
 */
class AmazonAdsLiveBidBgtSyncService
{
    public const BGT_TOLERANCE = 0.51;

    public const BID_TOLERANCE = 0.015;

    public const PULL_ATTEMPTS = 5;

    public const PUSH_ATTEMPTS = 5;

    public const VERIFY_ATTEMPTS = 6;

    /** @var list<int> */
    public const VERIFY_DELAYS_MS = [500, 1000, 2000, 4000, 8000, 8000];

    /** @var array<string, mixed> */
    private array $hooks;

    /** @var array<string, \Illuminate\Contracts\Cache\Lock> */
    private array $locks = [];

    public function __construct(
        private ?AmazonAdsLiveValuePuller $puller = null,
        array $hooks = [],
    ) {
        $this->hooks = $hooks;
    }

    /**
     * @param  list<array<string, mixed>>  $rows  campaign_id, channel (sp|sb), campaign_name?, sbgt?, sbid?
     * @return array{results: list<array<string, mixed>>, synced: int, failed: int, skipped: int, in_progress: int}
     */
    public function syncRows(array $rows, string $source = 'web'): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cid = trim((string) ($row['campaign_id'] ?? $row['campaignId'] ?? ''));
            if ($cid === '') {
                continue;
            }
            $channel = self::normalizeChannel($row['channel'] ?? $row['ad_type'] ?? 'sp');
            $name = trim((string) ($row['campaign_name'] ?? $row['campaignName'] ?? ''));
            $sbgt = $row['sbgt'] ?? null;
            $bid = self::positiveNumber($row['sbid'] ?? $row['bid'] ?? $row['last_sbid'] ?? null);
            $wantBgt = AmazonAdsSbgt::isExplicitZero($sbgt) || AmazonAdsSbgt::parsePushableBudget($sbgt) !== null;
            if (! $wantBgt && $bid === null) {
                continue;
            }
            $normalized[] = [
                'campaign_id' => $cid,
                'channel' => $channel,
                'campaign_name' => $name,
                'sbgt' => $wantBgt ? (AmazonAdsSbgt::isExplicitZero($sbgt) ? 0.0 : (float) AmazonAdsSbgt::parsePushableBudget($sbgt)) : null,
                'sbid' => $bid,
            ];
        }

        $liveBgt = ['sp' => null, 'sb' => null];
        $liveBid = ['sp' => null, 'sb' => null];
        $pullErrors = ['sp' => ['bgt' => null, 'bid' => null], 'sb' => ['bgt' => null, 'bid' => null]];
        foreach (['sp', 'sb'] as $ch) {
            $bgtIds = [];
            $bidIds = [];
            foreach ($normalized as $row) {
                if ($row['channel'] !== $ch) {
                    continue;
                }
                if ($row['sbgt'] !== null) {
                    $bgtIds[] = $row['campaign_id'];
                }
                if ($row['sbid'] !== null) {
                    $bidIds[] = $row['campaign_id'];
                }
            }
            if ($bgtIds !== []) {
                $pull = $this->pullLiveWithRetry($ch, 'bgt', $bgtIds);
                if ($pull['ok']) {
                    $liveBgt[$ch] = $pull['map'];
                } else {
                    $pullErrors[$ch]['bgt'] = $pull['error'] ?? 'pull_failed';
                }
            }
            if ($bidIds !== []) {
                $pull = $this->pullLiveWithRetry($ch, 'bid', $bidIds);
                if ($pull['ok']) {
                    $liveBid[$ch] = $pull['map'];
                } else {
                    $pullErrors[$ch]['bid'] = $pull['error'] ?? 'pull_failed';
                }
            }
        }

        $results = [];
        $synced = 0;
        $failed = 0;
        $skipped = 0;
        $inProgress = 0;
        foreach ($normalized as $row) {
            $one = $this->syncRowUsingPrefetch($row, $source, $liveBgt, $liveBid, $pullErrors);
            $results[] = $one;
            $st = (string) ($one['status'] ?? '');
            if ($st === 'synced') {
                $synced++;
            } elseif ($st === 'failed') {
                $failed++;
            } elseif ($st === 'in_progress') {
                $inProgress++;
            } else {
                $skipped++;
            }
        }

        return [
            'results' => $results,
            'synced' => $synced,
            'failed' => $failed,
            'skipped' => $skipped,
            'in_progress' => $inProgress,
        ];
    }

    /**
     * @param  array{campaign_id: string, channel: string, campaign_name: string, sbgt: float|null, sbid: float|null}  $row
     * @param  array<string, array<string, float|null>|null>  $liveBgt
     * @param  array<string, array<string, float|null>|null>  $liveBid
     * @param  array<string, array{bgt: ?string, bid: ?string}>  $pullErrors
     * @return array<string, mixed>
     */
    private function syncRowUsingPrefetch(array $row, string $source, array $liveBgt, array $liveBid, array $pullErrors): array
    {
        $cid = $row['campaign_id'];
        $channel = $row['channel'];
        $name = $row['campaign_name'];
        $out = ['campaign_id' => $cid, 'channel' => $channel, 'campaign_name' => $name, 'fields' => []];
        $worst = 'synced';
        $work = [];
        if ($row['sbgt'] !== null) {
            $work['bgt'] = (float) $row['sbgt'];
        }
        if ($row['sbid'] !== null) {
            $work['bid'] = (float) $row['sbid'];
        }
        foreach ($work as $field => $desired) {
            $prefetch = $field === 'bid' ? $liveBid[$channel] : $liveBgt[$channel];
            $err = $pullErrors[$channel][$field] ?? null;
            if ($prefetch === null && $err !== null) {
                $one = [
                    'campaign_id' => $cid,
                    'channel' => $channel,
                    'field' => $field,
                    'desired' => $desired,
                    'status' => 'failed',
                    'reason' => 'pull_failed: '.$err,
                    'old_live' => null,
                ];
                $this->finish($one, 'failed', 'pull_failed: '.$err, $source, $name, $desired, null);
                $out['fields'][$field] = $one;
                $worst = 'failed';
                continue;
            }
            $pulled = is_array($prefetch) ? ($prefetch[$cid] ?? null) : null;
            // Fresh Amazon value already matches SBID/SBGT: keep it, do not push.
            if (is_numeric($pulled)) {
                $one = $this->syncField($channel, $field, $cid, $name, $desired, $source, (float) $pulled);
            } else {
                $one = $this->syncField($channel, $field, $cid, $name, $desired, $source);
            }
            $out['fields'][$field] = $one;
            $st = (string) ($one['status'] ?? 'failed');
            if ($st === 'failed') {
                $worst = 'failed';
            } elseif ($st === 'in_progress' && $worst !== 'failed') {
                $worst = 'in_progress';
            } elseif ($st !== 'synced' && $worst === 'synced') {
                $worst = $st;
            }
        }
        $out['status'] = $worst;
        $out['reason'] = $out['fields']['bgt']['reason'] ?? $out['fields']['bid']['reason'] ?? null;

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function syncRow(array $row, string $source = 'web'): array
    {
        $cid = trim((string) ($row['campaign_id'] ?? $row['campaignId'] ?? ''));
        $channel = self::normalizeChannel($row['channel'] ?? $row['ad_type'] ?? 'sp');
        $name = trim((string) ($row['campaign_name'] ?? $row['campaignName'] ?? ''));
        if ($cid === '') {
            return ['campaign_id' => '', 'status' => 'skipped', 'reason' => 'missing_campaign_id'];
        }

        $fields = [];
        $sbgt = $row['sbgt'] ?? null;
        $sbid = $row['sbid'] ?? $row['bid'] ?? null;
        if (AmazonAdsSbgt::isExplicitZero($sbgt) || AmazonAdsSbgt::parsePushableBudget($sbgt) !== null) {
            $fields['bgt'] = AmazonAdsSbgt::isExplicitZero($sbgt) ? 0.0 : (float) AmazonAdsSbgt::parsePushableBudget($sbgt);
        }
        $bid = self::positiveNumber($sbid);
        if ($bid !== null) {
            $fields['bid'] = $bid;
        }
        if ($fields === []) {
            return ['campaign_id' => $cid, 'channel' => $channel, 'status' => 'skipped', 'reason' => 'no_desired_sbid_or_sbgt'];
        }

        $out = ['campaign_id' => $cid, 'channel' => $channel, 'campaign_name' => $name, 'fields' => []];
        $worst = 'synced';
        foreach ($fields as $field => $desired) {
            $one = $this->syncField($channel, $field, $cid, $name, (float) $desired, $source);
            $out['fields'][$field] = $one;
            $st = (string) ($one['status'] ?? 'failed');
            if ($st === 'failed') {
                $worst = 'failed';
            } elseif ($st === 'in_progress' && $worst !== 'failed') {
                $worst = 'in_progress';
            } elseif ($st !== 'synced' && $worst === 'synced') {
                $worst = $st;
            }
        }
        $out['status'] = $worst;
        $out['reason'] = $out['fields']['bgt']['reason'] ?? $out['fields']['bid']['reason'] ?? null;

        return $out;
    }

    /**
     * Cron helper: desired budget map → verify loop. Returns chunk-trait shape.
     *
     * @param  array<string, float|int>  $idToSbgt
     * @param  array<string, string>  $names
     * @return array{updated_ids: list<string>, failed: list<array<string, mixed>>, skipped: list<array<string, mixed>>}
     */
    public function syncBudgetMap(string $channel, array $idToSbgt, array $names, string $source): array
    {
        $updated = [];
        $failed = [];
        $skipped = [];
        foreach ($idToSbgt as $cid => $sbgt) {
            $row = $this->syncField($channel, 'bgt', (string) $cid, (string) ($names[$cid] ?? ''), (float) $sbgt, $source);
            $st = (string) ($row['status'] ?? 'failed');
            if ($st === 'synced') {
                $updated[] = (string) $cid;
            } elseif ($st === 'in_progress' || $st === 'skipped') {
                $skipped[] = ['campaign_id' => (string) $cid, 'reason' => $row['reason'] ?? $st];
            } else {
                $failed[] = ['campaign_id' => (string) $cid, 'reason' => $row['reason'] ?? 'sync failed', 'error' => $row['reason'] ?? 'sync failed'];
            }
        }

        return ['updated_ids' => $updated, 'failed' => $failed, 'skipped' => $skipped];
    }

    /**
     * @param  list<string>  $ids
     * @param  list<float|int>  $bids
     * @return array{status: int, failed: list<array<string, mixed>>, skipped: list<array<string, mixed>>}
     */
    public function syncBidChunk(string $channel, array $ids, array $bids, string $source, array $names = []): array
    {
        $failed = [];
        $skipped = [];
        foreach ($ids as $i => $cid) {
            $cid = trim((string) $cid);
            $bid = self::positiveNumber($bids[$i] ?? null);
            if ($cid === '' || $bid === null) {
                $skipped[] = ['campaign_id' => $cid, 'reason' => 'invalid_bid'];
                continue;
            }
            $row = $this->syncField($channel, 'bid', $cid, (string) ($names[$cid] ?? ''), $bid, $source);
            $st = (string) ($row['status'] ?? 'failed');
            if ($st === 'synced') {
                continue;
            }
            if ($st === 'in_progress' || $st === 'skipped') {
                $skipped[] = ['campaign_id' => $cid, 'reason' => $row['reason'] ?? $st];
            } else {
                $failed[] = ['campaign_id' => $cid, 'reason' => $row['reason'] ?? 'sync failed', 'error' => $row['reason'] ?? 'sync failed'];
            }
        }

        return [
            'status' => $failed === [] ? 200 : 207,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function syncField(string $channel, string $field, string $campaignId, string $campaignName, float $desired, string $source, ?float $knownLive = null): array
    {
        $channel = self::normalizeChannel($channel);
        $field = $field === 'bid' ? 'bid' : 'bgt';
        $lockKey = 'amazon-ads-live-sync:'.$channel.':'.$field.':'.$campaignId;
        $locked = $this->acquireLockWithRetry($lockKey);
        if ($locked === false) {
            $existing = $this->loadState($channel, $field, $campaignId);
            if (is_array($existing) && (string) ($existing['status'] ?? '') === 'synced') {
                $live = $existing['live_value'] ?? $existing['desired_value'] ?? null;
                $tolerance = $field === 'bid' ? self::BID_TOLERANCE : self::BGT_TOLERANCE;
                $liveNum = is_numeric($live) ? (float) $live : null;
                if (AmazonAdsApiRetry::valuesMatch($liveNum, $desired, $tolerance)) {
                    return $this->withPresentedStatus([
                        'campaign_id' => $campaignId,
                        'channel' => $channel,
                        'field' => $field,
                        'desired' => $desired,
                        'verified_live' => $liveNum,
                        'status' => 'synced',
                        'reason' => 'already_matched',
                    ]);
                }
            }

            // Do not persist this. A second sync used to overwrite a verified row
            // with in_progress and the grid stayed yellow even when Lbid already matched SBID.
            return $this->withPresentedStatus([
                'campaign_id' => $campaignId,
                'channel' => $channel,
                'field' => $field,
                'desired' => $desired,
                'status' => 'in_progress',
                'reason' => 'concurrent_sync',
            ]);
        }

        try {
            return $this->runField($channel, $field, $campaignId, $campaignName, $desired, $source, $knownLive);
        } finally {
            $this->releaseLock($lockKey);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function runField(string $channel, string $field, string $campaignId, string $campaignName, float $desired, string $source, ?float $knownLive = null): array
    {
        $base = [
            'campaign_id' => $campaignId,
            'channel' => $channel,
            'field' => $field,
            'desired' => $desired,
            'old_live' => null,
            'verified_live' => null,
            'push_response' => null,
            'verify_result' => null,
            'pull_attempts' => 0,
            'push_attempts' => 0,
            'verify_attempts' => 0,
        ];
        $tolerance = $field === 'bid' ? self::BID_TOLERANCE : self::BGT_TOLERANCE;
        if ($knownLive !== null) {
            $oldLive = $knownLive;
            $base['pull_attempts'] = 1;
            $base['old_live'] = $oldLive;
        } else {
            $this->saveState($channel, $field, $campaignId, $campaignName, $desired, null, 'pending', 'pulling_live', 0, $base);

            $pull = $this->pullLiveWithRetry($channel, $field, [$campaignId]);
            $base['pull_attempts'] = (int) ($pull['attempts'] ?? 0);
            if (! $pull['ok']) {
                return $this->finish($base, 'failed', 'pull_failed: '.($pull['error'] ?? 'unknown'), $source, $campaignName, $desired, null);
            }
            $oldLive = $pull['map'][$campaignId] ?? null;
            $base['old_live'] = $oldLive;
        }

        if ($oldLive === null || ! is_numeric($oldLive)) {
            $why = $field === 'bid'
                ? 'pull_failed: no live Amazon keyword/target bid found'
                : 'pull_failed: campaign budget not returned by Amazon';

            return $this->finish($base, 'failed', $why, $source, $campaignName, $desired, null);
        }

        if ($field === 'bgt' && AmazonAdsSbgt::isExplicitZero($desired)) {
            return $this->syncPause($channel, $campaignId, $campaignName, $oldLive, $base, $source);
        }

        if (AmazonAdsApiRetry::valuesMatch($oldLive !== null ? (float) $oldLive : null, $desired, $tolerance)) {
            $this->persistVerifiedLive($channel, $field, $campaignId, (float) $oldLive);

            return $this->finish($base, 'synced', 'already_matched', $source, $campaignName, $desired, (float) $oldLive, 'skipped');
        }

        $push = $this->pushWithRetry($channel, $field, $campaignId, $desired);
        $base['push_attempts'] = (int) ($push['attempts'] ?? 0);
        $base['push_response'] = $push['response'] ?? null;
        if (! $push['ok']) {
            return $this->finish($base, 'failed', 'push_failed: '.($push['error'] ?? 'unknown'), $source, $campaignName, $desired, $oldLive);
        }

        $this->saveState($channel, $field, $campaignId, $campaignName, $desired, $oldLive !== null ? (float) $oldLive : null, 'pending', 'push_succeeded_waiting_verify', (int) $base['push_attempts'], $base);

        $verify = $this->verifyUntilMatch($channel, $field, $campaignId, $desired, $tolerance);
        $base['verify_attempts'] = (int) ($verify['attempts'] ?? 0);
        $base['verified_live'] = $verify['live'] ?? null;
        $base['verify_result'] = $verify['ok'] ? 'matched' : 'mismatch';
        if (! $verify['ok']) {
            return $this->finish(
                $base,
                'failed',
                'verify_failed: live '.($verify['live'] ?? 'null').' !== desired '.$desired,
                $source,
                $campaignName,
                $desired,
                $oldLive
            );
        }

        $this->persistVerifiedLive($channel, $field, $campaignId, (float) $verify['live']);

        return $this->finish($base, 'synced', 'verified_after_push', $source, $campaignName, $desired, (float) $verify['live']);
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function syncPause(string $channel, string $campaignId, string $campaignName, mixed $oldLive, array $base, string $source): array
    {
        $push = AmazonAdsApiRetry::run(
            function () use ($channel, $campaignId) {
                $stats = $this->pauseCampaigns($channel, [$campaignId]);
                if ((int) ($stats['failed'] ?? 0) > 0) {
                    throw new \RuntimeException(implode('; ', $stats['errors'] ?? ['pause failed']));
                }

                return $stats;
            },
            self::PUSH_ATTEMPTS,
            [400, 800, 1600, 3200, 6400],
            $this->sleeper()
        );
        $base['push_attempts'] = (int) ($push['attempts'] ?? 0);
        $base['push_response'] = $push['value'] ?? $push['error'];
        if (! $push['ok']) {
            return $this->finish($base, 'failed', 'pause_failed: '.($push['error'] ?? 'unknown'), $source, $campaignName, 0.0, $oldLive);
        }

        $this->saveState($channel, 'bgt', $campaignId, $campaignName, 0.0, $oldLive !== null ? (float) $oldLive : null, 'pending', 'push_succeeded_waiting_verify', (int) $base['push_attempts'], $base);

        $verify = AmazonAdsApiRetry::run(
            function () use ($channel, $campaignId) {
                $campaigns = $channel === 'sb'
                    ? $this->ads()->listSbCampaignsByIds([$campaignId], ['ENABLED', 'PAUSED'])
                    : $this->ads()->listSpCampaignsByIds([$campaignId], ['ENABLED', 'PAUSED']);
                $state = strtoupper((string) (($campaigns[0]['state'] ?? $campaigns[0]['campaignStatus'] ?? '') ?: ''));
                if ($state !== 'PAUSED') {
                    throw new \RuntimeException('campaign state '.$state.' !== PAUSED');
                }

                return $state;
            },
            self::VERIFY_ATTEMPTS,
            self::VERIFY_DELAYS_MS,
            $this->sleeper(),
            true
        );
        $base['verify_attempts'] = (int) ($verify['attempts'] ?? 0);
        $base['verify_result'] = $verify['ok'] ? 'paused' : 'not_paused';
        if (! $verify['ok']) {
            return $this->finish($base, 'failed', 'verify_failed: '.($verify['error'] ?? 'not paused'), $source, $campaignName, 0.0, $oldLive);
        }

        return $this->finish($base, 'synced', 'paused_zero_sbgt', $source, $campaignName, 0.0, $oldLive !== null ? (float) $oldLive : null);
    }

    /**
     * @param  list<string>  $ids
     * @return array{ok: bool, map: array<string, float|null>, error: ?string, attempts: int}
     */
    private function pullLiveWithRetry(string $channel, string $field, array $ids): array
    {
        $run = AmazonAdsApiRetry::run(
            function () use ($channel, $field, $ids) {
                $map = $field === 'bid'
                    ? $this->pullBids($channel, $ids)
                    : $this->pullBudgets($channel, $ids);
                if (! is_array($map)) {
                    throw new \RuntimeException('pull returned non-array');
                }

                return $map;
            },
            self::PULL_ATTEMPTS,
            [400, 800, 1600, 3200, 6400],
            $this->sleeper()
        );

        return [
            'ok' => (bool) $run['ok'],
            'map' => is_array($run['value']) ? $run['value'] : [],
            'error' => $run['error'],
            'attempts' => (int) $run['attempts'],
        ];
    }

    /**
     * @return array{ok: bool, response: mixed, error: ?string, attempts: int}
     */
    private function pushWithRetry(string $channel, string $field, string $campaignId, float $desired): array
    {
        $run = AmazonAdsApiRetry::run(
            function () use ($channel, $field, $campaignId, $desired) {
                $result = $field === 'bid'
                    ? $this->pushBid($channel, $campaignId, $desired)
                    : $this->pushBudget($channel, $campaignId, $desired);
                $failed = is_array($result) ? ($result['failed'] ?? []) : [];
                if (is_array($failed) && $failed !== []) {
                    $reason = (string) ($failed[0]['reason'] ?? $failed[0]['error'] ?? 'amazon rejected');
                    throw new \RuntimeException($reason);
                }
                $status = is_array($result) ? (int) ($result['status'] ?? 200) : 200;
                if ($status >= 400) {
                    throw new \RuntimeException((string) ($result['error'] ?? $result['message'] ?? 'push http '.$status));
                }

                return $result;
            },
            self::PUSH_ATTEMPTS,
            [400, 800, 1600, 3200, 6400],
            $this->sleeper()
        );

        return [
            'ok' => (bool) $run['ok'],
            'response' => $run['value'],
            'error' => $run['error'],
            'attempts' => (int) $run['attempts'],
        ];
    }

    /**
     * @return array{ok: bool, live: float|null, attempts: int, error: ?string}
     */
    private function verifyUntilMatch(string $channel, string $field, string $campaignId, float $desired, float $tolerance): array
    {
        $lastLive = null;
        $run = AmazonAdsApiRetry::run(
            function () use ($channel, $field, $campaignId, $desired, $tolerance, &$lastLive) {
                $map = $field === 'bid'
                    ? $this->pullBids($channel, [$campaignId])
                    : $this->pullBudgets($channel, [$campaignId]);
                $live = $map[$campaignId] ?? null;
                $lastLive = $live;
                if (! AmazonAdsApiRetry::valuesMatch($live !== null ? (float) $live : null, $desired, $tolerance)) {
                    throw new \RuntimeException('live '.($live ?? 'null').' !== '.$desired);
                }

                return $live;
            },
            self::VERIFY_ATTEMPTS,
            self::VERIFY_DELAYS_MS,
            $this->sleeper(),
            true
        );

        return [
            'ok' => (bool) $run['ok'],
            'live' => $run['ok'] ? (is_numeric($run['value']) ? (float) $run['value'] : $lastLive) : $lastLive,
            'attempts' => (int) $run['attempts'],
            'error' => $run['error'],
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function finish(
        array $base,
        string $status,
        string $reason,
        string $source,
        string $campaignName,
        float $desired,
        mixed $live,
        ?string $logStatus = null
    ): array {
        $base['status'] = $status;
        $base['reason'] = $reason;
        $base['verified_live'] = $base['verified_live'] ?? ($live !== null ? (float) $live : null);
        $attempts = (int) ($base['pull_attempts'] ?? 0) + (int) ($base['push_attempts'] ?? 0) + (int) ($base['verify_attempts'] ?? 0);
        $this->saveState(
            (string) $base['channel'],
            (string) $base['field'],
            (string) $base['campaign_id'],
            $campaignName,
            $desired,
            $live !== null && is_numeric($live) ? (float) $live : null,
            $status,
            $reason,
            $attempts,
            $base
        );
        $this->logPush($base, $logStatus ?? ($status === 'synced' ? 'success' : ($status === 'skipped' || $status === 'in_progress' ? 'skipped' : 'failed')), $source, $campaignName, $desired);

        return $this->withPresentedStatus($base);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withPresentedStatus(array $row): array
    {
        $presented = AmazonAdsLiveSyncStatus::present(
            (string) ($row['field'] ?? 'bgt'),
            [
                'status' => $row['status'] ?? null,
                'reason' => $row['reason'] ?? null,
                'desired_value' => $row['desired'] ?? $row['desired_value'] ?? null,
                'live_value' => $row['verified_live'] ?? $row['live_value'] ?? $row['old_live'] ?? null,
                'attempts' => (int) ($row['pull_attempts'] ?? 0) + (int) ($row['push_attempts'] ?? 0) + (int) ($row['verify_attempts'] ?? 0),
                'detail' => $row,
            ],
            $row['desired'] ?? null
        );
        $row['sync_color'] = $presented['color'];
        $row['sync_tip'] = $presented['tip'];

        return $row;
    }

    /**
     * @param  array<string, mixed>  $base
     */
    private function logPush(array $base, string $status, string $source, string $campaignName, float $desired): void
    {
        if (isset($this->hooks['log'])) {
            ($this->hooks['log'])($base, $status, $source, $campaignName, $desired);

            return;
        }
        try {
            $field = (string) ($base['field'] ?? 'bgt');
            $channel = (string) ($base['channel'] ?? 'sp');
            AmazonAdsPushLog::logPush([
                'push_type' => ($channel === 'sb' ? 'sb_' : 'sp_').($field === 'bid' ? 'sbid' : 'sbgt'),
                'campaign_id' => $base['campaign_id'] ?? null,
                'campaign_name' => $campaignName !== '' ? $campaignName : null,
                'value' => $desired,
                'status' => $status,
                'reason' => $base['reason'] ?? null,
                'request_data' => [
                    'desired' => $desired,
                    'old_live' => $base['old_live'] ?? null,
                ],
                'response_data' => [
                    'old_live' => $base['old_live'] ?? null,
                    'desired' => $desired,
                    'verified_live' => $base['verified_live'] ?? null,
                    'push_response' => $base['push_response'] ?? null,
                    'verify_result' => $base['verify_result'] ?? null,
                    'pull_attempts' => $base['pull_attempts'] ?? 0,
                    'push_attempts' => $base['push_attempts'] ?? 0,
                    'verify_attempts' => $base['verify_attempts'] ?? 0,
                ],
                'source' => $source,
            ]);
        } catch (Throwable $e) {
            Log::warning('amazon-ads live sync: push log failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function saveState(
        string $channel,
        string $field,
        string $campaignId,
        string $campaignName,
        float $desired,
        ?float $live,
        string $status,
        string $reason,
        int $attempts,
        array $detail
    ): void {
        if (isset($this->hooks['saveState'])) {
            ($this->hooks['saveState'])($channel, $field, $campaignId, $desired, $live, $status, $reason, $attempts, $detail);

            return;
        }
        try {
            if (! Schema::hasTable('amazon_ads_live_sync_states')) {
                return;
            }
            AmazonAdsLiveSyncState::query()->updateOrCreate(
                ['channel' => $channel, 'field' => $field, 'campaign_id' => $campaignId],
                [
                    'campaign_name' => $campaignName !== '' ? $campaignName : null,
                    'desired_value' => $desired,
                    'live_value' => $live,
                    'status' => $status,
                    'reason' => $reason,
                    'attempts' => $attempts,
                    'detail' => $detail,
                    'verified_at' => $status === 'synced' ? now() : null,
                    'locked_at' => null,
                ]
            );
        } catch (Throwable $e) {
            Log::warning('amazon-ads live sync: state save failed', ['error' => $e->getMessage()]);
        }
    }

    private function persistVerifiedLive(string $channel, string $field, string $campaignId, float $live): void
    {
        if (isset($this->hooks['persistLive'])) {
            ($this->hooks['persistLive'])($channel, $field, $campaignId, $live);

            return;
        }
        $table = $channel === 'sb' ? 'amazon_sb_campaign_reports' : 'amazon_sp_campaign_reports';
        if (! Schema::hasTable($table)) {
            return;
        }
        $ranges = ['L30', 'L15', 'L7', 'L1'];
        try {
            $latestDaily = DB::table($table)
                ->whereRaw('CHAR_LENGTH(TRIM(report_date_range)) >= 10')
                ->whereRaw("LEFT(TRIM(report_date_range), 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
                ->max(DB::raw('LEFT(TRIM(report_date_range), 10)'));
            if (is_string($latestDaily) && $latestDaily !== '') {
                $ranges[] = $latestDaily;
            }
        } catch (Throwable) {
        }

        $payload = ['updated_at' => now()];
        if ($field === 'bgt' && Schema::hasColumn($table, 'campaignBudgetAmount')) {
            $payload['campaignBudgetAmount'] = round($live, 2);
        } elseif ($field === 'bid' && Schema::hasColumn($table, 'last_sbid')) {
            $payload['last_sbid'] = (string) round($live, 2);
        } else {
            return;
        }

        try {
            DB::table($table)
                ->where('campaign_id', $campaignId)
                ->whereIn('report_date_range', $ranges)
                ->update($payload);
        } catch (Throwable $e) {
            Log::warning('amazon-ads live sync: persist live failed', [
                'table' => $table,
                'campaign_id' => $campaignId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, float|null>
     */
    private function pullBudgets(string $channel, array $ids): array
    {
        if (isset($this->hooks['pullBudgets'])) {
            return ($this->hooks['pullBudgets'])($channel, $ids);
        }

        return $this->puller()->pullBudgets($channel, $ids);
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, float|null>
     */
    private function pullBids(string $channel, array $ids): array
    {
        if (isset($this->hooks['pullBids'])) {
            return ($this->hooks['pullBids'])($channel, $ids);
        }

        return $this->puller()->pullBids($channel, $ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function pushBudget(string $channel, string $campaignId, float $desired): array
    {
        if (isset($this->hooks['pushBudget'])) {
            return ($this->hooks['pushBudget'])($channel, $campaignId, $desired);
        }
        $acos = app(AmazonACOSController::class);
        $result = $channel === 'sb'
            ? $acos->updateAutoAmazonSbCampaignBgt([$campaignId], [$desired])
            : $acos->updateAutoAmazonCampaignBgt([$campaignId], [$desired]);

        return is_array($result) ? $result : ['status' => 500, 'failed' => [['campaign_id' => $campaignId, 'reason' => 'non-array push result']]];
    }

    /**
     * @return array<string, mixed>
     */
    private function pushBid(string $channel, string $campaignId, float $desired): array
    {
        if (isset($this->hooks['pushBid'])) {
            return ($this->hooks['pushBid'])($channel, $campaignId, $desired);
        }
        if ($channel === 'sb') {
            $result = app(AmazonSbBudgetController::class)->updateAutoCampaignSbKeywordsBid([$campaignId], [$desired]);
        } else {
            $result = app(AmazonSpBudgetController::class)->updateAutoCampaignKeywordsBid([$campaignId], [$desired], false);
        }
        if (is_object($result) && method_exists($result, 'getData')) {
            $result = $result->getData(true);
        }

        return is_array($result) ? $result : ['status' => 500, 'failed' => [['campaign_id' => $campaignId, 'reason' => 'non-array push result']]];
    }

    /**
     * @param  list<string>  $ids
     * @return array{paused: int, failed: int, errors: list<string>}
     */
    private function pauseCampaigns(string $channel, array $ids): array
    {
        if (isset($this->hooks['pause'])) {
            return ($this->hooks['pause'])($channel, $ids);
        }
        $stats = app(AmazonAdsPauseRuleApplicator::class)->pauseCampaigns($channel, $ids);

        return [
            'paused' => (int) ($stats['paused'] ?? 0),
            'failed' => (int) ($stats['failed'] ?? 0),
            'errors' => is_array($stats['errors'] ?? null) ? $stats['errors'] : [],
        ];
    }

    private function ads(): object
    {
        if (isset($this->hooks['ads'])) {
            return $this->hooks['ads'];
        }

        return app(AmazonAdsService::class);
    }

    private function puller(): AmazonAdsLiveValuePuller
    {
        return $this->puller ?? app(AmazonAdsLiveValuePuller::class);
    }

    private function sleeper(): callable
    {
        return $this->hooks['sleeper'] ?? static function (int $ms): void {
            if ($ms > 0) {
                usleep($ms * 1000);
            }
        };
    }

    private function acquireLockWithRetry(string $key): bool
    {
        $delaysMs = [0, 200, 400, 800];
        foreach ($delaysMs as $i => $ms) {
            if ($i > 0) {
                ($this->sleeper())($ms);
            }
            if ($this->acquireLock($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadState(string $channel, string $field, string $campaignId): ?array
    {
        if (isset($this->hooks['loadState'])) {
            $loaded = ($this->hooks['loadState'])($channel, $field, $campaignId);

            return is_array($loaded) ? $loaded : null;
        }
        try {
            if (! Schema::hasTable('amazon_ads_live_sync_states')) {
                return null;
            }
            $row = AmazonAdsLiveSyncState::query()
                ->where('channel', $channel)
                ->where('field', $field)
                ->where('campaign_id', $campaignId)
                ->first();

            return $row ? $row->toArray() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function acquireLock(string $key): bool
    {
        if (isset($this->hooks['acquireLock'])) {
            return (bool) ($this->hooks['acquireLock'])($key);
        }
        try {
            $lock = Cache::lock($key, 180);
            if (! $lock->get()) {
                return false;
            }
            $this->locks[$key] = $lock;

            return true;
        } catch (Throwable) {
            return true;
        }
    }

    private function releaseLock(string $key): void
    {
        if (isset($this->hooks['releaseLock'])) {
            ($this->hooks['releaseLock'])($key);

            return;
        }
        try {
            if (isset($this->locks[$key])) {
                $this->locks[$key]->release();
                unset($this->locks[$key]);
            }
        } catch (Throwable) {
        }
    }

    public static function normalizeChannel(mixed $raw): string
    {
        $s = strtoupper(trim((string) $raw));
        if (str_contains($s, 'BRAND') || $s === 'SB' || $s === 'HL' || $s === 'SB_REPORTS') {
            return 'sb';
        }

        return 'sp';
    }

    public static function positiveNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $n = (float) $value;

        return ($n > 0 && is_finite($n)) ? $n : null;
    }
}
