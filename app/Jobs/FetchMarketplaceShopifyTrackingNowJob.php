<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\FetchTrackingNowProgress;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Manual "Fetch tracking now" catch-up. Not unique — a click always runs.
 * Fulfills unfulfilled Shopify copies, then pushes tracking to every marketplace.
 */
class FetchMarketplaceShopifyTrackingNowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public bool $failOnTimeout = false;

    public function __construct(
        public int $limit = 2500,
        public int $trackingLimit = 150,
        public string $runId = '',
    ) {
        if ($this->runId === '') {
            $this->runId = (string) Str::uuid();
        }
        $this->onQueue(MarketplaceManagerRegistry::QUEUE_TRACKING);
    }

    public function handle(VeeqoShopifyFulfillmentService $sync): void
    {
        FetchTrackingNowProgress::update($this->runId, [
            'status' => 'running',
            'percent' => 4,
            'message' => 'Fulfilling unfulfilled Shopify copies…',
        ]);

        $result = [
            'checked' => 0,
            'fulfilled' => 0,
            'skipped' => 0,
            'failed' => 0,
            'message' => '',
        ];

        try {
            $sync->setProgressReporter(function (array $event): void {
                $this->recordShopifyProgress($event);
            });
            $result = $sync->syncPendingUnfulfilled($this->limit, true, true);
            $sync->setProgressReporter(null);
        } catch (\Throwable $e) {
            $sync->setProgressReporter(null);
            Log::error('FetchMarketplaceShopifyTrackingNowJob: shopify fulfill failed', [
                'error' => $e->getMessage(),
            ]);
            FetchTrackingNowProgress::update($this->runId, [
                'status' => 'failed',
                'percent' => (int) (FetchTrackingNowProgress::get($this->runId)['percent'] ?? 10),
                'message' => 'Shopify fulfill failed: '.$e->getMessage(),
            ]);

            return;
        }

        FetchTrackingNowProgress::update($this->runId, [
            'status' => 'running',
            'percent' => 82,
            'checked' => (int) ($result['checked'] ?? 0),
            'fulfilled' => (int) ($result['fulfilled'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'message' => 'Pushing tracking to Amazon, Faire, Shein, Wayfair, Newegg, AliExpress, TikTok, Reverb…',
        ]);

        try {
            Artisan::call('mm:push-orders-tracking', [
                '--days' => 14,
                '--skip-fetch' => true,
                '--skip-inventory' => true,
                '--tracking-limit' => $this->trackingLimit,
            ]);
            Log::info('FetchMarketplaceShopifyTrackingNowJob: marketplace push', [
                'output' => mb_substr(trim(Artisan::output()), 0, 2000),
            ]);
        } catch (\Throwable $e) {
            Log::error('FetchMarketplaceShopifyTrackingNowJob: marketplace push failed', [
                'error' => $e->getMessage(),
            ]);
            FetchTrackingNowProgress::update($this->runId, [
                'status' => 'failed',
                'percent' => 90,
                'message' => 'Marketplace push failed: '.$e->getMessage(),
                'checked' => (int) ($result['checked'] ?? 0),
                'fulfilled' => (int) ($result['fulfilled'] ?? 0),
                'skipped' => (int) ($result['skipped'] ?? 0),
                'failed' => (int) ($result['failed'] ?? 0),
            ]);

            return;
        }

        $fulfilled = (int) ($result['fulfilled'] ?? 0);
        $checked = (int) ($result['checked'] ?? 0);
        FetchTrackingNowProgress::update($this->runId, [
            'status' => 'done',
            'percent' => 100,
            'checked' => $checked,
            'fulfilled' => $fulfilled,
            'skipped' => (int) ($result['skipped'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'message' => 'Done. Checked '.$checked.', fulfilled '.$fulfilled.' Shopify '
                .($fulfilled === 1 ? 'copy' : 'copies').'. Tracking pushed to the marketplaces.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function recordShopifyProgress(array $event): void
    {
        $type = (string) ($event['type'] ?? '');
        $checked = (int) ($event['checked'] ?? 0);
        $max = max(1, (int) ($event['max'] ?? $this->limit));
        $fulfilled = (int) ($event['fulfilled'] ?? 0);
        $percent = 5;
        if ($type === 'finish') {
            $percent = 80;
        } elseif ($type !== 'start') {
            $percent = 5 + (int) min(75, floor(($checked / $max) * 75));
        }

        $label = trim((string) ($event['label'] ?? ''));
        $message = 'Checking Shopify copies… '.$checked.' of '.$max;
        if ($fulfilled > 0) {
            $message .= ' · fulfilled '.$fulfilled;
        }
        if ($label !== '') {
            $message .= ' · '.$label;
        }

        FetchTrackingNowProgress::update($this->runId, [
            'status' => 'running',
            'percent' => $percent,
            'checked' => $checked,
            'fulfilled' => $fulfilled,
            'skipped' => (int) ($event['skipped'] ?? 0),
            'failed' => (int) ($event['failed'] ?? 0),
            'message' => $message,
        ]);
    }
}
