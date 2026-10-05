<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\Log;

/**
 * One small synchronous batch of Shopify → marketplace tracking pushes.
 * The orders page calls this repeatedly and shows a percentage.
 */
class MarketplaceTrackingBatchPush
{
    /**
     * @return array<string, mixed>
     */
    public function pushBatch(string $slug, int $limit = 2): array
    {
        $slug = strtolower(trim($slug));
        $limit = max(1, min(5, $limit));
        $service = $this->serviceFor($slug);
        if ($service === null) {
            return [
                'success' => false,
                'finished' => true,
                'checked' => 0,
                'pushed' => 0,
                'skipped' => 0,
                'failed' => 0,
                'pending_before' => 0,
                'pending_after' => 0,
                'message' => 'This marketplace does not push tracking.',
            ];
        }

        $pendingBefore = $this->countPending($service);
        try {
            app(VeeqoShopifyFulfillmentService::class)->syncPendingUnfulfilledForMarketplace($slug, $limit);
        } catch (\Throwable $e) {
            Log::warning('MarketplaceTrackingBatchPush: Shopify label copy failed', [
                'marketplace' => $slug,
                'error' => $e->getMessage(),
            ]);
        }

        $method = $this->syncMethod($service);
        if ($method === null) {
            return [
                'success' => false,
                'finished' => true,
                'checked' => 0,
                'pushed' => 0,
                'skipped' => 0,
                'failed' => 0,
                'pending_before' => $pendingBefore ?? 0,
                'pending_after' => $pendingBefore ?? 0,
                'message' => 'This marketplace does not push tracking.',
            ];
        }

        try {
            $result = $service->{$method}($limit);
        } catch (\Throwable $e) {
            Log::error('MarketplaceTrackingBatchPush: batch failed', [
                'marketplace' => $slug,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'finished' => true,
                'checked' => 0,
                'pushed' => 0,
                'skipped' => 0,
                'failed' => 1,
                'pending_before' => $pendingBefore ?? 0,
                'pending_after' => $pendingBefore ?? 0,
                'message' => $e->getMessage(),
            ];
        }

        if (! is_array($result)) {
            $result = [];
        }
        $checked = (int) ($result['checked'] ?? 0);
        $pendingAfter = $this->countPending($service);
        $finished = $checked === 0
            || ($pendingAfter !== null && $pendingAfter === 0)
            || ($pendingAfter === null && $checked < $limit);

        return [
            'success' => ($result['failed'] ?? 0) === 0,
            'finished' => $finished,
            'checked' => $checked,
            'pushed' => (int) ($result['pushed'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'pending_before' => $pendingBefore,
            'pending_after' => $pendingAfter,
            'message' => (string) ($result['message'] ?? 'Tracking push finished.'),
        ];
    }

    protected function countPending(object $service): ?int
    {
        if (! method_exists($service, 'countPendingTracking')) {
            return null;
        }

        try {
            return max(0, (int) $service->countPendingTracking());
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function syncMethod(object $service): ?string
    {
        foreach (['syncPendingFromShopify', 'syncPending', 'syncFromShopify'] as $method) {
            if (method_exists($service, $method)) {
                return $method;
            }
        }

        return null;
    }

    protected function serviceFor(string $slug): ?object
    {
        $class = match ($slug) {
            'amazon' => AmazonTrackingSyncService::class,
            'aliexpress' => AliexpressTrackingSyncService::class,
            'alibaba' => AlibabaTrackingSyncService::class,
            'reverb' => ReverbTrackingSyncService::class,
            'newegg' => NeweggTrackingSyncService::class,
            'shein' => SheinTrackingSyncService::class,
            'topdawg' => TopDawgTrackingSyncService::class,
            'temu' => TemuTrackingSyncService::class,
            'temu2' => Temu2TrackingSyncService::class,
            'temu3' => Temu3TrackingSyncService::class,
            'ebay1' => Ebay1TrackingSyncService::class,
            'ebay2' => Ebay2TrackingSyncService::class,
            'ebay3' => Ebay3TrackingSyncService::class,
            'faire' => FaireTrackingSyncService::class,
            'tiktok' => TikTokTrackingSyncService::class,
            'tiktok2' => TikTok2TrackingSyncService::class,
            'purchasingpower' => PurchasingPowerTrackingSyncService::class,
            'wayfair' => WayfairTrackingSyncService::class,
            'bestbuy' => BestBuyTrackingSyncService::class,
            'macy' => MacyTrackingSyncService::class,
            'doba' => DobaTrackingSyncService::class,
            'b5cb2b' => B5cB2bTrackingSyncService::class,
            default => null,
        };

        return $class === null ? null : app($class);
    }
}
