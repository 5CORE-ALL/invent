<?php

namespace App\Services\MarketplaceManager;

use App\Support\Marketplace\ListingChannelCounts;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * One "refresh listings" run for a marketplace listing page (/listing-<slug>):
 *   1. pull the live listing data from the marketplace (status pull / link map / catalog),
 *   2. rebuild the Missing L / Listed counts from it.
 *
 * Used by the "Refresh listings" button (queued job) and by the daily `listing:refresh all` schedule.
 * The scheduled run respects each marketplace's "Auto-link listings by SKU" switch; the button forces it.
 */
class ListingChannelRefresher
{
    public const STATUS_PREFIX = 'listing_refresh_status:';

    public const LOCK_PREFIX = 'listing_refresh_lock:';

    /**
     * Steps per listing page slug (the part after /listing- in the URL).
     * Pages that are not listed here have no API to read: the button only rebuilds their counts.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function plan(): array
    {
        $link = fn (string $label, string $command, bool $force = true): array => [
            'type' => 'artisan', 'label' => $label, 'command' => $command, 'force' => $force,
        ];
        $ebay = fn (int $store): array => ['type' => 'ebay', 'label' => 'eBay '.$store.' listings (Active / Ended / Deleted)', 'store' => $store];
        $catalog = fn (string $key, string $label): array => ['type' => 'catalog', 'label' => $label, 'channel' => $key];

        return [
            'ebay' => [$ebay(1), $link('eBay 1 link map', 'ebay1:sync-link-map')],
            'ebaytwo' => [$ebay(2), $link('eBay 2 link map', 'ebay2:sync-link-map')],
            'ebaythree' => [$ebay(3), $link('eBay 3 link map', 'ebay3:sync-link-map')],
            'amazon' => [$link('Amazon link map', 'amazon:sync-link-map')],
            'walmart' => [['type' => 'artisan', 'label' => 'Walmart listed items', 'command' => 'walmart:fetch-listed-prices', 'force' => false]],
            'doba' => [$link('Doba link map', 'doba:sync-link-map', false)],
            'wayfair' => [$link('Wayfair link map', 'wayfair:sync-link-map', false)],
            'neweggb2c' => [$link('Newegg link map', 'newegg:sync-link-map')],
            'reverb' => [
                ['type' => 'artisan', 'label' => 'Reverb listing statuses', 'command' => 'reverb:sync-listing-statuses', 'force' => false],
                $link('Reverb link map', 'reverb:manager-sync-link-map'),
            ],
            'temu' => [$link('Temu link map', 'temu:sync-link-map')],
            'temu2' => [$link('Temu 2 link map', 'temu2:sync-link-map')],
            'temu3' => [$link('Temu 3 link map', 'temu3:sync-link-map')],
            'aliexpress' => [$link('AliExpress link map', 'aliexpress:sync-link-map')],
            'alibaba' => [$link('Alibaba link map', 'alibaba:sync-link-map')],
            'shein' => [$link('Shein link map', 'shein:sync-link-map')],
            'tiktokshop' => [['type' => 'tiktok', 'label' => 'TikTok Shop products', 'channel' => 'tiktok']],
            'tiktokshop2' => [['type' => 'tiktok', 'label' => 'TikTok 2 products', 'channel' => 'tiktok2']],
            'macys' => [$catalog('macy', "Macy's catalog")],
            'bestbuyusa' => [$catalog('bestbuy', 'Best Buy catalog')],
            'purchasingpower' => [$catalog('purchasingpower', 'Purchasing Power catalog')],
            'pls' => [$catalog('pls', 'PLS catalog')],
            'shopifyb2c' => [$catalog('shopifyb2c', 'Shopify B2C catalog')],
            'b5cb2b' => [$catalog('b5cb2b', 'Business 5Core B2B catalog')],
            'topdawg' => [$catalog('topdawg', 'TopDawg catalog')],
            'faire' => [$catalog('faire', 'Faire catalog')],
        ];
    }

    public static function normalizeSlug(string $slug): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $slug) ?? '');
    }

    public static function statusKey(string $slug): string
    {
        return self::STATUS_PREFIX.self::normalizeSlug($slug);
    }

    /** @return array<string, mixed> */
    public static function status(string $slug): array
    {
        $status = Cache::get(self::statusKey($slug));

        return is_array($status) ? $status : ['state' => 'idle'];
    }

    public static function markQueued(string $slug): void
    {
        self::putStatus($slug, [
            'state' => 'running',
            'message' => 'Queued…',
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'steps' => [],
        ]);
    }

    /**
     * Run every step for one listing page, then rebuild its counts.
     *
     * @param  bool  $manual  true for the button: ignore the "Auto-link listings by SKU" switch
     * @return array<string, mixed> final status
     */
    public function run(string $slug, bool $manual = true): array
    {
        $slug = self::normalizeSlug($slug);
        @set_time_limit(0);

        $lock = Cache::lock(self::LOCK_PREFIX.$slug, 3600);
        if (! $lock->get()) {
            return self::status($slug);
        }

        $status = [
            'state' => 'running',
            'message' => 'Refreshing…',
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'steps' => [],
        ];
        self::putStatus($slug, $status);

        try {
            $failed = 0;
            foreach (self::plan()[$slug] ?? [] as $step) {
                $stepStart = microtime(true);
                [$ok, $message] = $this->runStep($step, $manual);
                $message .= ' ('.round(microtime(true) - $stepStart).'s)';
                $status['steps'][] = ['label' => $step['label'], 'ok' => $ok, 'message' => $message];
                $status['message'] = 'Done: '.$step['label'];
                if (! $ok) {
                    $failed++;
                }
                self::putStatus($slug, $status);
            }

            try {
                $counts = ListingChannelCounts::refreshChannelOnMissingListingPage($slug);
                $status['counts'] = $counts;
                $status['steps'][] = ['label' => 'Rebuild Missing L / Listed counts', 'ok' => true, 'message' => 'Missing L '.(int) ($counts['Pending'] ?? 0).', Listed '.(int) ($counts['Listed'] ?? 0)];
            } catch (\Throwable $e) {
                $failed++;
                $status['steps'][] = ['label' => 'Rebuild Missing L / Listed counts', 'ok' => false, 'message' => $e->getMessage()];
            }

            $status['state'] = $failed > 0 ? 'failed' : 'done';
            $status['message'] = $failed > 0
                ? $failed.' step(s) failed — see details.'
                : 'Listings refreshed.';
        } catch (\Throwable $e) {
            Log::error('ListingChannelRefresher failed', ['slug' => $slug, 'error' => $e->getMessage()]);
            $status['state'] = 'failed';
            $status['message'] = $e->getMessage();
        } finally {
            optional($lock)->release();
        }

        $status['finished_at'] = now()->toIso8601String();
        self::putStatus($slug, $status);

        return $status;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array{0: bool, 1: string}
     */
    protected function runStep(array $step, bool $manual): array
    {
        try {
            switch ($step['type']) {
                case 'ebay':
                    $result = app(EbayPortalListingStatusSync::class)->sync((int) $step['store']);

                    return $this->fromResult($result);

                case 'catalog':
                    // Skip the 30-minute freshness cache: this refresh was asked for explicitly.
                    Cache::forget('ml.listed_catalog.fresh.'.ListingChannelCounts::normalize((string) $step['channel']));
                    $status = app(MissingListingCatalogRefresh::class)->refreshChannel((string) $step['channel']);

                    return [in_array($status, ['ok', 'skipped', 'fresh'], true), 'Catalog: '.$status];

                case 'tiktok':
                    \App\Jobs\SyncTikTokProductsJob::dispatchSync((string) $step['channel'], true);

                    return [true, 'TikTok products synced.'];

                case 'artisan':
                    $params = [];
                    if ($manual && ! empty($step['force'])) {
                        $params['--force'] = true;
                    }
                    $code = Artisan::call((string) $step['command'], $params);
                    $output = trim(preg_replace('/\s+/', ' ', Artisan::output()) ?? '');

                    return [$code === 0, $output !== '' ? mb_substr($output, 0, 300) : 'exit '.$code];
            }
        } catch (\Throwable $e) {
            Log::warning('ListingChannelRefresher step failed', ['step' => $step['label'] ?? '', 'error' => $e->getMessage()]);

            return [false, $e->getMessage()];
        }

        return [false, 'Unknown step.'];
    }

    /**
     * @param  mixed  $result
     * @return array{0: bool, 1: string}
     */
    protected function fromResult($result): array
    {
        if (! is_array($result)) {
            return [true, 'Done.'];
        }
        $ok = array_key_exists('ok', $result) ? (bool) $result['ok'] : (array_key_exists('success', $result) ? (bool) $result['success'] : true);
        $message = (string) ($result['message'] ?? $result['error'] ?? '');
        if ($message === '') {
            $parts = [];
            foreach ($result as $k => $v) {
                if (is_scalar($v)) {
                    $parts[] = $k.': '.(is_bool($v) ? ($v ? 'yes' : 'no') : $v);
                }
            }
            $message = implode(', ', array_slice($parts, 0, 8));
        }

        return [$ok, $message !== '' ? $message : ($ok ? 'Done.' : 'Failed.')];
    }

    /**
     * @param  array<string, mixed>  $status
     */
    protected static function putStatus(string $slug, array $status): void
    {
        Cache::put(self::statusKey($slug), $status, now()->addDays(2));
    }
}
