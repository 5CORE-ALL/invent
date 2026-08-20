<?php

namespace App\Support\Marketplace;

use App\Models\ChannelMaster;
use App\Services\MarketplaceManager\MarketplaceListingQtyMatchService;
use App\Services\MarketplaceManager\MarketplacePortalInactiveCount;
use App\Services\ShopifyPlsTokenService;
use App\Services\Support\MarketplaceApiConfigService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Missing Mapping Titas counts from Marketplace Manager listings
 * (Active SKU Mismatch on /marketplace/{channel}/products).
 */
class MappingChannelCounts
{
    public const TOTAL_CACHE_KEY = 'mapping_pages_nmap_total_v2';

    public const TOTAL_TITAS_CACHE_KEY = 'mapping_pages_titas_total_v2';

    public const MASTER_ROWS_CACHE_KEY = 'mapping_pages_master_rows_v2';

    public const API_STATUS_CACHE_KEY = 'mapping_pages_api_status_v1';

    public const INACTIVE_TOTAL_CACHE_KEY = 'inactive_listings_total_v6';

    public const INACTIVE_MASTER_ROWS_CACHE_KEY = 'inactive_listings_master_rows_v6';

    /**
     * Channels shown on /map-issues.
     * mi_key marks MapIssues channels that have SKU-level detail pages.
     *
     * @var array<string, array{label: string, mi_key?: string, loader?: string}>
     */
    private static array $sources = [
        'ebay' => ['label' => 'eBay', 'mi_key' => 'not_map_count'],
        'ebay2' => ['label' => 'eBay 2', 'mi_key' => 'ebay2_not_map_count'],
        'ebaytwo' => ['label' => 'eBay 2', 'mi_key' => 'ebay2_not_map_count'],
        'ebay3' => ['label' => 'eBay 3', 'mi_key' => 'ebay3_not_map_count'],
        'ebaythree' => ['label' => 'eBay 3', 'mi_key' => 'ebay3_not_map_count'],
        'amazon' => ['label' => 'Amazon', 'mi_key' => 'amazon_not_map_count'],
        'reverb' => ['label' => 'Reverb', 'mi_key' => 'reverb_not_map_count', 'loader' => 'reverb'],
        'macys' => ['label' => 'Macys', 'mi_key' => 'macys_not_map_count', 'loader' => 'macys'],
        'bestbuy' => ['label' => 'BestBuy USA', 'mi_key' => 'bestbuy_not_map_count'],
        'bestbuyusa' => ['label' => 'BestBuy USA', 'mi_key' => 'bestbuy_not_map_count'],
        'temu' => ['label' => 'Temu', 'mi_key' => 'temu_not_map_count', 'loader' => 'temu'],
        'temu2' => ['label' => 'Temu 2', 'loader' => 'temu2'],
        'shein' => ['label' => 'Shein', 'mi_key' => 'shein_not_map_count', 'loader' => 'shein'],
        'newegg' => ['label' => 'Newegg', 'mi_key' => 'newegg_not_map_count'],
        'neweggb2c' => ['label' => 'Newegg', 'mi_key' => 'newegg_not_map_count'],
        'aliexpress' => ['label' => 'Aliexpress', 'mi_key' => 'aliexpress_not_map_count', 'loader' => 'aliexpress'],
        'pls' => ['label' => 'PLS', 'loader' => 'pls'],
        'wayfair' => ['label' => 'Wayfair', 'loader' => 'wayfair'],
        'faire' => ['label' => 'Faire', 'loader' => 'faire'],
        'topdawg' => ['label' => 'TopDawg', 'loader' => 'topdawg'],
        'tiktok' => ['label' => 'TikTok Shop', 'loader' => 'tiktok'],
        'tiktokshop' => ['label' => 'TikTok Shop', 'loader' => 'tiktok'],
        'tiktok2' => ['label' => 'TikTok 2', 'loader' => 'tiktok2'],
        'tiktokshop2' => ['label' => 'TikTok 2', 'loader' => 'tiktok2'],
    ];

    public static function normalize(string $channel): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim($channel)) ?? '');
    }

    public static function hasMappingSource(string $channel): bool
    {
        $key = self::normalize($channel);

        return $key !== '' && isset(self::$sources[$key]);
    }

    /**
     * Sum of Missing Mapping Titas (Active SKU Mismatch from listings).
     */
    public static function totalTitas(bool $useCache = true): int
    {
        if ($useCache) {
            try {
                $cached = Cache::get(self::TOTAL_TITAS_CACHE_KEY);
                if ($cached !== null) {
                    return (int) $cached;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $rows = self::masterRows(true);
        $total = (int) collect($rows)->sum('missing_mapping_titas');

        try {
            Cache::put(self::TOTAL_TITAS_CACHE_KEY, $total, now()->addMinutes(10));
        } catch (\Throwable $e) {
            // ignore
        }

        return $total;
    }

    public static function storeTotalTitas(int $total): void
    {
        try {
            Cache::put(self::TOTAL_TITAS_CACHE_KEY, $total, now()->addMinutes(30));
            Cache::put(self::TOTAL_CACHE_KEY, $total, now()->addMinutes(30));
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Cached total only — never scans marketplaces during HTML render.
     */
    public static function cachedTotalOrZero(): int
    {
        foreach ([self::TOTAL_TITAS_CACHE_KEY, self::TOTAL_CACHE_KEY] as $key) {
            try {
                $cached = Cache::get($key);
                if ($cached !== null) {
                    return (int) $cached;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return 0;
    }

    public static function forgetMasterCaches(): void
    {
        try {
            Cache::forget(self::TOTAL_TITAS_CACHE_KEY);
            Cache::forget(self::TOTAL_CACHE_KEY);
            Cache::forget(self::MASTER_ROWS_CACHE_KEY);
            Cache::forget(self::API_STATUS_CACHE_KEY);
            Cache::forget(self::INACTIVE_TOTAL_CACHE_KEY);
            Cache::forget(self::INACTIVE_MASTER_ROWS_CACHE_KEY);
            Cache::forget('inactive_listings_total_v5');
            Cache::forget('inactive_listings_master_rows_v5');
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Master table rows: one row per unique mapping channel (Titas / Active SKU Mismatch).
     *
     * @return list<array{channel: string, channel_slug: string, image: ?string, missing_mapping_titas: int, detail_url: string, listings_url: ?string, has_sku_detail: bool}>
     */
    public static function masterRows(bool $useCache = false): array
    {
        if ($useCache) {
            try {
                $cached = Cache::get(self::MASTER_ROWS_CACHE_KEY);
                if (is_array($cached) && $cached !== []) {
                    return $cached;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $titasCounts = self::collectListingsMismatchCounts();
        $apiStatuses = self::collectApiStatuses();
        $logos = self::logoMap();
        $displayNames = self::displayNameMap();

        // Unique logical channels (skip alias duplicates)
        $seen = [];
        $rows = [];

        $order = [
            'amazon', 'ebay', 'ebay2', 'ebay3', 'reverb', 'macys', 'bestbuy',
            'temu', 'temu2', 'shein', 'newegg', 'aliexpress',
            'pls', 'wayfair', 'faire', 'topdawg', 'tiktok', 'tiktok2',
        ];

        foreach ($order as $slug) {
            if (! isset(self::$sources[$slug]) || isset($seen[$slug])) {
                continue;
            }
            // Skip alias keys that share counts with a primary slug
            if (in_array($slug, ['ebaytwo', 'ebaythree', 'bestbuyusa', 'neweggb2c', 'tiktokshop', 'tiktokshop2'], true)) {
                continue;
            }

            $label = $displayNames[$slug] ?? self::$sources[$slug]['label'];
            $seen[$slug] = true;
            $api = $apiStatuses[$slug] ?? [
                'api_status' => 'red',
                'api_connected' => false,
                'api_updated_at' => null,
                'api_label' => 'API not linked',
            ];

            $rows[] = [
                'channel' => $label,
                'channel_slug' => $slug,
                'image' => $logos[$slug] ?? null,
                'missing_mapping_titas' => (int) ($titasCounts[$slug] ?? 0),
                'detail_url' => route('map.issues.channel', ['channel' => $slug]),
                'listings_url' => self::listingsUrlForSlug($slug),
                // mi_key MapIssues channels + pricing loaders that expose SKU detail
                'has_sku_detail' => isset(self::$sources[$slug]['mi_key'])
                    || in_array($slug, ['tiktok', 'tiktok2', 'shein', 'pls', 'temu', 'temu2'], true),
                'api_status' => $api['api_status'],
                'api_connected' => $api['api_connected'],
                'api_updated_at' => $api['api_updated_at'],
                'api_label' => $api['api_label'],
            ];
        }

        try {
            Cache::put(self::MASTER_ROWS_CACHE_KEY, $rows, now()->addMinutes(10));
            self::storeTotalTitas((int) collect($rows)->sum('missing_mapping_titas'));
        } catch (\Throwable $e) {
            // ignore
        }

        return $rows;
    }

    public static function cachedInactiveTotalOrZero(): int
    {
        try {
            $cached = Cache::get(self::INACTIVE_TOTAL_CACHE_KEY);
            if ($cached !== null) {
                return (int) $cached;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $rows = Cache::get(self::INACTIVE_MASTER_ROWS_CACHE_KEY);
            if (is_array($rows) && $rows !== []) {
                return (int) collect($rows)->sum('inactive_listings');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return 0;
    }

    public static function storeInactiveTotal(int $total): void
    {
        try {
            Cache::put(self::INACTIVE_TOTAL_CACHE_KEY, $total, now()->addMinutes(30));
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Master table rows: Marketplace Manager Inactive SKU tab only.
     *
     * @return list<array{channel: string, channel_slug: string, image: ?string, inactive_listings: int, detail_url: string, listings_url: ?string, has_sku_detail: bool, api_status: string, api_connected: bool, api_updated_at: ?string, api_label: string}>
     */
    public static function inactiveMasterRows(bool $useCache = false): array
    {
        if ($useCache) {
            try {
                $cached = Cache::get(self::INACTIVE_MASTER_ROWS_CACHE_KEY);
                if (is_array($cached) && $cached !== []) {
                    return $cached;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $inactiveCounts = self::collectListingsInactiveCounts();
        $apiStatuses = self::collectApiStatuses();
        $logos = self::logoMap();
        $displayNames = self::displayNameMap();
        $seen = [];
        $rows = [];
        $order = [
            'amazon', 'ebay', 'ebay2', 'ebay3', 'reverb', 'macys', 'bestbuy',
            'temu', 'temu2', 'shein', 'newegg', 'aliexpress',
            'pls', 'wayfair', 'faire', 'topdawg', 'tiktok', 'tiktok2',
        ];

        foreach ($order as $slug) {
            if (! isset(self::$sources[$slug]) || isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;
            $label = $displayNames[$slug] ?? self::$sources[$slug]['label'];
            $api = $apiStatuses[$slug] ?? [
                'api_status' => 'red',
                'api_connected' => false,
                'api_updated_at' => null,
                'api_label' => 'API not linked',
            ];

            $rows[] = [
                'channel' => $label,
                'channel_slug' => $slug,
                'image' => $logos[$slug] ?? null,
                'inactive_listings' => (int) ($inactiveCounts[$slug] ?? 0),
                'detail_url' => url('/inactive-listings/channel/'.$slug),
                'listings_url' => self::listingsInactiveUrlForSlug($slug),
                'has_sku_detail' => MarketplaceListingQtyMatchService::fromMapIssuesSlug($slug) !== null,
                'api_status' => $api['api_status'],
                'api_connected' => $api['api_connected'],
                'api_updated_at' => $api['api_updated_at'],
                'api_label' => $api['api_label'],
            ];
        }

        try {
            $ttl = MarketplacePortalInactiveCount::$portalSyncIncomplete ? 1 : 10;
            Cache::put(self::INACTIVE_MASTER_ROWS_CACHE_KEY, $rows, now()->addMinutes($ttl));
            self::storeInactiveTotal((int) collect($rows)->sum('inactive_listings'));
        } catch (\Throwable $e) {
            // ignore
        }

        return $rows;
    }

    /**
     * Green = API credentials present and listing data updated today.
     * Yellow = API linked but last update is not today.
     * Red = API not linked.
     *
     * @return array<string, array{api_status: string, api_connected: bool, api_updated_at: ?string, api_label: string}>
     */
    public static function collectApiStatuses(): array
    {
        try {
            $cached = Cache::get(self::API_STATUS_CACHE_KEY);
            if (is_array($cached) && $cached !== []) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $config = app(MarketplaceApiConfigService::class);
        $tz = 'Asia/Kolkata';
        $freshAfter = Carbon::now($tz)->subHours(26);
        $out = [];

        foreach (array_keys(self::$sources) as $slug) {
            if (in_array($slug, ['ebaytwo', 'ebaythree', 'bestbuyusa', 'neweggb2c', 'tiktokshop', 'tiktokshop2'], true)) {
                continue;
            }

            $linked = false;
            $linkNote = '';
            if ($slug === 'pls') {
                try {
                    $ping = app(ShopifyPlsTokenService::class)->pingShopCached();
                    $linked = (bool) ($ping['connected'] ?? false);
                    $linkNote = trim((string) ($ping['message'] ?? ''));
                } catch (\Throwable $e) {
                    $linked = $config->isConfigured('pls');
                    $linkNote = 'PLS API check failed';
                }
            } else {
                $linked = $config->isConfigured($slug);
            }

            $last = self::latestListingTimestamp($slug);
            $lastLabel = $last?->timezone($tz)->format('Y-m-d H:i');
            $updatedToday = $last !== null && $last->gte($freshAfter);

            if (! $linked) {
                $out[$slug] = [
                    'api_status' => 'red',
                    'api_connected' => false,
                    'api_updated_at' => $lastLabel,
                    'api_label' => $linkNote !== '' ? $linkNote : 'API not linked',
                ];
                continue;
            }

            if ($updatedToday) {
                $out[$slug] = [
                    'api_status' => 'green',
                    'api_connected' => true,
                    'api_updated_at' => $lastLabel,
                    'api_label' => trim(($linkNote !== '' ? $linkNote.' · ' : 'API linked · ').'updated within 24h ('.$lastLabel.' IST)'),
                ];
                continue;
            }

            $stale = $lastLabel !== null
                ? 'last update '.$lastLabel.' IST (older than 24h)'
                : 'no daily inventory sync found';
            $out[$slug] = [
                'api_status' => 'yellow',
                'api_connected' => true,
                'api_updated_at' => $lastLabel,
                'api_label' => trim(($linkNote !== '' ? $linkNote.' · ' : 'API linked · ').$stale),
            ];
        }

        try {
            Cache::put(self::API_STATUS_CACHE_KEY, $out, now()->addMinutes(10));
        } catch (\Throwable $e) {
            // ignore
        }

        return $out;
    }

    private static function latestListingTimestamp(string $slug): ?Carbon
    {
        $latest = null;

        foreach (self::listingFreshnessTables($slug) as $table) {
            $ts = self::maxTableTimestamp($table);
            if ($ts !== null && ($latest === null || $ts->gt($latest))) {
                $latest = $ts;
            }
        }

        $cronAt = self::latestCronTimestamp($slug);
        if ($cronAt !== null && ($latest === null || $cronAt->gt($latest))) {
            $latest = $cronAt;
        }

        return $latest;
    }

    /**
     * Tables the daily inventory / link-map jobs actually write (not ads metric sheets).
     *
     * @return list<string>
     */
    private static function listingFreshnessTables(string $slug): array
    {
        return match ($slug) {
            'ebay' => ['ebay_metrics'],
            'ebay2' => ['ebay_2_metrics'],
            'ebay3' => ['ebay_3_metrics'],
            'amazon' => ['amazon_listing_statuses'],
            'reverb' => ['reverb_metric', 'reverb_pricing_prices'],
            'macys' => ['macy_products', 'macys_price_data'],
            'bestbuy' => ['bestbuy_usa_products', 'bestbuy_usa_listing_statuses'],
            'temu' => ['temu_metrics'],
            'temu2' => ['temu2_metrics', 'temu2_listing_statuses'],
            'shein' => ['shein_metric', 'shein_pricing_prices', 'shein_listing_statuses'],
            'newegg' => ['newegg_metric', 'newegg_pricing_prices'],
            'aliexpress' => ['aliexpress_metric', 'aliexpress_pricing_prices', 'aliexpress_listing_statuses'],
            'pls' => ['pls_products', 'pls_listing_statuses', 'shopify_catalog_variants'],
            'wayfair' => ['wayfair_pricing_prices'],
            'faire' => ['faire_metric', 'faire_listing_statuses'],
            'topdawg' => ['topdawg_products'],
            'tiktok' => ['tiktok_products', 'tiktok_shop_listing_statuses'],
            'tiktok2' => ['tiktok_products_two', 'tiktok_two_shop_listing_statuses'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private static function cronJobNames(string $slug): array
    {
        $key = match ($slug) {
            'ebay' => 'ebay1',
            'macys' => 'macy',
            default => $slug,
        };

        return [
            $key.'-sync-inventory',
            $key.'-sync-mismatch-inventory',
            $key.'-sync-link-map',
        ];
    }

    private static function maxTableTimestamp(string $table): ?Carbon
    {
        try {
            if (! Schema::hasTable($table)) {
                return null;
            }
            $latest = null;
            foreach (['updated_at', 'last_synced_at', 'last_sync_at'] as $col) {
                if (! Schema::hasColumn($table, $col)) {
                    continue;
                }
                $raw = DB::table($table)->max($col);
                if ($raw === null || $raw === '') {
                    continue;
                }
                $ts = Carbon::parse((string) $raw);
                if ($latest === null || $ts->gt($latest)) {
                    $latest = $ts;
                }
            }

            return $latest;
        } catch (\Throwable $e) {
            Log::warning('MappingChannelCounts API timestamp failed for '.$table.': '.$e->getMessage());

            return null;
        }
    }

    private static function latestCronTimestamp(string $slug): ?Carbon
    {
        try {
            if (! Schema::hasTable('cron_execution_logs')) {
                return null;
            }
            $names = self::cronJobNames($slug);
            $q = DB::table('cron_execution_logs')
                ->where(function ($q) use ($names) {
                    $q->whereIn('job_name', $names);
                    if (Schema::hasColumn('cron_execution_logs', 'command')) {
                        $q->orWhereIn('command', $names);
                    }
                });
            if (Schema::hasColumn('cron_execution_logs', 'status')) {
                $q->whereIn('status', ['success', 'recovered', 'partial_success', 'running']);
            }
            $raw = null;
            if (Schema::hasColumn('cron_execution_logs', 'finished_at')) {
                $raw = (clone $q)->max('finished_at');
            }
            if (($raw === null || $raw === '') && Schema::hasColumn('cron_execution_logs', 'started_at')) {
                $raw = (clone $q)->max('started_at');
            }
            if ($raw === null || $raw === '') {
                return null;
            }

            return Carbon::parse((string) $raw);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Marketplace Manager listings (Active SKU Mismatch) for a /map-issues slug.
     */
    public static function listingsUrlForSlug(string $slug): ?string
    {
        $mm = MarketplaceListingQtyMatchService::fromMapIssuesSlug($slug);
        if ($mm === null) {
            return null;
        }

        try {
            return url('/marketplace/'.$mm.'/products?link=mismatch');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function listingsInactiveUrlForSlug(string $slug): ?string
    {
        $mm = MarketplaceListingQtyMatchService::fromMapIssuesSlug($slug);
        if ($mm === null) {
            return null;
        }

        try {
            return url('/marketplace/'.$mm.'/products?link=matched_inactive');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Inactive SKU tab count from Marketplace Manager listings.
     *
     * @return array<string, int>
     */
    public static function collectListingsInactiveCounts(): array
    {
        $counts = [];
        $seenMm = [];

        foreach (self::$sources as $slug => $meta) {
            $mm = MarketplaceListingQtyMatchService::fromMapIssuesSlug($slug);
            if ($mm === null) {
                continue;
            }
            try {
                if (! array_key_exists($mm, $seenMm)) {
                    $seenMm[$mm] = MarketplacePortalInactiveCount::count($mm);
                }
                $counts[$slug] = (int) $seenMm[$mm];
            } catch (\Throwable $e) {
                Log::warning("MappingChannelCounts {$slug} inactive listings failed: ".$e->getMessage());
                $counts[$slug] = (int) ($seenMm[$mm] ?? 0);
            }
        }

        return $counts;
    }

    /**
     * Active SKU Mismatch from Marketplace Manager listings
     * (/marketplace/{channel}/products — same pages as the Link column).
     *
     * @return array<string, int>
     */
    public static function collectListingsMismatchCounts(): array
    {
        $counts = [];
        $match = app(MarketplaceListingQtyMatchService::class);
        $seenMm = [];

        foreach (self::$sources as $slug => $meta) {
            $mm = MarketplaceListingQtyMatchService::fromMapIssuesSlug($slug);
            if ($mm === null) {
                continue;
            }
            try {
                if (! array_key_exists($mm, $seenMm)) {
                    $seenMm[$mm] = $match->activeMismatchCount($mm, false);
                }
                $counts[$slug] = (int) $seenMm[$mm];
            } catch (\Throwable $e) {
                Log::warning("MappingChannelCounts {$slug} listings mismatch failed: ".$e->getMessage());
                $counts[$slug] = (int) ($seenMm[$mm] ?? 0);
            }
        }

        return $counts;
    }

    /**
     * @return array<string, string>
     */
    private static function logoMap(): array
    {
        $map = [];
        if (! Schema::hasTable('channel_master') || ! Schema::hasColumn('channel_master', 'logo')) {
            return $map;
        }

        ChannelMaster::query()
            ->whereNotNull('channel')
            ->where('channel', '!=', '')
            ->get(['channel', 'logo'])
            ->each(function ($row) use (&$map) {
                $key = self::normalize((string) $row->channel);
                if ($key !== '' && ! empty($row->logo) && empty($map[$key])) {
                    $map[$key] = (string) $row->logo;
                }
            });

        return $map;
    }

    /**
     * Prefer Active Channel display names when they match a mapping slug.
     *
     * @return array<string, string>
     */
    private static function displayNameMap(): array
    {
        $map = [];
        if (! Schema::hasTable('channel_master')) {
            return $map;
        }

        $q = ChannelMaster::query()
            ->whereNotNull('channel')
            ->where('channel', '!=', '');
        if (Schema::hasColumn('channel_master', 'status')) {
            $q->whereRaw('LOWER(TRIM(status)) = ?', ['active']);
        }

        $q->get(['channel'])->each(function ($row) use (&$map) {
            $key = self::normalize((string) $row->channel);
            if ($key !== '' && isset(self::$sources[$key]) && empty($map[$key])) {
                $map[$key] = (string) $row->channel;
            }
        });

        return $map;
    }
}
