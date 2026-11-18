<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\WayfairStatusReconciler;
use App\Services\MarketplaceManager\WayfairTrackingSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers to Wayfair for linked orders.
 */
class SyncWayfairTrackingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, FulfillsShopifyBeforeChannelTracking;

    public int $tries = 3;

    public int $timeout = 1200;

    public int $uniqueFor = 1500;

    public array $backoff = [20, 60, 120];

    public function __construct(
        public bool $respectSettings = true,
        public int $limit = 40,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::QUEUE_TRACKING);
    }

    public function uniqueId(): string
    {
        return 'mm-wayfair-tracking-sync';
    }

    public function handle(WayfairTrackingSyncService $sync): void
    {
        $this->refreshOrderStatuses($sync);

        if ($this->respectSettings && ! WayfairTrackingSyncService::canPushTracking()) {
            Log::info('SyncWayfairTrackingJob: skipped (push_tracking_to_wayfair Off)');

            return;
        }

        $this->fulfillShopifyCopiesFirst('wayfair', $this->limit);
        $this->runTrackingSafely(fn () => $sync->syncPending($this->limit));
    }

    /**
     * Wayfair never reports shipped / delivered back, so find each open PO's label tracking and let the carrier
     * status move it off open / accepted. Independent of the push setting and never fails the job.
     */
    protected function refreshOrderStatuses(WayfairTrackingSyncService $sync): void
    {
        try {
            $label = $sync->fillMissingLabelTracking(60, 75, microtime(true) + 240);
            $status = app(WayfairStatusReconciler::class)->reconcile(
                null, null, null, 0, false, now()->subDays(120)->toDateString()
            );
            Log::info('SyncWayfairTrackingJob: order status refresh', [
                'label_checked' => $label['checked'],
                'label_found' => $label['found'],
                'delivered' => $status['delivered'],
                'shipped' => $status['shipped'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('SyncWayfairTrackingJob: order status refresh failed', ['error' => $e->getMessage()]);
        }
    }
}
