<?php

namespace App\Services\MarketplaceManager;

use App\Models\TopDawgProduct;
use App\Services\AmazonSpApiService;
use App\Services\TopDawgApiService;
use App\Support\Marketplace\ListingInactiveParentChildCounts;
use App\Support\Marketplace\MappingChannelCounts;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Re-pull marketplace listing statuses and rebuild /inactive-listings.
 * Portal ensure* helpers skip after the first inactive row exists — this forces a refresh.
 */
class InactiveListingsSyncService
{
    public const STATUS_KEY = 'inactive_listings_sync_status_v1';

    public const PORTAL_DONE_KEYS = [
        'mm.ebay2.portal_inactive_synced_v2',
        'mm.ebay3.portal_inactive_synced_v2',
        'mm.macy.portal_inactive_synced_v2',
        'mm.bestbuy.portal_inactive_synced_v2',
        'mm.faire.portal_inactive_synced_v1',
        'mm.topdawg.portal_inactive_synced_v2',
        'mm.faire.portal_status_page_v1',
    ];

    /**
     * @return array{status: string, started_at: ?string, finished_at: ?string, message: string, channels: array<string, string>}
     */
    public static function status(): array
    {
        $cached = Cache::get(self::STATUS_KEY);
        if (is_array($cached)) {
            return array_merge(self::emptyStatus(), $cached);
        }

        return self::emptyStatus();
    }

    /**
     * @return array{status: string, started_at: ?string, finished_at: ?string, message: string, channels: array<string, string>}
     */
    public static function emptyStatus(): array
    {
        return [
            'status' => 'idle',
            'started_at' => null,
            'finished_at' => null,
            'message' => '',
            'channels' => [],
        ];
    }

    public static function markRunning(): void
    {
        Cache::put(self::STATUS_KEY, [
            'status' => 'running',
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'message' => 'Syncing marketplace listing statuses…',
            'channels' => [],
        ], now()->addHours(6));
    }

    public function isRunning(): bool
    {
        $status = self::status();
        if (($status['status'] ?? '') !== 'running') {
            return false;
        }
        $started = $status['started_at'] ?? null;
        if (! is_string($started) || $started === '') {
            return true;
        }
        try {
            return \Carbon\Carbon::parse($started)->gt(now()->subMinutes(40));
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * @return array{status: string, started_at: ?string, finished_at: ?string, message: string, channels: array<string, string>}
     */
    public function run(): array
    {
        self::markRunning();
        $channels = [];

        $this->forgetPortalDoneKeys();
        MarketplacePortalInactiveCount::resetMemos();
        ListingInactiveParentChildCounts::resetMemos();

        $channels['ebay1'] = $this->runChannel('eBay 1', fn () => app(EbayPortalListingStatusSync::class)->sync(1));
        $channels['ebay2'] = $this->runChannel('eBay 2', fn () => app(EbayPortalListingStatusSync::class)->sync(2));
        $channels['ebay3'] = $this->runChannel('eBay 3', fn () => app(EbayPortalListingStatusSync::class)->sync(3));
        $channels['macy'] = $this->runChannel('Macy', fn () => app(MiraklMcmOfferStatusSync::class)->sync('macy', 180));
        $channels['bestbuy'] = $this->runChannel('Best Buy', fn () => app(MiraklMcmOfferStatusSync::class)->sync('bestbuy', 180));
        $channels['faire'] = $this->runChannel('Faire', fn () => $this->syncFaire());
        $channels['topdawg'] = $this->runChannel('TopDawg', fn () => $this->syncTopDawg());
        $channels['amazon'] = $this->runChannel('Amazon', fn () => $this->syncAmazonStates());
        $channels['reverb'] = $this->runChannel('Reverb', function () {
            Artisan::call('reverb:sync-listing-statuses');

            return ['ok' => true];
        });

        MarketplacePortalInactiveCount::resetMemos();
        ListingInactiveParentChildCounts::resetMemos();
        MappingChannelCounts::forgetMasterCaches();
        MappingChannelCounts::inactiveMasterRows(false);

        $failed = collect($channels)->filter(fn (string $msg) => str_starts_with($msg, 'failed'))->count();
        $payload = [
            'status' => 'done',
            'started_at' => self::status()['started_at'] ?? now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'message' => $failed > 0
                ? 'Sync finished with '.$failed.' channel warning(s). Counts were rebuilt.'
                : 'Sync finished. Inactive Listing counts were rebuilt.',
            'channels' => $channels,
        ];
        Cache::put(self::STATUS_KEY, $payload, now()->addDays(2));

        Log::info('InactiveListingsSyncService: finished', $payload);

        return $payload;
    }

    /**
     * @param  callable(): mixed  $fn
     */
    protected function runChannel(string $label, callable $fn): string
    {
        try {
            $result = $fn();
            if (is_array($result) && array_key_exists('ok', $result) && ! ($result['ok'] ?? false)) {
                $error = (string) ($result['error'] ?? 'sync failed');

                return 'failed: '.$error;
            }

            return 'ok';
        } catch (\Throwable $e) {
            Log::warning('InactiveListingsSyncService: '.$label.' failed', [
                'error' => $e->getMessage(),
            ]);

            return 'failed: '.$e->getMessage();
        }
    }

    protected function forgetPortalDoneKeys(): void
    {
        foreach (self::PORTAL_DONE_KEYS as $key) {
            try {
                Cache::forget($key);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    protected function syncFaire(): array
    {
        Cache::forget('mm.faire.portal_status_page_v1');
        $deadline = microtime(true) + 240;
        $last = ['ok' => false, 'done' => false];
        do {
            $last = app(FaireLinkMapSyncService::class)->syncListingStatuses(60);
            if (! ($last['ok'] ?? false)) {
                return $last;
            }
        } while (! ($last['done'] ?? false) && microtime(true) < $deadline);

        return $last;
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    protected function syncTopDawg(): array
    {
        $api = app(TopDawgApiService::class);
        if (! $api->isConfigured()) {
            return ['ok' => false, 'error' => 'TopDawg API not configured'];
        }

        $result = $api->fetchProducts(null);
        $items = is_array($result['data'] ?? null) ? $result['data'] : [];
        $inactiveProbe = 0;
        foreach ($items as $probe) {
            if (is_array($probe) && MarketplacePortalStatusTabs::bucket((string) (TopDawgApiService::listingStateFromItem($probe) ?? '')) === 'inactive') {
                $inactiveProbe++;
                break;
            }
        }
        if ($inactiveProbe === 0) {
            foreach (['inactive', 'disabled', 'pending', 'rejected'] as $statusFilter) {
                $more = $api->fetchProducts(null, null, ['status' => $statusFilter]);
                $extra = is_array($more['data'] ?? null) ? $more['data'] : [];
                if ($extra !== []) {
                    $items = array_merge($items, $extra);
                }
            }
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['product_code'] ?? $item['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $state = TopDawgApiService::listingStateFromItem($item);
            if ($state === null) {
                continue;
            }
            TopDawgProduct::query()->updateOrCreate(
                ['sku' => $sku],
                ['listing_state' => $state]
            );
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool}
     */
    protected function syncAmazonStates(): array
    {
        $skus = MarketplacePortalInactiveCount::amazonInactiveReportSkus();
        app(AmazonSpApiService::class)->forgetSellerCentralListingStates($skus);
        if ($skus !== []) {
            app(AmazonSpApiService::class)->sellerCentralListingStates($skus);
        }

        return ['ok' => true];
    }
}
