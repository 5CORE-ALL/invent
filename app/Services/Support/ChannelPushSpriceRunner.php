<?php

namespace App\Services\Support;

use App\Http\Controllers\MarketPlace\AlibabaAnalyticsController;
use App\Http\Controllers\MarketPlace\AliexpressController;
use App\Http\Controllers\MarketPlace\SheinController;
use App\Http\Controllers\MarketPlace\CvrMasterController;
use App\Http\Controllers\MarketPlace\MacyController;
use App\Http\Controllers\MarketPlace\DobaController;
use App\Http\Controllers\MarketPlace\NeweggPricingController;
use App\Http\Controllers\MarketPlace\OverallAmazonController;
use App\Http\Controllers\MarketPlace\TemuController;
use App\Http\Controllers\MarketPlace\Temu3Controller;
use App\Services\AlibabaApiService;
use App\Services\AliExpressApiService;
use App\Services\SheinApiService;
use App\Services\ChannelLivePriceSync;
use App\Services\NeweggApiService;
use App\Services\TemuApiService;
use App\Services\Temu2ApiService;
use App\Support\MacysAmazonPriceCap;
use App\Support\Marketplace\EbaySpricePush;
use App\Support\NegativeSnroiPushGuard;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Background S PRC → live listing price worker.
 * Runs on the server so closing the tabulator page does not stop pushes.
 */
class ChannelPushSpriceRunner
{
    private readonly string $channel;

    public function __construct(string $channel = 'ebay1')
    {
        $this->channel = strtolower(trim($channel)) ?: 'ebay1';
    }

    public static function for(string $channel): self
    {
        return new self($channel);
    }

    public static function livePushAllowed(): bool
    {
        if (! app()->environment('local')) {
            return true;
        }

        return filter_var(env('CHANNEL_PUSH_SPRICE_ALLOW_LOCAL', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function spawnWorker(string $channel): bool
    {
        $channel = strtolower(trim($channel)) ?: 'ebay1';
        if (! self::livePushAllowed()) {
            Log::warning('Channel S PRC worker spawn skipped — live push disabled in local', [
                'channel' => $channel,
            ]);

            return false;
        }
        try {
            if (self::lockHeld($channel)) {
                return true;
            }
            $php = PHP_BINARY ?: 'php';
            if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
                $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
                if ($cli !== '') {
                    $php = $cli;
                }
            }
            $artisan = base_path('artisan');
            $log = storage_path('logs/'.$channel.'-push-sprice.log');
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                pclose(popen('start /B '.escapeshellarg($php).' '.escapeshellarg($artisan).' channel:push-sprice-run '.escapeshellarg($channel).' --sync', 'r'));

                return true;
            }
            $cmd = 'nohup '.escapeshellarg($php).' '.escapeshellarg($artisan)
                .' channel:push-sprice-run '.escapeshellarg($channel)
                .' --sync >> '.escapeshellarg($log).' 2>&1 &';
            pclose(popen($cmd, 'r'));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Channel S PRC worker spawn failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public static function lockHeld(string $channel): bool
    {
        $lockPath = storage_path('app/'.$channel.'-push-sprice/runner.lock');
        if (! is_file($lockPath)) {
            return false;
        }
        $h = @fopen($lockPath, 'c+');
        if (! $h) {
            return false;
        }
        $got = flock($h, LOCK_EX | LOCK_NB);
        if ($got) {
            flock($h, LOCK_UN);
        }
        fclose($h);

        return ! $got;
    }

    public function run(): int
    {
        @set_time_limit(0);

        if (! self::livePushAllowed()) {
            ChannelPushSpriceJobStore::for($this->channel)->forceStop(
                'Blocked: local does not push live eBay prices. Set CHANNEL_PUSH_SPRICE_ALLOW_LOCAL=true to override.'
            );
            Log::warning('S PRC runner blocked in local environment', [
                'channel' => $this->channel,
            ]);

            return 0;
        }

        $store = ChannelPushSpriceJobStore::for($this->channel);
        $logger = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/'.$this->channel.'-push-sprice.log'),
            'level' => 'debug',
        ]);

        $lockPath = storage_path('app/'.$this->channel.'-push-sprice/runner.lock');
        $lockDir = dirname($lockPath);
        if (! is_dir($lockDir)) {
            @mkdir($lockDir, 0755, true);
        }
        $lockHandle = @fopen($lockPath, 'c+');
        if (! $lockHandle || ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            $logger->info('S PRC push runner skipped — another process holds the lock', [
                'channel' => $this->channel,
            ]);
            if ($lockHandle) {
                fclose($lockHandle);
            }

            return 0;
        }

        try {
            return $this->runLocked($store, $logger);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function runLocked(ChannelPushSpriceJobStore $store, \Psr\Log\LoggerInterface $logger): int
    {
        // This process holds the only runner lock, so a task still marked "pushing" was left by a worker
        // that died or was restarted mid-push. Re-queue it, otherwise the job never completes.
        $store->update(function (array $state) {
            foreach ($state['tasks'] ?? [] as $i => $task) {
                if (is_array($task) && ($task['status'] ?? '') === 'pushing') {
                    $state['tasks'][$i]['status'] = 'pending';
                }
            }

            return $state;
        });

        while (true) {
            $state = $store->load();
            if (($state['status'] ?? 'idle') !== 'running') {
                return 0;
            }

            // Purchasing Power: a push waits ~20–40 s for the MCM import, so push the queue in batches (one import each).
            if ($this->channel === 'purchasing_power' && $this->pushPurchasingPowerBatch($store, $logger)) {
                usleep($this->pauseAfterPushMicros());

                continue;
            }

            $index = $this->findNextPendingIndex($state['tasks'] ?? []);
            if ($index === null) {
                $store->update(function (array $state) {
                    foreach ($state['tasks'] ?? [] as $task) {
                        if (! is_array($task)) {
                            continue;
                        }
                        if (in_array((string) ($task['status'] ?? ''), ['pending', 'queued', 'pushing'], true)) {
                            return $state;
                        }
                    }
                    $state['status'] = 'completed';
                    $state['current_sku'] = null;
                    $state['finished_at'] = now()->toDateTimeString();
                    $state['last_message'] = "Completed: {$state['ok_count']} ok, {$state['fail_count']} failed.";

                    return $state;
                });
                $state = $store->load();
                if (($state['status'] ?? '') === 'completed') {
                    $store->appendMessage(
                        "Completed: {$state['ok_count']} ok, {$state['fail_count']} failed.",
                        ((int) ($state['fail_count'] ?? 0)) === 0
                    );
                    return 0;
                }

                continue;
            }

            $task = $state['tasks'][$index] ?? null;
            if (! is_array($task)) {
                return 0;
            }
            $sku = (string) ($task['sku'] ?? '');
            $price = (float) ($task['price'] ?? 0);

            $store->update(function (array $state) use ($index, $sku) {
                $state['current_index'] = $index;
                $state['current_sku'] = $sku;
                if (isset($state['tasks'][$index]) && is_array($state['tasks'][$index])) {
                    $state['tasks'][$index]['status'] = 'pushing';
                    $state['tasks'][$index]['attempts'] = ((int) ($state['tasks'][$index]['attempts'] ?? 0)) + 1;
                }

                return $state;
            });

            $logger->info('S PRC background push', [
                'channel' => $this->channel,
                'sku' => $sku,
                'price' => $price,
            ]);

            $ok = false;
            $error = null;
            $live = null;
            $alreadyLive = false;
            $negSnroi = false;
            $dupSkip = false;
            $pendingReview = false;
            try {
                if ($sku === '' || ! ($price > 0)) {
                    throw new \RuntimeException('SKU and S PRC > 0 required');
                }
                if (EbaySpricePush::supports($this->channel)
                    && EbaySpricePush::alreadyPushedInJob($store->load()['tasks'] ?? [], $index, $sku, $price)) {
                    $ok = true;
                    $dupSkip = true;
                    $live = round($price, 2);
                    EbaySpricePush::storeAcceptedPriceFor($this->channel, $sku, $live);
                    $store->appendMessage('Skip '.$sku.': already pushed at $'.number_format($price, 2));
                } elseif ($this->channel === 'newtemuone'
                    ? NegativeSnroiPushGuard::shouldSkipTemuPromoBase(
                        $this->channel,
                        $sku,
                        $price,
                        app(\App\Http\Controllers\MarketPlace\NewTemuoneController::class)->standardPriceForSku($sku)
                    )
                    : NegativeSnroiPushGuard::shouldSkip($this->channel, $sku, $price)) {
                    $ok = true;
                    $negSnroi = true;
                    $logger->info('S PRC background push skipped — negative SNROI', [
                        'channel' => $this->channel,
                        'sku' => $sku,
                        'price' => $price,
                    ]);
                    $store->appendMessage('Skip '.$sku.': negative SNROI');
                    if ($this->channel === 'newtemuone') {
                        NewTemuoneSuggestedPriceStore::markSkipped($sku, $price, 'negative_snroi');
                    }
                } else {
                $matchedLive = $this->liveListingPriceIfMatches($sku, $price);
                if ($matchedLive !== null) {
                    $ok = true;
                    $alreadyLive = true;
                    $live = $matchedLive;
                    if ($this->channel === 'newtemuone') {
                        NewTemuoneSuggestedPriceStore::markSkipped($sku, $price, 'already_live');
                    }
                    $logger->info('S PRC background push skipped — Price already = S PRC', [
                        'channel' => $this->channel,
                        'sku' => $sku,
                        'price' => $matchedLive,
                    ]);
                } else {
                    if ($this->channel === 'purchasing_power') {
                        $block = \App\Http\Controllers\MarketPlace\PurchasingPowerController::pricePushBlockReason($sku);
                        if ($block !== null) {
                            throw new \RuntimeException($block);
                        }
                    }
                    if (in_array($this->channel, ['macys', 'macy'], true)) {
                        $block = \App\Http\Controllers\MarketPlace\MacyController::pricePushBlockReason($sku);
                        if ($block !== null) {
                            throw new \RuntimeException($block);
                        }
                    }
                    if ($this->channel === 'bestbuy') {
                        $block = \App\Http\Controllers\MarketPlace\BestBuyPricingController::pricePushBlockReason($sku);
                        if ($block !== null) {
                            throw new \RuntimeException($block);
                        }
                    }
                    if (in_array($this->channel, ['macys', 'macy', 'purchasing_power', 'bestbuy', 'newegg'], true)) {
                        $floored = MacysAmazonPriceCap::capForSku(
                            $sku,
                            $price,
                            in_array($this->channel, ['macys', 'macy', 'purchasing_power'], true)
                        );
                        if ($floored > 0 && abs($floored - $price) >= 0.005) {
                            $logger->info('S PRC raised to Amazon price', [
                                'channel' => $this->channel,
                                'sku' => $sku,
                                'requested' => $price,
                                'floored' => $floored,
                            ]);
                            $price = $floored;
                        }
                    }
                    $pushRes = $this->pushPrice($sku, $price);
                    $payload = method_exists($pushRes, 'getData') ? $pushRes->getData(true) : [];
                    $status = method_exists($pushRes, 'getStatusCode') ? $pushRes->getStatusCode() : 200;
                    if ($status >= 400 || (is_array($payload) && (
                        isset($payload['success']) && $payload['success'] === false
                        || ! empty($payload['errors'])
                    ))) {
                        $error = is_array($payload)
                            ? (string) (($payload['errors'][0]['message'] ?? null) ?: ($payload['message'] ?? 'S PRC push failed'))
                            : 'S PRC push failed';
                        throw new \RuntimeException($error);
                    }
                    $live = is_array($payload)
                        ? ($payload['ebay_price'] ?? $payload['price'] ?? $price)
                        : $price;
                    $ok = true;
                    $listPriceError = is_array($payload) ? ($payload['data']['list_price_error'] ?? null) : null;
                    if ($listPriceError) {
                        $store->appendMessage('Cross price '.$sku.': '.$listPriceError);
                    }
                    if ($this->channel === 'alibaba') {
                        // Push and pull together, same as Amazon: record the push and read the live price now.
                        $this->alibabaPullAfterPush($sku, (float) $price);
                    }
                    if (in_array($this->channel, ['tiktok', 'tiktok2', 'doba', 'doba_withoutship'], true)
                        && (! is_numeric($live) || abs((float) $live - (float) $price) >= 0.05)) {
                        $pulled = $this->pullLivePriceAfterPush($sku, (float) $price);
                        if ($pulled > 0) {
                            $live = $pulled;
                        }
                    }
                    if (EbaySpricePush::supports($this->channel)) {
                        $live = $this->pullEbayLivePrice($sku, (float) $price, is_numeric($live) ? (float) $live : (float) $price);
                    }
                    $stamp = (float) $price;
                    if ($this->channel === 'newtemuone') {
                        $full = \App\Services\TemuShopifySalesService::computeFullTemuPrice($stamp);
                        NewTemuoneSuggestedPriceStore::markPushed($sku, $stamp, $full);
                    } elseif ($this->channel === 'newtemutwo') {
                        $full = \App\Services\TemuShopifySalesService::computeFullTemuPrice($stamp);
                        NewTemutwoSuggestedPriceStore::markPushed($sku, $stamp, $full);
                    } else {
                        ChannelLivePriceSync::confirmAfterPush($this->channel, $sku, $stamp);
                    }
                    if ($this->channel === 'topdawg') {
                        // TopDawg shows the pushed price in TD Price for the review window.
                        $live = $stamp;
                    }
                }
                }
            } catch (\Throwable $e) {
                $ok = false;
                $error = $e->getMessage();
                $logger->error('S PRC background push failed', [
                    'channel' => $this->channel,
                    'sku' => $sku,
                    'error' => $error,
                ]);
                $this->markListingEndedIfNeeded($sku, $error);
                if ($this->channel === 'newtemuone' && $sku !== '' && $price > 0
                    && preg_match('/review your last application/i', (string) $error)) {
                    // Temu still has the previous price change under review ("Please wait for Temu to review your last
                    // application."). Nothing is wrong and nothing can be sent now: hold the SKU, do not report a failure.
                    $ok = true;
                    $pendingReview = true;
                    $logger->info('S PRC background push held — Temu is still reviewing the last price change', [
                        'channel' => $this->channel,
                        'sku' => $sku,
                        'price' => $price,
                    ]);
                    try {
                        NewTemuoneSuggestedPriceStore::markSkipped($sku, $price, 'pending_review');
                    } catch (\Throwable) {
                        // ignore
                    }
                } elseif ($this->channel === 'newtemuone' && $sku !== '' && $price > 0) {
                    // Not retried at the same price by the 10-minute job for a few hours.
                    try {
                        NewTemuoneSuggestedPriceStore::markSkipped($sku, $price, 'failed');
                    } catch (\Throwable) {
                        // ignore
                    }
                }
                if (EbaySpricePush::supports($this->channel) && $sku !== '' && $price > 0) {
                    // A SKU eBay refuses (e.g. multi-variation) is not retried on every 10-minute catch-up.
                    EbaySpricePush::startCooldown($this->channel, $sku, (float) $price, 60);
                }
            }

            if ($this->channel === 'alibaba' && $sku !== '') {
                // The page shows this on the red X of Push Prc.
                if ($ok) {
                    \App\Support\AlibabaPushError::forget($sku);
                } else {
                    \App\Support\AlibabaPushError::remember($sku, (string) ($error ?: 'Push failed'));
                }
            }

            $store->update(function (array $state) use ($index, $sku, $ok, $error, $live, $alreadyLive, $negSnroi, $dupSkip, $pendingReview) {
                if (! isset($state['tasks'][$index]) || ! is_array($state['tasks'][$index])) {
                    return $state;
                }
                if ($ok) {
                    $state['tasks'][$index]['status'] = 'ok';
                    $state['tasks'][$index]['error'] = null;
                    $state['tasks'][$index]['message'] = $negSnroi
                        ? 'negative SNROI'
                        : ($pendingReview ? 'waiting for Temu review' : ($dupSkip ? 'already pushed' : ($alreadyLive ? 'already live' : 'pushed')));
                    if ($live !== null) {
                        $state['tasks'][$index]['ebay_price'] = $live;
                    }
                    $state['ok_count'] = ((int) ($state['ok_count'] ?? 0)) + 1;
                } else {
                    $state['tasks'][$index]['status'] = 'failed';
                    $state['tasks'][$index]['error'] = $error ?: 'Push failed';
                    $state['tasks'][$index]['message'] = $error ?: 'Push failed';
                    $state['fail_count'] = ((int) ($state['fail_count'] ?? 0)) + 1;
                }
                $state['results'][$sku] = [
                    'success' => $ok,
                    'sku' => $sku,
                    'error' => $ok ? null : ($error ?: 'Push failed'),
                    'ebay_price' => $live,
                ];
                $done = ((int) ($state['ok_count'] ?? 0)) + ((int) ($state['fail_count'] ?? 0));
                $total = (int) ($state['total'] ?? 0);
                $state['last_message'] = ($ok ? 'OK' : 'Fail')." {$sku} — {$done}/{$total}";
                $state['current_sku'] = null;

                return $state;
            });

            if (! $dupSkip) { // the duplicate-push skip already appended its own message above
                $store->appendMessage(
                    ($ok ? (($negSnroi || $alreadyLive || $pendingReview) ? 'Skip ' : 'OK ') : 'Fail ').$sku
                        .($negSnroi ? ': negative SNROI' : ($pendingReview ? ': waiting for Temu to review the last price change' : ($alreadyLive ? ': Price already = S PRC' : ($error ? (': '.$error) : '')))),
                    $ok
                );
            }
            usleep($this->pauseAfterPushMicros());
        }
    }

    private const PP_BATCH_SIZE = 25;

    /** A SKU the MCM rate limit keeps deferring is reported as failed after this many attempts. */
    private const PP_MAX_RATE_LIMIT_ATTEMPTS = 4;

    /**
     * Push up to PP_BATCH_SIZE pending Purchasing Power tasks in one MCM import. Returns true when it finished at least
     * one task. Tasks it does not take (bad SKU/price, negative SNROI, fewer than 2 waiting) stay pending for the
     * one-by-one path, which reports them.
     */
    private function pushPurchasingPowerBatch(ChannelPushSpriceJobStore $store, \Psr\Log\LoggerInterface $logger): bool
    {
        $state = $store->load();
        $picked = [];
        foreach ($state['tasks'] ?? [] as $i => $task) {
            if (count($picked) >= self::PP_BATCH_SIZE) {
                break;
            }
            if (! is_array($task) || ! in_array((string) ($task['status'] ?? 'pending'), ['pending', 'queued'], true)) {
                continue;
            }
            $sku = trim((string) ($task['sku'] ?? ''));
            $price = (float) ($task['price'] ?? 0);
            if ($sku === '' || ! ($price > 0)) {
                continue;
            }
            if (NegativeSnroiPushGuard::shouldSkip($this->channel, $sku, $price)) {
                continue;
            }
            $floored = MacysAmazonPriceCap::capForSku($sku, $price, true);
            if ($floored > 0 && abs($floored - $price) >= 0.005) {
                $price = $floored;
            }
            $picked[(int) $i] = ['sku' => $sku, 'price' => $price];
        }
        if (count($picked) < 2) {
            return false;
        }

        $store->update(function (array $state) use ($picked) {
            foreach ($picked as $i => $_) {
                if (isset($state['tasks'][$i]) && is_array($state['tasks'][$i])) {
                    $state['tasks'][$i]['status'] = 'pushing';
                    $state['tasks'][$i]['attempts'] = ((int) ($state['tasks'][$i]['attempts'] ?? 0)) + 1;
                }
            }
            $state['current_sku'] = null;

            return $state;
        });
        $logger->info('S PRC background batch push', ['channel' => $this->channel, 'count' => count($picked)]);

        try {
            $results = app(CvrMasterController::class)->pushPurchasingPowerBatch(array_values($picked));
        } catch (\Throwable $e) {
            // Put them back so the one-by-one path pushes them and reports each SKU.
            $logger->error('S PRC background batch push failed', ['channel' => $this->channel, 'error' => $e->getMessage()]);
            $store->update(function (array $state) use ($picked) {
                foreach ($picked as $i => $_) {
                    if (isset($state['tasks'][$i]) && is_array($state['tasks'][$i])
                        && ($state['tasks'][$i]['status'] ?? '') === 'pushing') {
                        $state['tasks'][$i]['status'] = 'pending';
                    }
                }

                return $state;
            });

            return false;
        }

        $outcome = [];
        foreach ($picked as $i => $p) {
            $res = $results[$p['sku']] ?? ['success' => false, 'price' => $p['price'], 'message' => 'No result from batch push'];
            $ok = ! empty($res['success']);
            $error = $ok ? null : ((string) ($res['message'] ?? '') ?: 'Push failed');
            $live = round((float) ($res['price'] ?? $p['price']), 2);
            if ($ok) {
                try {
                    ChannelLivePriceSync::confirmAfterPush($this->channel, $p['sku'], $live);
                } catch (\Throwable $e) {
                    $logger->warning('S PRC batch confirmAfterPush failed', ['sku' => $p['sku'], 'error' => $e->getMessage()]);
                }
            } elseif (! empty($res['retryable'])) {
                // MCM rate limit (HTTP 429): the SKU was not judged. Re-queued below, not counted as failed.
                $logger->warning('S PRC batch push deferred — MCM rate limit', ['channel' => $this->channel, 'sku' => $p['sku']]);
            } else {
                $logger->error('S PRC background push failed', ['channel' => $this->channel, 'sku' => $p['sku'], 'error' => $error]);
                $this->markListingEndedIfNeeded($p['sku'], $error);
            }
            $outcome[$i] = ['sku' => $p['sku'], 'ok' => $ok, 'error' => $error, 'live' => $live, 'retry' => ! $ok && ! empty($res['retryable'])];
        }

        $requeued = [];
        $store->update(function (array $state) use ($outcome, &$requeued) {
            $requeued = [];
            foreach ($outcome as $i => $o) {
                if (! isset($state['tasks'][$i]) || ! is_array($state['tasks'][$i])) {
                    continue;
                }
                if ($o['retry'] && ((int) ($state['tasks'][$i]['attempts'] ?? 1)) < self::PP_MAX_RATE_LIMIT_ATTEMPTS) {
                    $state['tasks'][$i]['status'] = 'pending';
                    $state['tasks'][$i]['error'] = null;
                    $state['tasks'][$i]['message'] = 'rate limited — retrying';
                    $requeued[$o['sku']] = true;

                    continue;
                }
                if ($o['ok']) {
                    $state['tasks'][$i]['status'] = 'ok';
                    $state['tasks'][$i]['error'] = null;
                    $state['tasks'][$i]['message'] = 'pushed';
                    $state['tasks'][$i]['ebay_price'] = $o['live'];
                    $state['ok_count'] = ((int) ($state['ok_count'] ?? 0)) + 1;
                } else {
                    $state['tasks'][$i]['status'] = 'failed';
                    $state['tasks'][$i]['error'] = $o['error'];
                    $state['tasks'][$i]['message'] = $o['error'];
                    $state['fail_count'] = ((int) ($state['fail_count'] ?? 0)) + 1;
                }
                $state['results'][$o['sku']] = [
                    'success' => $o['ok'],
                    'sku' => $o['sku'],
                    'error' => $o['ok'] ? null : $o['error'],
                    'ebay_price' => $o['live'],
                ];
            }
            $done = ((int) ($state['ok_count'] ?? 0)) + ((int) ($state['fail_count'] ?? 0));
            $state['last_message'] = 'Batch of '.count($outcome)." — {$done}/".(int) ($state['total'] ?? 0);
            $state['current_sku'] = null;

            return $state;
        });
        foreach ($outcome as $o) {
            if (isset($requeued[$o['sku']])) {
                continue; // re-queued, summarised below
            }
            $store->appendMessage(($o['ok'] ? 'OK ' : 'Fail ').$o['sku'].($o['error'] ? (': '.$o['error']) : ''), $o['ok']);
        }
        if ($requeued !== []) {
            $store->appendMessage('MCM rate limit — '.count($requeued).' SKU(s) re-queued, retrying shortly', false);
            // Give the MCM rate-limit window time to clear before the next batch.
            sleep(20);
        }

        return true;
    }

    /**
     * business5core.com throttles listing writes. One B2B push about every
     * 1.5s stays under that limit; a 429 still waits inside the store client.
     */
    private function pauseAfterPushMicros(): int
    {
        if (in_array($this->channel, ['macys', 'macy'], true)) {
            return 50000;
        }
        if ($this->channel === 'shopify_b2b') {
            return 1500000;
        }

        return 250000;
    }

    /**
     * eBay accepted the revise. Pull the listing's real price (GetItem) and store it, so the Price column is what
     * eBay has. When eBay still shows another price, hold this SKU/price for a few hours instead of re-pushing it
     * on every 10-minute catch-up.
     */
    private function pullEbayLivePrice(string $sku, float $pushed, float $fallback): float
    {
        try {
            $rows = app(\App\Services\ChannelPushedPricePullService::class)
                ->pullSkus($this->channel, [$sku], [strtoupper(trim($sku)) => $pushed]);
        } catch (\Throwable $e) {
            Log::warning('eBay live price pull after S PRC push failed', [
                'channel' => $this->channel,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return $fallback;
        }
        $row = $rows[0] ?? [];
        $pulled = (float) ($row['price'] ?? 0);
        if (empty($row['success']) || ! ($pulled > 0)) {
            return $fallback;
        }
        if (abs($pulled - $pushed) > 0.05) {
            EbaySpricePush::startCooldown($this->channel, $sku, $pushed);
            Log::warning('eBay price still differs after S PRC push', [
                'channel' => $this->channel,
                'sku' => $sku,
                'pushed' => $pushed,
                'live' => $pulled,
            ]);
        }

        return round($pulled, 2);
    }

    /**
     * Alibaba accepted the price. Save the push (so the catch-up holds it while Alibaba reviews it and the page
     * keeps the green mark after a reload), then pull the live price. alibaba:pull-pushed-prices re-reads it later.
     */
    private function alibabaPullAfterPush(string $sku, float $pushed): void
    {
        if ($sku === '' || ! ($pushed > 0)) {
            return;
        }
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('alibaba_pricing_prices')
                && \Illuminate\Support\Facades\Schema::hasColumn('alibaba_pricing_prices', 'sprice_pushed_at')) {
                $now = now()->toDateTimeString();
                $row = \App\Models\AlibabaPricingPrice::query()
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
                    ->first() ?? new \App\Models\AlibabaPricingPrice(['sku' => trim($sku)]);
                $row->fill([
                    'sprice' => round($pushed, 2),
                    'sprice_status' => 'pushed',
                    'sprice_status_at' => $now,
                    'sprice_pushed_value' => round($pushed, 2),
                    'sprice_pushed_at' => $now,
                ]);
                $row->save();
            }
            app(\App\Services\AlibabaPushedPricePullService::class)->afterPush($sku, $pushed);
        } catch (\Throwable $e) {
            Log::warning('Alibaba pull after S PRC push failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
    }

    private function pullLivePriceAfterPush(string $sku, float $expected): float
    {
        $last = 0.0;
        foreach ([400000, 1200000, 2000000] as $waitUs) {
            usleep($waitUs);
            try {
                $rows = app(\App\Services\ChannelPushedPricePullService::class)
                    ->pullSkus($this->channel, [$sku]);
            } catch (\Throwable $e) {
                Log::warning('Live price pull after S PRC push failed', [
                    'channel' => $this->channel,
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
            $price = (float) ($rows[0]['price'] ?? 0);
            if ($price > 0) {
                $last = round($price, 2);
                if (abs($last - $expected) <= 0.05) {
                    return round($expected, 2);
                }
            }
        }

        return $last;
    }

    private function markListingEndedIfNeeded(string $sku, ?string $error): void
    {
        if (! \App\Support\Marketplace\EbayListingEnded::looksEndedError($error)) {
            return;
        }
        $class = match ($this->channel) {
            'ebay2', 'ebay2op' => \App\Models\Ebay2Metric::class,
            'ebay3' => \App\Models\Ebay3Metric::class,
            'ebay1' => \App\Models\EbayMetric::class,
            default => null,
        };
        if (! $class) {
            return;
        }
        try {
            $row = \App\Support\Marketplace\EbayListingEnded::preferredRow($class, $sku);
            if (! $row) {
                return;
            }
            $row->listing_status = 'ENDED';
            if (empty($row->inactive_reason)) {
                $row->inactive_reason = 'Ended listing';
            }
            $row->save();
        } catch (\Throwable) {
            // ignore
        }
    }

    private function findNextPendingIndex(array $tasks): ?int
    {
        foreach ($tasks as $i => $task) {
            if (! is_array($task)) {
                continue;
            }
            $st = (string) ($task['status'] ?? 'pending');
            if (in_array($st, ['pending', 'queued'], true)) {
                return (int) $i;
            }
        }

        return null;
    }

    private function liveListingPriceIfMatches(string $sku, float $price): ?float
    {
        if (EbaySpricePush::supports($this->channel)) {
            return EbaySpricePush::liveMatches(EbaySpricePush::metric($this->channel, $sku), $price)
                ? round($price, 2)
                : null;
        }
        if (! in_array($this->channel, ['shopify_b2c', 'shopify_b2b'], true)) {
            return null;
        }

        $row = ShopifySku::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
            ->first(['price', 'b2c_price', 'b2b_price']);
        if (! $row) {
            return null;
        }

        $live = $this->channel === 'shopify_b2b'
            ? (float) (($row->b2b_price ?? 0) ?: ($row->price ?? 0))
            : (float) (($row->price ?? 0) ?: ($row->b2c_price ?? 0));

        if ($live > 0 && abs($live - $price) < 0.005) {
            return round($live, 2);
        }

        return null;
    }

    private function pushPrice(string $sku, float $price)
    {
        $pushPrice = $price;
        if (EbaySpricePush::supports($this->channel)) {
            $res = EbaySpricePush::push($this->channel, $sku, $pushPrice);

            return response()->json($res, ! empty($res['success']) ? 200 : 422);
        }

        if ($this->channel === 'alibaba') {
            $alibabaReq = Request::create('/alibaba-analytics/push-price', 'POST', [
                'sku' => $sku,
                'price' => $pushPrice,
            ]);
            $alibabaReq->headers->set('Accept', 'application/json');

            return app(AlibabaAnalyticsController::class)->pushPrice($alibabaReq, app(AlibabaApiService::class));
        }

        if ($this->channel === 'aliexpress') {
            $aeReq = Request::create('/aliexpress/pricing-push-price', 'POST', [
                'updates' => [
                    ['sku' => $sku, 'price' => $pushPrice],
                ],
            ]);
            $aeReq->headers->set('Accept', 'application/json');

            return app(AliexpressController::class)->pushPricingPrice($aeReq, app(AliExpressApiService::class));
        }

        if ($this->channel === 'shein') {
            $sheinReq = Request::create('/shein/pricing-push-price', 'POST', [
                'updates' => [
                    ['sku' => $sku, 'price' => $pushPrice],
                ],
            ]);
            $sheinReq->headers->set('Accept', 'application/json');

            return app(SheinController::class)->pushPricingPrice($sheinReq, app(SheinApiService::class));
        }

        if (in_array($this->channel, ['macys', 'macy'], true)) {
            $macysReq = Request::create('/macys-push-price', 'POST', [
                'sku' => $sku,
                'price' => $pushPrice,
            ]);

            return app(MacyController::class)->pushPriceTabulator($macysReq);
        }

        if ($this->channel === 'newegg') {
            $neweggReq = Request::create('/newegg-pricing-push', 'POST', [
                'sku' => $sku,
                'price' => $pushPrice,
            ]);

            return app(NeweggPricingController::class)->pushPriceToNewegg($neweggReq, app(NeweggApiService::class));
        }

        if ($this->channel === 'newtemuone') {
            $temuParams = [
                'sku' => $sku,
                'price' => $pushPrice,
                'skip_local_base' => 1,
            ];
            // Cross (strikethrough) price = Std Prc, base price = S Promo Base Prc.
            $std = app(\App\Http\Controllers\MarketPlace\NewTemuoneController::class)->standardPriceForSku($sku);
            if ($std !== null && $std > 0) {
                $temuParams['list_price'] = $std;
            }
            $temuReq = Request::create('/temu/push-price', 'POST', $temuParams);

            return app(TemuController::class)->pushTemuPrice($temuReq, app(TemuApiService::class));
        }

        if ($this->channel === 'newtemutwo') {
            $temuReq = Request::create('/temu2/push-price', 'POST', [
                'sku' => $sku,
                'price' => $pushPrice,
                'skip_local_base' => 1,
            ]);

            return app(TemuController::class)->pushTemu2Price($temuReq, app(Temu2ApiService::class));
        }

        if (in_array($this->channel, ['temu', 'temu2', 'temu3'], true)) {
            // Temu 3 Analytics pushes S Base Prc (S PRC × 0.88, − $2.99 under $30). Temu 1 / 2 keep the Temu Price inverse.
            $base = $this->channel === 'temu3'
                ? \App\Services\TemuShopifySalesService::newTemuoneSBasePrice($price)
                : \App\Services\TemuShopifySalesService::computePushBaseFromSprice($price);
            if ($base !== null && $base > 0) {
                $pushPrice = $base;
            }
            if ($this->channel === 'temu3') {
                $temu3Req = Request::create('/temu3/push-price', 'POST', [
                    'sku' => $sku,
                    'price' => $pushPrice,
                ]);

                return app(Temu3Controller::class)->pushTemu3Price($temu3Req);
            }
            $temuReq = Request::create(
                $this->channel === 'temu2' ? '/temu2/push-price' : '/temu/push-price',
                'POST',
                ['sku' => $sku, 'price' => $pushPrice]
            );

            return $this->channel === 'temu2'
                ? app(TemuController::class)->pushTemu2Price($temuReq, app(Temu2ApiService::class))
                : app(TemuController::class)->pushTemuPrice($temuReq, app(TemuApiService::class));
        }

        // Prepaid / pickup page: update Doba Pick Up only (selfPickAnticipatedIncome).
        // Must not send anticipatedIncome — that is the Delivery field on seller.doba.com.
        if ($this->channel === 'doba_withoutship') {
            $pickupReq = Request::create('/doba/push-price', 'POST', [
                'sku' => $sku,
                'mode' => 'pickup',
                'self_pick_price' => $pushPrice,
            ]);

            return app(DobaController::class)->pushPriceToDoba($pickupReq);
        }

        $req = Request::create('/channel-push-sprice-push', 'POST', [
            'sku' => $sku,
            'price' => $pushPrice,
        ]);

        $cvrMarket = [
            'shopify_b2b' => 'shopifyb2b',
            'reverb' => 'reverb',
            'walmart' => 'walmart',
            'macys' => 'macys',
            'macy' => 'macys',
            'bestbuy' => 'bestbuy',
            'doba' => 'doba',
            'tiktok' => 'tiktok',
            'tiktok2' => 'tiktok2',
            'topdawg' => 'topdawg',
            'purchasing_power' => 'purchasingpower',
            'wayfair' => 'wayfair',
            'faire' => 'faire',
            'pls' => 'pls',
            'aliexpress' => 'aliexpress',
            'shein' => 'shein',
        ][$this->channel] ?? null;

        if ($cvrMarket) {
            return app(CvrMasterController::class)->pushPriceToAmazon(Request::create('/cvr-master-push-price', 'POST', [
                'sku' => $sku,
                'price' => $pushPrice,
                'marketplace' => $cvrMarket,
            ]));
        }

        return match ($this->channel) {
            'shopify_b2c' => app(OverallAmazonController::class)->pushShopifyB2CPrice($req),
            default => throw new \RuntimeException('Live S PRC push is not available for '.$this->channel),
        };
    }
}
