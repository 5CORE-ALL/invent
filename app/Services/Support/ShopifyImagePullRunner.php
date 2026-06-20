<?php

namespace App\Services\Support;

use App\Http\Controllers\ProductMaster\ImageMasterController;
use Illuminate\Http\Request;

class ShopifyImagePullRunner
{
    public function __construct(
        private readonly ShopifyImagePullJobStore $store,
    ) {}

    public function run(): int
    {
        while (true) {
            $state = $this->store->load();
            $status = $state['status'] ?? 'idle';

            if ($status === 'stopping') {
                $this->store->update(function (array $state) {
                    $state['status'] = 'stopped';
                    $state['finished_at'] = now()->toDateTimeString();
                    $state['last_message'] = 'Stopped by user.';

                    return $state;
                });
                $this->store->appendMessage('Stopped by user.', false);

                return 0;
            }

            if ($status === 'paused') {
                sleep(2);
                continue;
            }

            if ($status !== 'running') {
                return 0;
            }

            $index = (int) ($state['current_index'] ?? 0);
            $skus = array_values($state['skus'] ?? []);
            $total = count($skus);

            if ($index >= $total) {
                $this->store->update(function (array $state) {
                    $state['status'] = 'completed';
                    $state['current_sku'] = null;
                    $state['finished_at'] = now()->toDateTimeString();
                    $state['last_message'] = "Completed: {$state['ok_count']} ok, {$state['fail_count']} failed.";

                    return $state;
                });
                $state = $this->store->load();
                $this->store->appendMessage(
                    "Completed: {$state['ok_count']} ok, {$state['fail_count']} failed.",
                    ((int) ($state['fail_count'] ?? 0)) === 0
                );

                return 0;
            }

            $sku = trim((string) ($skus[$index] ?? ''));
            if ($sku === '') {
                $this->advance(false, 'Blank SKU skipped.');
                continue;
            }

            $this->store->update(function (array $state) use ($sku, $index, $total) {
                $state['current_sku'] = $sku;
                $state['last_message'] = 'Pulling '.($index + 1)."/{$total}: {$sku}";

                return $state;
            });

            $ok = false;
            $message = "{$sku}: failed - Unable to pull Shopify images.";
            try {
                $response = app(ImageMasterController::class)->pullShopifyImagesToMaster(new Request(['sku' => $sku]));
                $payload = method_exists($response, 'getData') ? $response->getData(true) : [];
                $ok = (bool) ($payload['success'] ?? false);
                $message = $ok
                    ? "{$sku}: {$payload['status']} - ".count($payload['shopify_images'] ?? []).' image(s) from '.($payload['source'] ?? 'shopify')
                    : "{$sku}: failed - ".($payload['message'] ?? 'Unable to pull Shopify images.');
            } catch (\Throwable $e) {
                $message = "{$sku}: failed - {$e->getMessage()}";
            }

            $this->advance($ok, $message);
            $this->delayBeforeNextSku();
        }
    }

    private function advance(bool $ok, string $message): void
    {
        $this->store->update(function (array $state) use ($ok, $message) {
            $state['current_index'] = ((int) ($state['current_index'] ?? 0)) + 1;
            $state[$ok ? 'ok_count' : 'fail_count'] = ((int) ($state[$ok ? 'ok_count' : 'fail_count'] ?? 0)) + 1;
            $state['last_message'] = $message;

            return $state;
        });
        $this->store->appendMessage($message, $ok);
    }

    private function delayBeforeNextSku(): void
    {
        $delay = max(1, (int) ($this->store->load()['delay_seconds'] ?? 6));
        for ($i = 0; $i < $delay; $i++) {
            $status = $this->store->load()['status'] ?? 'idle';
            if ($status !== 'running') {
                return;
            }
            sleep(1);
        }
    }
}
