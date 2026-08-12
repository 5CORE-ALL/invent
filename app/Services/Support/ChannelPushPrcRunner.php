<?php

namespace App\Services\Support;

use App\Http\Controllers\MarketPlace\EbayController;
use App\Http\Controllers\MarketPlace\EbayThreeController;
use App\Http\Controllers\MarketPlace\EbayTwoController;
use App\Services\ChannelPromoPricingService;
use App\Services\Ebay1CouponService;
use App\Services\Ebay1PromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Background channel Push Prc worker.
 *
 * 1) Push Std Prc as the live listing StartPrice
 * 2) Create/update sale event from PRMT % (eBay1 ORDER_DISCOUNT)
 * 3) Create/add coded coupon campaign from CPN % (eBay1 CODED_COUPON)
 *
 * Local S PRC (Std − PRMT%) is not overwritten. Same queue pattern as AmazonPushPrcRunner.
 */
class ChannelPushPrcRunner
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

    public function run(): int
    {
        @set_time_limit(0);

        $store = ChannelPushPrcJobStore::for($this->channel);
        $logger = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/'.$this->channel.'-push-prc.log'),
            'level' => 'debug',
        ]);

        $lockPath = storage_path('app/'.$this->channel.'-push-prc/runner.lock');
        $lockDir = dirname($lockPath);
        if (! is_dir($lockDir)) {
            @mkdir($lockDir, 0755, true);
        }
        $lockHandle = @fopen($lockPath, 'c+');
        if (! $lockHandle || ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            $logger->info('Channel Push Prc runner skipped — another process holds the lock', [
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

    private function runLocked(ChannelPushPrcJobStore $store, \Psr\Log\LoggerInterface $logger): int
    {
        $promo = app(ChannelPromoPricingService::class);

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
            $std = (float) ($task['std'] ?? $task['effective'] ?? 0);
            $prmt = max(0, (float) ($task['prmt'] ?? 0));
            $cpn = max(0, (float) ($task['cpn'] ?? 0));

            $store->update(function (array $state) use ($index, $sku) {
                $state['current_index'] = $index;
                $state['current_sku'] = $sku;
                if (isset($state['tasks'][$index]) && is_array($state['tasks'][$index])) {
                    $state['tasks'][$index]['status'] = 'pushing';
                    $state['tasks'][$index]['attempts'] = ((int) ($state['tasks'][$index]['attempts'] ?? 0)) + 1;
                }
                $done = ((int) ($state['ok_count'] ?? 0)) + ((int) ($state['fail_count'] ?? 0));
                $total = (int) ($state['total'] ?? 0);
                $state['last_message'] = 'Pushing '.($done + 1).'/'.$total.': '.$sku;

                return $state;
            });

            $logger->info('Channel Push Prc: 3-step push', [
                'channel' => $this->channel,
                'sku' => $sku,
                'std' => $std,
                'prmt' => $prmt,
                'cpn' => $cpn,
            ]);

            $ok = false;
            $error = null;
            try {
                if (! ($std > 0)) {
                    throw new \RuntimeException('Std Prc required for Push Prc');
                }

                $promo->upsert($this->channel, $sku, [
                    'prmt_pct' => $prmt,
                    'cpn_pct' => $cpn,
                    'push_prc_status' => 'pushed',
                    'push_prc_value' => $std,
                    'push_prc_pushed_at' => now()->toDateTimeString(),
                ]);

                // 1) Push Std Prc as listing StartPrice
                $pushRes = $this->pushPrice($sku, $std);
                $payload = method_exists($pushRes, 'getData') ? $pushRes->getData(true) : [];
                $status = method_exists($pushRes, 'getStatusCode') ? $pushRes->getStatusCode() : 200;
                if ($status >= 400 || (is_array($payload) && ! empty($payload['errors']))) {
                    $error = is_array($payload)
                        ? (string) (($payload['errors'][0]['message'] ?? null) ?: ($payload['message'] ?? 'Std Prc push failed'))
                        : 'Std Prc push failed';
                    throw new \RuntimeException($error);
                }

                $stepErrors = [];
                if ($this->channel === 'ebay1') {
                    // 2) Sale event from PRMT %
                    $saleRes = $this->syncEbay1Sale($sku, $prmt, $logger);
                    if (empty($saleRes['success'])) {
                        $stepErrors[] = 'Sale: '.((string) ($saleRes['message'] ?? 'failed'));
                    }
                    // 3) Coupon campaign from CPN %
                    $cpnRes = $this->syncEbay1Coupon($sku, $cpn, $logger);
                    if (empty($cpnRes['success'])) {
                        $stepErrors[] = 'Coupon: '.((string) ($cpnRes['message'] ?? 'failed'));
                    }
                }

                if ($stepErrors !== []) {
                    throw new \RuntimeException(implode(' | ', $stepErrors));
                }
                $ok = true;
            } catch (\Throwable $e) {
                $ok = false;
                $error = $e->getMessage();
                $logger->error('Channel Push Prc exception', [
                    'channel' => $this->channel,
                    'sku' => $sku,
                    'error' => $error,
                ]);
                try {
                    $promo->upsert($this->channel, $sku, [
                        'push_prc_status' => 'error',
                        'push_prc_value' => $std > 0 ? $std : null,
                    ]);
                } catch (\Throwable) {
                    // ignore
                }
            }

            $store->update(function (array $state) use ($index, $sku, $ok, $error) {
                if (! isset($state['tasks'][$index]) || ! is_array($state['tasks'][$index])) {
                    return $state;
                }
                if ($ok) {
                    $state['tasks'][$index]['status'] = 'ok';
                    $state['tasks'][$index]['error'] = null;
                    $state['tasks'][$index]['message'] = 'pushed';
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

    /**
     * @return array{success:bool,message?:string}
     */
    private function syncEbay1Sale(string $sku, float $prmt, \Psr\Log\LoggerInterface $logger): array
    {
        try {
            $res = app(Ebay1PromotionService::class)->syncSkuPromotionPercent($sku, $prmt);
            $logger->info('Channel Push Prc: sale event', [
                'sku' => $sku,
                'prmt' => $prmt,
                'success' => ! empty($res['success']),
                'message' => $res['message'] ?? null,
            ]);

            return is_array($res) ? $res : ['success' => false, 'message' => 'Sale event failed'];
        } catch (\Throwable $e) {
            $logger->error('Channel Push Prc: sale event exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{success:bool,message?:string}
     */
    private function syncEbay1Coupon(string $sku, float $cpn, \Psr\Log\LoggerInterface $logger): array
    {
        try {
            $res = app(Ebay1CouponService::class)->syncSkuCouponPercent($sku, $cpn);
            $logger->info('Channel Push Prc: coupon campaign', [
                'sku' => $sku,
                'cpn' => $cpn,
                'success' => ! empty($res['success']),
                'message' => $res['message'] ?? null,
            ]);

            return is_array($res) ? $res : ['success' => false, 'message' => 'Coupon campaign failed'];
        } catch (\Throwable $e) {
            $logger->error('Channel Push Prc: coupon campaign exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function pushPrice(string $sku, float $price)
    {
        $req = Request::create('/channel-push-prc-push', 'POST', [
            'sku' => $sku,
            'price' => $price,
        ]);

        return match ($this->channel) {
            'ebay2', 'ebay2op' => app(EbayTwoController::class)->pushEbay2Price($req),
            'ebay3' => app(EbayThreeController::class)->pushEbay3Price($req),
            default => app(EbayController::class)->pushEbayPrice($req),
        };
    }

    /**
     * @param  list<mixed>  $tasks
     */
    private function findNextPendingIndex(array $tasks): ?int
    {
        foreach ($tasks as $i => $task) {
            if (! is_array($task)) {
                continue;
            }
            if (in_array((string) ($task['status'] ?? ''), ['pending', 'queued'], true)) {
                return (int) $i;
            }
        }

        return null;
    }
}
