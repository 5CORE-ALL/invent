<?php

namespace App\Console\Commands;

use App\Http\Controllers\ProductMaster\BulletPointMasterController;
use App\Services\Support\ShopifyBulletPullJobStore;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class RunShopifyBulletPull extends Command
{
    protected $signature = 'bullet-points:shopify-pull-run';

    protected $description = 'Run the background Shopify bullet pull into Product Master';

    public function handle(ShopifyBulletPullJobStore $store): int
    {
        while (true) {
            $state = $store->load();
            $status = $state['status'] ?? 'idle';

            if ($status === 'stopping') {
                $store->update(function (array $state) {
                    $state['status'] = 'stopped';
                    $state['finished_at'] = now()->toDateTimeString();
                    $state['last_message'] = 'Stopped by user.';

                    return $state;
                });
                $store->appendMessage('Stopped by user.', false);

                return self::SUCCESS;
            }

            if ($status === 'paused') {
                sleep(2);
                continue;
            }

            if ($status !== 'running') {
                return self::SUCCESS;
            }

            $index = (int) ($state['current_index'] ?? 0);
            $skus = array_values($state['skus'] ?? []);
            $total = count($skus);

            if ($index >= $total) {
                $store->update(function (array $state) {
                    $state['status'] = 'completed';
                    $state['current_sku'] = null;
                    $state['finished_at'] = now()->toDateTimeString();
                    $state['last_message'] = "Completed: {$state['ok_count']} ok, {$state['fail_count']} failed.";

                    return $state;
                });
                $state = $store->load();
                $store->appendMessage("Completed: {$state['ok_count']} ok, {$state['fail_count']} failed.", ((int) ($state['fail_count'] ?? 0)) === 0);

                return self::SUCCESS;
            }

            $sku = trim((string) ($skus[$index] ?? ''));
            if ($sku === '') {
                $this->advance($store, false, 'Blank SKU skipped.');
                continue;
            }

            $store->update(function (array $state) use ($sku, $index, $total) {
                $state['current_sku'] = $sku;
                $state['last_message'] = 'Pulling '.($index + 1)."/{$total}: {$sku}";

                return $state;
            });

            $ok = false;
            $message = "{$sku}: failed - Unable to pull Shopify bullets.";
            try {
                $response = app(BulletPointMasterController::class)->pullShopifyBulletsToMaster(new Request(['sku' => $sku]));
                $payload = method_exists($response, 'getData') ? $response->getData(true) : [];
                $ok = (bool) ($payload['success'] ?? false);
                $message = $ok
                    ? "{$sku}: {$payload['status']} - {$payload['format']}, confidence {$payload['confidence']}% (".count($payload['shopify_bullets'] ?? []).' bullets)'
                    : "{$sku}: failed - ".($payload['message'] ?? 'Unable to pull Shopify bullets.');
            } catch (\Throwable $e) {
                $message = "{$sku}: failed - {$e->getMessage()}";
            }

            $this->advance($store, $ok, $message);
            $this->delayBeforeNextSku($store);
        }
    }

    private function advance(ShopifyBulletPullJobStore $store, bool $ok, string $message): void
    {
        $store->update(function (array $state) use ($ok, $message) {
            $state['current_index'] = ((int) ($state['current_index'] ?? 0)) + 1;
            $state[$ok ? 'ok_count' : 'fail_count'] = ((int) ($state[$ok ? 'ok_count' : 'fail_count'] ?? 0)) + 1;
            $state['last_message'] = $message;

            return $state;
        });
        $store->appendMessage($message, $ok);
    }

    private function delayBeforeNextSku(ShopifyBulletPullJobStore $store): void
    {
        $delay = max(1, (int) ($store->load()['delay_seconds'] ?? 6));
        for ($i = 0; $i < $delay; $i++) {
            $status = $store->load()['status'] ?? 'idle';
            if ($status !== 'running') {
                return;
            }
            sleep(1);
        }
    }
}
