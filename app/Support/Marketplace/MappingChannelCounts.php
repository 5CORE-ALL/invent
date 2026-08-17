<?php

namespace App\Support\Marketplace;

use App\Http\Controllers\MarketPlace\AliexpressController;
use App\Http\Controllers\MarketPlace\FaireController;
use App\Http\Controllers\MarketPlace\MacyController;
use App\Http\Controllers\MarketPlace\PlsController;
use App\Http\Controllers\MarketPlace\ReverbController;
use App\Http\Controllers\MarketPlace\SheinController;
use App\Http\Controllers\MarketPlace\TemuController;
use App\Services\MarketplaceManager\MarketplaceListingQtyMatchService;
use App\Http\Controllers\MarketPlace\TikTokPricingController;
use App\Http\Controllers\MarketPlace\TopDawgPricingController;
use App\Http\Controllers\MarketPlace\WayfairController;
use App\Models\ChannelMaster;
use App\Services\ShopifyPlsTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Missing Mapping (N Map) counts from Marketplace Manager listings qty mismatch
 * (same Active + Inactive SKU Mismatch tabs).
 */
class MappingChannelCounts
{
    public const TOTAL_CACHE_KEY = 'mapping_pages_nmap_total_v2';

    /**
     * Channels with a page-level N Map source.
     * mi_key = MapIssuesController::data() count field (batch, page-aligned).
     * loader = dedicated pricing-page badge counter (when not in MapIssues).
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
     * @return array{map: int, miss: int, nmap: int}
     */
    public static function forChannel(string $channel, bool $useCache = true): array
    {
        $key = self::normalize($channel);
        if ($key === '' || ! isset(self::$sources[$key])) {
            return ['map' => 0, 'miss' => 0, 'nmap' => 0];
        }

        $cacheKey = 'mapping_channel_nmap_v2:'.$key;
        if ($useCache) {
            try {
                $cached = Cache::get($cacheKey);
                if (is_array($cached) && isset($cached['nmap'])) {
                    return $cached;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $all = self::collectPageCounts(!$useCache);
        $nmap = (int) ($all[$key] ?? 0);
        $result = ['map' => 0, 'miss' => 0, 'nmap' => $nmap];

        try {
            Cache::put($cacheKey, $result, now()->addMinutes(10));
        } catch (\Throwable $e) {
            // ignore
        }

        return $result;
    }

    public static function nmap(string $channel, bool $useCache = true): int
    {
        return (int) (self::forChannel($channel, $useCache)['nmap'] ?? 0);
    }

    /**
     * Sum of page N Map across unique mapping sources (deduped aliases).
     */
    public static function totalNmap(bool $useCache = true): int
    {
        if ($useCache) {
            try {
                $cached = Cache::get(self::TOTAL_CACHE_KEY);
                if ($cached !== null) {
                    return (int) $cached;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $rows = self::masterRows(false);
        $total = (int) collect($rows)->sum('missing_mapping');

        try {
            Cache::put(self::TOTAL_CACHE_KEY, $total, now()->addMinutes(10));
        } catch (\Throwable $e) {
            // ignore
        }

        return $total;
    }

    public static function storeTotalNmap(int $total): void
    {
        try {
            Cache::put(self::TOTAL_CACHE_KEY, $total, now()->addMinutes(30));
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Master table rows: one row per unique mapping channel (page N Map).
     *
     * @return list<array{channel: string, channel_slug: string, image: ?string, missing_mapping: int, detail_url: string, has_sku_detail: bool}>
     */
    public static function masterRows(bool $useCache = false): array
    {
        $pageCounts = self::collectPageCounts($useCache === false);
        $logos = self::logoMap();
        $displayNames = self::displayNameMap();
        $plsApi = ['connected' => null, 'message' => null];
        try {
            $plsApi = app(ShopifyPlsTokenService::class)->pingShopCached();
        } catch (\Throwable $e) {
            $plsApi = ['connected' => false, 'message' => 'PLS API check failed'];
        }

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
            $nmap = (int) ($pageCounts[$slug] ?? 0);
            $seen[$slug] = true;

            $rows[] = [
                'channel' => $label,
                'channel_slug' => $slug,
                'image' => $logos[$slug] ?? null,
                'missing_mapping' => $nmap,
                'detail_url' => route('map.issues.channel', ['channel' => $slug]),
                // mi_key MapIssues channels + pricing loaders that expose SKU detail
                'has_sku_detail' => isset(self::$sources[$slug]['mi_key'])
                    || in_array($slug, ['tiktok', 'tiktok2', 'shein', 'pls', 'temu', 'temu2'], true),
                'api_connected' => $slug === 'pls' ? (bool) ($plsApi['connected'] ?? false) : null,
                'api_label' => $slug === 'pls' ? (string) ($plsApi['message'] ?? '') : null,
            ];
        }

        return $rows;
    }

    /**
     * Collect N Map ints keyed by normalized slug from Marketplace Manager listings.
     *
     * @return array<string, int>
     */
    public static function collectPageCounts(bool $fresh = true): array
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
                    $seenMm[$mm] = $match->mismatchCount($mm);
                }
                $counts[$slug] = (int) $seenMm[$mm];
            } catch (\Throwable $e) {
                Log::warning("MappingChannelCounts {$slug} listings mismatch failed: ".$e->getMessage());
                $counts[$slug] = (int) ($seenMm[$mm] ?? 0);
            }
        }

        return $counts;
    }

    private static function fromReverbPage(): int
    {
        $raw = app(ReverbController::class)->reverbDataJson(Request::create('/reverb-data-json', 'GET'));
        $payload = self::jsonPayload($raw);
        if (isset($payload['map_miss_summary']['nmap'])) {
            return (int) $payload['map_miss_summary']['nmap'];
        }
        $rows = self::rowsFromPayload($payload);

        return (int) (app(ReverbController::class)->computeReverbMapMissCounts($rows)['nmap'] ?? 0);
    }

    private static function fromMacysPage(): int
    {
        $raw = app(MacyController::class)->getViewMacysTabulatorData(Request::create('/macys-data-json', 'GET'));
        $rows = self::rowsFromPayload(self::jsonPayload($raw));

        return (int) (MacyController::countMacysPricingBadgeTotals($rows)['nmap'] ?? 0);
    }

    private static function fromSheinPage(): int
    {
        $raw = app(SheinController::class)->getSheinPricingData(Request::create('/shein/pricing-data', 'GET'));
        $rows = self::rowsFromPayload(self::jsonPayload($raw));

        return (int) (SheinController::countSheinPricingBadgeTotals($rows)['nmap'] ?? 0);
    }

    private static function fromAliexpressPage(): int
    {
        $raw = app(AliexpressController::class)->getPricingData(Request::create('/aliexpress/pricing-data', 'GET'));
        $rows = self::rowsFromPayload(self::jsonPayload($raw));

        return (int) (AliexpressController::countAliexpressPricingBadgeTotals($rows)['nmap'] ?? 0);
    }

    private static function fromPlsPage(): int
    {
        $raw = app(PlsController::class)->pricingDataJson(Request::create('/pls-pricing-data-json', 'GET'));
        $rows = self::rowsFromPayload(self::jsonPayload($raw));

        return (int) (PlsController::countPlsPricingBadgeTotals($rows)['nmap'] ?? 0);
    }

    private static function fromWayfairPage(): int
    {
        $raw = app(WayfairController::class)->getWayfairPricingData(Request::create('/wayfair/pricing-data', 'GET'));
        $rows = self::rowsFromPayload(self::jsonPayload($raw));

        return (int) (WayfairController::countWayfairPricingBadgeTotals($rows)['nmap'] ?? 0);
    }

    private static function fromFairePage(): int
    {
        $raw = app(FaireController::class)->getFairePricingData(Request::create('/faire/pricing-data', 'GET'));
        $rows = self::rowsFromPayload(self::jsonPayload($raw));

        return (int) (FaireController::countFairePricingBadgeTotals($rows)['nmap'] ?? 0);
    }

    private static function fromTopDawgPage(): int
    {
        $raw = app(TopDawgPricingController::class)->getViewTopDawgTabularData(Request::create('/topdawg-data-json', 'GET'));
        $rows = self::rowsFromPayload(self::jsonPayload($raw));

        return (int) (TopDawgPricingController::countTopDawgPricingBadgeTotals($rows)['nmap'] ?? 0);
    }

    private static function fromTemuPage(bool $isTemu2): int
    {
        if ($isTemu2) {
            // Same source as /marketplace/temu2/products Active + Inactive SKU Mismatch.
            return app(Temu2SyncController::class)->listingQtyMismatchCount();
        }

        $temu = app(TemuController::class);
        $raw = $temu->getTemuDecreaseData(Request::create('/temu-decrease-data', 'GET'));
        $rows = self::rowsFromPayload(self::jsonPayload($raw));

        return count(TemuController::nmapSkuRowsFromDecrease($rows));
    }

    private static function fromTikTokPage(string $variant): int
    {
        $raw = app(TikTokPricingController::class)->getViewTikTokTabularData(
            Request::create($variant === 'v2' ? '/tiktok-2-data-json' : '/tiktok-data-json', 'GET'),
            $variant
        );
        $rows = self::rowsFromPayload(self::jsonPayload($raw));

        // Same N Map rules as /tiktok-pricing badge (skip Missing L / parents; |INV−TT Stock| > 3)
        return TikTokPricingController::countNmapFromTabular($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private static function jsonPayload(mixed $response): array
    {
        if ($response instanceof JsonResponse) {
            $decoded = json_decode($response->getContent(), true);

            return is_array($decoded) ? $decoded : [];
        }
        if (is_array($response)) {
            return $response;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private static function rowsFromPayload(array $payload): array
    {
        if (isset($payload['data']) && is_array($payload['data'])) {
            $data = $payload['data'];
            // Macys returns objects — normalize
            return array_map(function ($row) {
                return is_object($row) ? (array) $row : (is_array($row) ? $row : []);
            }, array_values($data));
        }

        if ($payload !== [] && array_is_list($payload)) {
            return array_map(function ($row) {
                return is_object($row) ? (array) $row : (is_array($row) ? $row : []);
            }, $payload);
        }

        return [];
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
