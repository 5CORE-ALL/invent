<?php

namespace App\Console\Commands;

use App\Services\AmazonDilPriceCouponPushService;
use App\Services\AmazonSpApiService;
use App\Services\Support\AmazonDilPriceCouponPushStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Background worker for the Promotions "Push to Amazon" button.
 * Works through the tasks the controller queued in AmazonDilPriceCouponPushStore, one SKU at a time.
 */
class RunAmazonDilPriceCouponPush extends Command
{
    protected $signature = 'amazon:dil-price-coupon-push-run {--sleep-ms=400 : Delay between Amazon Listings API calls (ms)}';

    protected $description = 'Push the queued Promotions Amazon Dil % × Price coupons (as discounted prices) to Amazon.';

    public function handle(AmazonDilPriceCouponPushService $service): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $store = new AmazonDilPriceCouponPushStore;
        $state = $store->load();
        if (! $store->isActive($state)) {
            $this->warn('No active Amazon coupon push job. Start one from Promotions → Amazon → Push to Amazon.');

            return self::FAILURE;
        }

        $lockPath = AmazonDilPriceCouponPushStore::lockPath();
        if (! is_dir(dirname($lockPath))) {
            @mkdir(dirname($lockPath), 0755, true);
        }
        $lock = @fopen($lockPath, 'c+');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->warn('Another worker is already running.');
            if ($lock) {
                fclose($lock);
            }

            return self::SUCCESS;
        }

        try {
            return $this->work($service, $store);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function work(AmazonDilPriceCouponPushService $service, AmazonDilPriceCouponPushStore $store): int
    {
        $sleepUs = max(0, (int) $this->option('sleep-ms')) * 1000;
        $api = new AmazonSpApiService;

        while (true) {
            $state = $store->load();
            if (! $store->isActive($state)) {
                return self::SUCCESS; // cancelled
            }

            $index = null;
            foreach ((array) $state['tasks'] as $i => $t) {
                if (is_array($t) && ($t['status'] ?? '') === 'pending') {
                    $index = (int) $i;
                    break;
                }
            }

            if ($index === null) {
                $final = $store->update(function (array $s) {
                    $s['status'] = 'completed';
                    $s['current_sku'] = null;
                    $s['finished_at'] = now()->toDateTimeString();
                    $s['last_message'] = "Completed: {$s['ok_count']} pushed, {$s['fail_count']} failed.";

                    return $s;
                });
                $store->appendMessage($final['last_message'], (int) $final['fail_count'] === 0);

                return self::SUCCESS;
            }

            $task = $state['tasks'][$index];
            $sku = (string) $task['sku'];
            $store->update(function (array $s) use ($index, $sku) {
                $s['tasks'][$index]['status'] = 'pushing';
                $s['current_sku'] = $sku;
                $done = (int) $s['ok_count'] + (int) $s['fail_count'];
                $s['last_message'] = 'Pushing '.($done + 1).'/'.$s['total'].': '.$sku;

                return $s;
            });

            $ok = false;
            $error = null;
            try {
                $res = $service->pushTask($api, $task);
                $ok = $res['ok'];
                $error = $res['error'];
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                Log::error('[AmazonDilPriceCouponPush] exception', ['sku' => $sku, 'error' => $error]);
            }

            $store->update(function (array $s) use ($index, $ok, $error) {
                // Only the task and counters change here; a cancel that landed mid-push keeps its status.
                $s['tasks'][$index]['status'] = $ok ? 'ok' : 'failed';
                $s['tasks'][$index]['error'] = $ok ? null : ($error ?: 'Push failed');
                $s[$ok ? 'ok_count' : 'fail_count'] = (int) $s[$ok ? 'ok_count' : 'fail_count'] + 1;
                $s['current_sku'] = null;
                $done = (int) $s['ok_count'] + (int) $s['fail_count'];
                $s['last_message'] = ($ok ? 'OK' : 'Fail').' — '.$done.'/'.$s['total'];

                return $s;
            });
            if (! $ok) {
                $store->appendMessage('Fail '.$sku.': '.$error, false);
            }

            if ($sleepUs > 0) {
                usleep($sleepUs);
            }
        }
    }
}
