<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\SyncAlibabaTrackingJob;
use App\Jobs\SyncAliexpressTrackingJob;
use App\Jobs\SyncAmazonTrackingJob;
use App\Jobs\SyncBestBuyTrackingJob;
use App\Jobs\SyncDobaTrackingJob;
use App\Jobs\SyncEbay1TrackingJob;
use App\Jobs\SyncEbay2TrackingJob;
use App\Jobs\SyncEbay3TrackingJob;
use App\Jobs\SyncFaireTrackingJob;
use App\Jobs\SyncMacyTrackingJob;
use App\Jobs\SyncNeweggTrackingJob;
use App\Jobs\SyncPurchasingPowerTrackingJob;
use App\Jobs\SyncReverbTrackingJob;
use App\Jobs\SyncSheinTrackingJob;
use App\Jobs\SyncTemu2TrackingJob;
use App\Jobs\SyncTemuTrackingJob;
use App\Jobs\SyncTikTok2TrackingJob;
use App\Jobs\SyncTikTokTrackingJob;
use App\Jobs\SyncTopDawgTrackingJob;
use App\Jobs\SyncWayfairTrackingJob;
use App\Models\AlibabaOrderMetric;
use App\Models\AliexpressOrderMetric;
use App\Models\AmazonOrder;
use App\Models\BestBuyOrderMetric;
use App\Models\DobaDailyData;
use App\Models\Ebay1OrderMetric;
use App\Models\Ebay2OrderMetric;
use App\Models\Ebay3OrderMetric;
use App\Models\FaireOrderMetric;
use App\Models\MacyOrderMetric;
use App\Models\NeweggOrderMetric;
use App\Models\PurchasingPowerSale;
use App\Models\ReverbOrderMetric;
use App\Models\SheinOrderMetric;
use App\Models\Temu2Order;
use App\Models\TemuOrder;
use App\Models\Tiktok2Order;
use App\Models\TiktokOrder;
use App\Models\TopDawgOrderMetric;
use App\Models\WayfairDailyData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * After Shopify has a tracking number, push it to the originating marketplace.
 * Used by VeeqoShopifyFulfillmentService (immediate) and as a map for cron jobs.
 */
class MarketplaceChannelFulfillmentHub
{
    /**
     * @return array<string, array{0: class-string, 1: class-string, 2: list<string>}>
     */
    protected function channelMap(): array
    {
        return [
            'amazon' => [AmazonOrder::class, AmazonTrackingSyncService::class, ['amazon_order_id']],
            'aliexpress' => [AliexpressOrderMetric::class, AliexpressTrackingSyncService::class, ['order_id', 'order_number']],
            'alibaba' => [AlibabaOrderMetric::class, AlibabaTrackingSyncService::class, ['order_id', 'order_number']],
            'reverb' => [ReverbOrderMetric::class, ReverbTrackingSyncService::class, ['order_id', 'order_number']],
            'newegg' => [NeweggOrderMetric::class, NeweggTrackingSyncService::class, ['order_id', 'order_number']],
            'shein' => [SheinOrderMetric::class, SheinTrackingSyncService::class, ['order_id', 'order_number']],
            'topdawg' => [TopDawgOrderMetric::class, TopDawgTrackingSyncService::class, ['order_id', 'order_number']],
            'temu' => [TemuOrder::class, TemuTrackingSyncService::class, ['parent_order_sn', 'order_sn']],
            'temu2' => [Temu2Order::class, Temu2TrackingSyncService::class, ['parent_order_sn', 'order_sn']],
            'purchasingpower' => [PurchasingPowerSale::class, PurchasingPowerTrackingSyncService::class, ['order_id', 'order_number']],
            'wayfair' => [WayfairDailyData::class, WayfairTrackingSyncService::class, ['po_number']],
            'bestbuy' => [BestBuyOrderMetric::class, BestBuyTrackingSyncService::class, ['order_id', 'channel_order_id']],
            'macy' => [MacyOrderMetric::class, MacyTrackingSyncService::class, ['order_id', 'channel_order_id']],
            'doba' => [DobaDailyData::class, DobaTrackingSyncService::class, ['order_no', 'platform_order_no']],
            'ebay1' => [Ebay1OrderMetric::class, Ebay1TrackingSyncService::class, ['order_id', 'order_number']],
            'ebay2' => [Ebay2OrderMetric::class, Ebay2TrackingSyncService::class, ['order_id', 'order_number']],
            'ebay3' => [Ebay3OrderMetric::class, Ebay3TrackingSyncService::class, ['order_id', 'order_number']],
            'faire' => [FaireOrderMetric::class, FaireTrackingSyncService::class, ['order_id', 'order_number']],
            'tiktok' => [TiktokOrder::class, TikTokTrackingSyncService::class, ['order_id']],
            'tiktok2' => [Tiktok2Order::class, TikTok2TrackingSyncService::class, ['order_id']],
        ];
    }

    /**
     * Push tracking to the channel. Does not fulfill Shopify (caller already did).
     *
     * @param  array<string, mixed>  $result
     */
    public function pushAfterShopifyTracking(string $marketplace, int $orderId, array $result = []): void
    {
        $marketplace = strtolower(trim($marketplace));
        if (trim((string) ($result['tracking'] ?? '')) === '' && $result !== []) {
            return;
        }

        $line = $this->findLineByLocalId($marketplace, $orderId);
        if ($line === null) {
            return;
        }

        $this->pushLine($marketplace, $line);
    }

    /**
     * When Shopify copies are fulfilled from the unfulfilled-order scan (no local id),
     * resolve the channel row from tags like newegg-123 / tiktok2-5768….
     *
     * @param  array<string, mixed>  $shopifyOrder
     * @param  array<string, mixed>  $result
     */
    public function pushAfterShopifyCopy(array $shopifyOrder, string $shopifyOrderId, array $result): void
    {
        if (trim((string) ($result['tracking'] ?? '')) === '') {
            return;
        }

        $hay = strtolower(trim((string) ($shopifyOrder['tags'] ?? '')).' '.trim((string) ($shopifyOrder['note'] ?? '')));
        $slugs = array_keys($this->channelMap());
        usort($slugs, static fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

        foreach ($slugs as $slug) {
            if (! preg_match('/(?:^|[\s,])'.preg_quote($slug, '/').'-([^\s,]+)/i', $hay, $m)) {
                continue;
            }
            $channelRef = trim((string) ($m[1] ?? ''));
            if ($channelRef === '') {
                continue;
            }
            $line = $this->findLineByChannelRef($slug, $channelRef);
            if ($line === null) {
                continue;
            }
            if (trim((string) ($line->shopify_order_id ?? '')) === '') {
                try {
                    $line->shopify_order_id = $shopifyOrderId;
                    $line->save();
                } catch (\Throwable) {
                    // Link is best-effort.
                }
            }
            $this->pushLine($slug, $line);
        }
    }

    public static function dispatchTrackingJob(string $slug, int $limit = 40): void
    {
        $slug = strtolower(trim($slug));
        $job = match ($slug) {
            'amazon' => new SyncAmazonTrackingJob(true, $limit),
            'aliexpress' => new SyncAliexpressTrackingJob(true, $limit),
            'alibaba' => new SyncAlibabaTrackingJob(true, $limit),
            'reverb' => new SyncReverbTrackingJob(true, $limit),
            'newegg' => new SyncNeweggTrackingJob(true, $limit),
            'shein' => new SyncSheinTrackingJob(true, $limit),
            'topdawg' => new SyncTopDawgTrackingJob(true, $limit),
            'temu' => new SyncTemuTrackingJob(true, $limit),
            'temu2' => new SyncTemu2TrackingJob(true, $limit),
            'purchasingpower' => new SyncPurchasingPowerTrackingJob(true, $limit),
            'wayfair' => new SyncWayfairTrackingJob(true, $limit),
            'bestbuy' => new SyncBestBuyTrackingJob(true, $limit),
            'macy' => new SyncMacyTrackingJob(true, $limit),
            'doba' => new SyncDobaTrackingJob(true, $limit),
            'ebay1' => new SyncEbay1TrackingJob(true, $limit),
            'ebay2' => new SyncEbay2TrackingJob(true, $limit),
            'ebay3' => new SyncEbay3TrackingJob(true, $limit),
            'faire' => new SyncFaireTrackingJob(true, $limit),
            'tiktok' => new SyncTikTokTrackingJob(true, $limit),
            'tiktok2' => new SyncTikTok2TrackingJob(true, $limit),
            default => null,
        };
        if ($job === null) {
            return;
        }
        dispatch($job);
    }

    protected function pushLine(string $marketplace, object $line): void
    {
        $map = $this->channelMap()[$marketplace] ?? null;
        if ($map === null) {
            return;
        }
        [, $serviceClass] = $map;
        $service = app($serviceClass);
        if (! $this->channelAllowsPush($service)) {
            return;
        }
        if (! method_exists($service, 'pushTrackingForOrder')) {
            return;
        }

        try {
            $service->pushTrackingForOrder($line);
        } catch (\Throwable $e) {
            Log::warning('MarketplaceChannelFulfillmentHub: channel tracking push failed', [
                'marketplace' => $marketplace,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function channelAllowsPush(object $service): bool
    {
        if (method_exists($service, 'canAutoPush')) {
            return (bool) $service::canAutoPush();
        }
        if (method_exists($service, 'canPushTracking')) {
            return (bool) $service::canPushTracking();
        }

        return true;
    }

    protected function findLineByLocalId(string $marketplace, int $orderId): ?object
    {
        $map = $this->channelMap()[$marketplace] ?? null;
        if ($map === null || $orderId < 1) {
            return null;
        }
        [$class] = $map;
        try {
            $model = new $class;
            if (! Schema::hasTable($model->getTable())) {
                return null;
            }

            return $class::query()->find($orderId);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function findLineByChannelRef(string $marketplace, string $ref): ?object
    {
        $map = $this->channelMap()[$marketplace] ?? null;
        if ($map === null || $ref === '') {
            return null;
        }
        [$class, , $fields] = $map;
        try {
            $model = new $class;
            $table = $model->getTable();
            if (! Schema::hasTable($table)) {
                return null;
            }
            $query = $class::query();
            $applied = false;
            $query->where(function ($q) use ($fields, $ref, $table, &$applied) {
                foreach ($fields as $field) {
                    if (! Schema::hasColumn($table, $field)) {
                        continue;
                    }
                    $q->orWhere($field, $ref);
                    $applied = true;
                }
            });
            if (! $applied) {
                return null;
            }

            return $query->orderBy('id')->first();
        } catch (\Throwable) {
            return null;
        }
    }
}
