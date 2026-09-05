<?php

namespace App\Services\Support;

use App\Http\Controllers\MarketPlace\CvrMasterController;
use App\Http\Controllers\MarketPlace\DobaController;
use App\Http\Controllers\MarketPlace\EbayController;
use App\Http\Controllers\MarketPlace\EbayThreeController;
use App\Http\Controllers\MarketPlace\EbayTwoController;
use App\Http\Controllers\MarketPlace\NeweggPricingController;
use App\Http\Controllers\MarketPlace\OverallAmazonController;
use App\Http\Controllers\MarketPlace\TemuController;
use App\Services\NeweggApiService;
use App\Services\TemuApiService;
use App\Services\Temu2ApiService;
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
        while (true) {
            $state = $store->load();
            if (($state['status'] ?? 'idle') !== 'running') {
                return 0;
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
            try {
                if ($sku === '' || ! ($price > 0)) {
                    throw new \RuntimeException('SKU and S PRC > 0 required');
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
            } catch (\Throwable $e) {
                $ok = false;
                $error = $e->getMessage();
                $logger->error('S PRC background push failed', [
                    'channel' => $this->channel,
                    'sku' => $sku,
                    'error' => $error,
                ]);
                $this->markListingEndedIfNeeded($sku, $error);
            }

            $store->update(function (array $state) use ($index, $sku, $ok, $error, $live) {
                if (! isset($state['tasks'][$index]) || ! is_array($state['tasks'][$index])) {
                    return $state;
                }
                if ($ok) {
                    $state['tasks'][$index]['status'] = 'ok';
                    $state['tasks'][$index]['error'] = null;
                    $state['tasks'][$index]['message'] = 'pushed';
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

            $store->appendMessage(($ok ? 'OK ' : 'Fail ').$sku.($error ? (': '.$error) : ''), $ok);
            usleep(250000);
        }
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

    private function pushPrice(string $sku, float $price)
    {
        $pushPrice = $price;
        if ($this->channel === 'newegg') {
            $neweggReq = Request::create('/newegg-pricing-push', 'POST', [
                'sku' => $sku,
                'price' => $pushPrice,
            ]);

            return app(NeweggPricingController::class)->pushPriceToNewegg($neweggReq, app(NeweggApiService::class));
        }

        if (in_array($this->channel, ['temu', 'temu2'], true)) {
            $base = \App\Services\TemuShopifySalesService::computePushBaseFromSprice($price);
            if ($base !== null && $base > 0) {
                $pushPrice = $base;
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
            'ebay1' => app(EbayController::class)->pushEbayPrice($req),
            'ebay2', 'ebay2op' => app(EbayTwoController::class)->pushEbay2Price($req),
            'ebay3' => app(EbayThreeController::class)->pushEbay3Price($req),
            'shopify_b2c' => app(OverallAmazonController::class)->pushShopifyB2CPrice($req),
            default => throw new \RuntimeException('Live S PRC push is not available for '.$this->channel),
        };
    }
}
