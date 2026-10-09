<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\ChannelMasterSummary;
use App\Models\MissingListingDar;
use App\Jobs\RebuildMissingListingPageJob;
use App\Support\Marketplace\CpMasterCounts;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingInactiveParentChildCounts;
use App\Support\Marketplace\SheetListingCatalog;
use App\Support\Marketplace\SheetListingCatalogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Missing Listing page — Tabulator view.
 *
 * Same universe as each channel's /listing-* page:
 * Product Master SKUs with Shopify INV > 0, minus PARENT, minus NRL.
 * Disconnected marketplace APIs show as Offline (no invented Missing L).
 * History chart from daily listing_miss_count snapshots (California dates).
 */
class MissingListingController extends Controller
{
    private const TZ = 'America/Los_Angeles';

    /** Default Seller Portal when channel_master.seller_link is empty. */
    private const DEFAULT_SELLER_PORTALS = [
        'faire' => 'https://www.faire.com/brand-portal/my-shop/products',
        'depop' => 'https://www.depop.com/sellinghub/',
        'vinted' => 'https://www.vinted.com/inbox',
        'dhgate' => 'https://seller.dhgate.com/',
        'tiendamia' => 'https://www.tiendamia.com/',
    ];

    public const PAGE_CACHE_KEY = 'missing_listing.page_payload_v6';

    private const PAGE_CACHE_TTL_DAYS = 7;

    /** @var list<string> */
    public const LISTING_MODES = ['Auto', 'CSV', 'Manual', 'Semi'];

    public function index()
    {
        return view('market-places.Missing_listing');
    }

    public function getData(Request $request)
    {
        try {
            $cached = Cache::get(self::PAGE_CACHE_KEY);
            if (is_array($cached) && ! empty($cached['data'])) {
                try {
                    $cached['data'] = $this->overlayListingModes($cached['data']);
                    $cached['data'] = $this->appendFbMarketplaceRow($cached['data']);
                } catch (\Throwable $e) {
                    Log::warning('Missing Listing overlayListingModes failed: '.$e->getMessage());
                }
                $this->dispatchPageRebuildIfNeeded($cached);

                return response()->json($cached);
            }

            try {
                $payload = $this->buildSkeletonPagePayload();
            } catch (\Throwable $e) {
                Log::warning('Missing Listing skeleton failed: '.$e->getMessage());
                $payload = $this->buildMinimalChannelPayload();
            }
            $this->dispatchPageRebuildIfNeeded(null);

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('Missing Listing getData failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Full Missing L table from local catalogs (no live marketplace API).
     *
     * @return array{success: bool, data: list<array<string, mixed>>, count: int, total_missing_l: int, computed_at: string, partial?: bool}
     */
    public function rebuildPagePayload(): array
    {
        @set_time_limit(180);
        $payload = $this->buildFullPagePayload();
        Cache::put(self::PAGE_CACHE_KEY, $payload, now()->addDays(self::PAGE_CACHE_TTL_DAYS));

        return $payload;
    }

    /**
     * Missing L history chart — listing-page snapshots, California calendar dates.
     */
    public function chartData(Request $request)
    {
        try {
            $rawChannel = trim((string) $request->input('channel', 'all'));
            $channelKey = ListingChannelCounts::normalize($rawChannel);
            $isAll = $channelKey === '' || $channelKey === 'all';
            $days = (int) $request->input('days', 32);
            $badgeValue = $request->input('badge_value');
            $hasBadge = $badgeValue !== null && $badgeValue !== '' && is_numeric($badgeValue);

            if (! Schema::hasTable('channel_master_daily_data')) {
                return response()->json(['success' => true, 'data' => $this->chartWithLiveOnly($isAll, $channelKey, $badgeValue)]);
            }

            $query = ChannelMasterSummary::query()->orderBy('snapshot_date', 'asc');
            if ($days > 0) {
                $startDate = now(self::TZ)->subDays(max($days - 1, 0))->toDateString();
                $query->whereDate('snapshot_date', '>=', $startDate);
            }

            $history = $query->get(['channel', 'snapshot_date', 'summary_data']);
            if (! $isAll) {
                $wanted = array_fill_keys($this->channelAliases($channelKey), true);
                $history = $history->filter(function ($row) use ($wanted) {
                    $name = (string) $row->channel;
                    $flat = strtolower((string) preg_replace('/[^a-z0-9]/', '', $name));

                    return isset($wanted[strtolower(trim($name))])
                        || isset($wanted[ListingChannelCounts::normalize($name)])
                        || ($flat !== '' && isset($wanted[$flat]));
                })->values();
            }

            // Group by California snapshot_date (same-day listing capture — no −1 shift)
            $grouped = $history->groupBy(function ($row) {
                return Carbon::parse($row->snapshot_date)->timezone(self::TZ)->toDateString();
            })->sortKeys();

            $chartData = [];
            foreach ($grouped as $dateKey => $rows) {
                $label = Carbon::parse($dateKey, self::TZ)->format('M d');
                $value = 0.0;

                if ($isAll) {
                    // Overall Missing Listing: one listing_miss_count per marketplace.
                    // miss_count is a different Active Channel metric and must not be added in.
                    $byChannel = [];
                    foreach ($rows as $row) {
                        $sd = is_array($row->summary_data) ? $row->summary_data : [];
                        if (! array_key_exists('listing_miss_count', $sd)) {
                            continue;
                        }
                        $ck = $this->chartCanonicalKey((string) $row->channel);
                        if (! isset($byChannel[$ck])) {
                            $byChannel[$ck] = (float) $sd['listing_miss_count'];
                        }
                    }
                    $value = array_sum($byChannel);
                } else {
                    $best = null;
                    foreach ($rows as $row) {
                        $sd = is_array($row->summary_data) ? $row->summary_data : [];
                        if (array_key_exists('listing_miss_count', $sd)) {
                            $best = (float) $sd['listing_miss_count'];
                            break;
                        }
                        if ($best === null) {
                            $best = (float) ($sd['miss_count'] ?? 0);
                        }
                    }
                    $value = (float) ($best ?? 0);
                }

                $chartData[] = [
                    'date' => $label,
                    'date_key' => $dateKey,
                    'value' => round($value, 2),
                ];
            }

            // Ensure today's California point matches live listing page Missing L
            $todayKey = now(self::TZ)->toDateString();
            $todayLabel = now(self::TZ)->format('M d');
            // The page already has today's Missing L. Recounting here is what made each
            // marketplace chart time out before any history could be drawn.
            $live = $hasBadge ? (float) $badgeValue : $this->liveMissingL($isAll, $channelKey);

            $replaced = false;
            foreach ($chartData as &$point) {
                if (($point['date_key'] ?? '') === $todayKey) {
                    $point['value'] = round($live, 2);
                    $replaced = true;
                }
            }
            unset($point);

            if (! $replaced) {
                $chartData[] = [
                    'date' => $todayLabel,
                    'date_key' => $todayKey,
                    'value' => round($live, 2),
                ];
            }

            // Drop helper key from payload
            $chartData = array_map(function ($p) {
                return ['date' => $p['date'], 'value' => $p['value']];
            }, $chartData);

            return response()->json(['success' => true, 'data' => array_values($chartData)]);
        } catch (\Throwable $e) {
            Log::error('Missing Listing chartData failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    /**
     * @return Collection<int, ChannelMaster>
     */
    private function loadMasterRows(): Collection
    {
        $hasLogo = Schema::hasTable('channel_master')
            && Schema::hasColumn('channel_master', 'logo');
        $hasSellerLink = Schema::hasTable('channel_master')
            && Schema::hasColumn('channel_master', 'seller_link');
        $hasListingMode = Schema::hasTable('channel_master')
            && Schema::hasColumn('channel_master', 'listing_mode');

        $masterColumns = ['id', 'channel', 'status'];
        if (Schema::hasColumn('channel_master', 'type')) {
            $masterColumns[] = 'type';
        }
        if ($hasLogo) {
            $masterColumns[] = 'logo';
        }
        if ($hasSellerLink) {
            $masterColumns[] = 'seller_link';
        }
        if ($hasListingMode) {
            $masterColumns[] = 'listing_mode';
        }

        if (! Schema::hasTable('channel_master')) {
            return collect();
        }

        return ChannelMaster::whereNotNull('channel')
            ->where('channel', '!=', '')
            ->orderBy('channel')
            ->get($masterColumns)
            ->filter(function ($master) {
                $key = ListingChannelCounts::normalize((string) $master->channel);
                if (in_array($key, ['fbmarketplace', 'facebookmarketplace'], true)) {
                    return true;
                }

                return ListingChannelCounts::shouldShowOnMissingListing(
                    (string) $master->channel,
                    $master->status ?? ''
                );
            })
            ->filter(fn ($master) => ListingChannelCounts::hasListingSource((string) $master->channel))
            ->values();
    }

    /**
     * @return array{success: bool, data: list<array<string, mixed>>, count: int, total_missing_l: int, computed_at: string, partial: bool}
     */
    private function buildSkeletonPagePayload(): array
    {
        $hasLogo = Schema::hasTable('channel_master') && Schema::hasColumn('channel_master', 'logo');
        $hasSellerLink = Schema::hasTable('channel_master') && Schema::hasColumn('channel_master', 'seller_link');
        $cpMasterCounts = $this->cachedCpMasterCounts();
        $cpSkuCount = (int) ($cpMasterCounts['SKU'] ?? 0);
        $cpZeroInv = (int) ($cpMasterCounts['ZeroInv'] ?? 0);
        $snapshots = $this->latestListingSnapshots();

        $data = $this->loadMasterRows()->map(function ($master) use ($hasLogo, $hasSellerLink, $cpSkuCount, $cpZeroInv, $snapshots) {
            $channel = (string) $master->channel;
            $dataSource = ListingChannelCounts::dataSource($channel);
            $key = ListingChannelCounts::normalize($channel);
            $snap = $snapshots[$key] ?? [];
            $counts = ListingChannelCounts::showsComputedCounts($channel);

            return [
                'id' => $master->id,
                'image' => $hasLogo ? ($master->logo ?? null) : null,
                'channel' => $channel,
                'listing_url' => ListingChannelCounts::listingUrl($channel),
                'data_source' => $dataSource,
                'allows_csv_upload' => ListingChannelCounts::isCsvCatalogSource($channel),
                'csv_import_url' => ListingChannelCounts::csvImportUrl($channel),
                'sku' => $cpSkuCount,
                'zero_inv' => $cpZeroInv,
                'req' => $counts ? (int) ($snap['listing_req'] ?? 0) : null,
                'nrl' => $counts ? (int) ($snap['listing_nrl'] ?? 0) : null,
                'listed' => $counts ? (int) ($snap['listing_listed'] ?? 0) : null,
                'missing_listing' => $counts ? (int) ($snap['listing_miss_count'] ?? 0) : null,
                'inactive_parent' => 0,
                'inactive_child' => 0,
                'inactive_listings_url' => null,
                'listing_mode' => $this->listingModeFor($master),
                'type' => $this->listingTypeFor($master),
                'seller_portal' => $this->sellerPortalFor($master, $hasSellerLink),
            ];
        })->values();

        $totalMissingL = (int) $data
            ->filter(fn ($row) => in_array($row['data_source'] ?? '', ['API', 'CSV'], true))
            ->sum(fn ($row) => (int) ($row['missing_listing'] ?? 0));

        return [
            'success' => true,
            'data' => $this->appendFbMarketplaceRow($data->all()),
            'count' => $data->count(),
            'total_missing_l' => $totalMissingL,
            'computed_at' => now()->toIso8601String(),
            'partial' => true,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function latestListingSnapshots(): array
    {
        if (! Schema::hasTable('channel_master_daily_data')) {
            return [];
        }

        $out = [];
        $rows = ChannelMasterSummary::query()
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id')
            ->get(['channel', 'summary_data']);
        foreach ($rows as $row) {
            $key = ListingChannelCounts::normalize((string) $row->channel);
            if ($key === '' || isset($out[$key])) {
                continue;
            }
            $out[$key] = $row->summaryArray();
        }

        return $out;
    }

    /**
     * @return array{success: bool, data: list<array<string, mixed>>, count: int, total_missing_l: int, computed_at: string, partial: bool}
     */
    private function buildFullPagePayload(): array
    {
        $hasLogo = Schema::hasTable('channel_master') && Schema::hasColumn('channel_master', 'logo');
        $hasSellerLink = Schema::hasTable('channel_master') && Schema::hasColumn('channel_master', 'seller_link');
        $cpMasterCounts = CpMasterCounts::counts(true);
        $cpSkuCount = (int) ($cpMasterCounts['SKU'] ?? 0);
        $cpZeroInv = (int) ($cpMasterCounts['ZeroInv'] ?? 0);

        $data = $this->loadMasterRows()->map(function ($master) use ($hasLogo, $hasSellerLink, $cpSkuCount, $cpZeroInv) {
            $channel = (string) $master->channel;
            $dataSource = ListingChannelCounts::dataSource($channel);

            try {
                $inactive = ListingInactiveParentChildCounts::forChannel($channel);
            } catch (\Throwable $e) {
                Log::warning('Missing Listing inactive counts failed for '.$channel.': '.$e->getMessage());
                $inactive = ['parent' => 0, 'child' => 0, 'url' => null];
            }

            if (! ListingChannelCounts::showsComputedCounts($channel)) {
                return [
                    'id' => $master->id,
                    'image' => $hasLogo ? ($master->logo ?? null) : null,
                    'channel' => $channel,
                    'listing_url' => ListingChannelCounts::listingUrl($channel),
                    'data_source' => $dataSource === 'Offline' ? 'Offline' : 'Sheet',
                    'allows_csv_upload' => false,
                    'csv_import_url' => null,
                    'sku' => $cpSkuCount,
                    'zero_inv' => $cpZeroInv,
                    'req' => null,
                    'nrl' => null,
                    'listed' => null,
                    'missing_listing' => null,
                    'inactive_parent' => (int) ($inactive['parent'] ?? 0),
                    'inactive_child' => (int) ($inactive['child'] ?? 0),
                    'inactive_listings_url' => $inactive['url'] ?? null,
                    'listing_mode' => $this->listingModeFor($master),
                    'type' => $this->listingTypeFor($master),
                    'seller_portal' => $this->sellerPortalFor($master, $hasSellerLink),
                ];
            }

            try {
                $listingCounts = ListingChannelCounts::forChannel($channel, true);
            } catch (\Throwable $e) {
                Log::warning('Missing Listing counts failed for '.$channel.': '.$e->getMessage());
                $listingCounts = ['REQ' => 0, 'NRL' => 0, 'Listed' => 0, 'Pending' => 0];
            }

            return [
                'id' => $master->id,
                'image' => $hasLogo ? ($master->logo ?? null) : null,
                'channel' => $channel,
                'listing_url' => ListingChannelCounts::listingUrl($channel),
                'data_source' => $dataSource,
                'allows_csv_upload' => ListingChannelCounts::isCsvCatalogSource($channel),
                'csv_import_url' => ListingChannelCounts::csvImportUrl($channel),
                'sku' => $cpSkuCount,
                'zero_inv' => $cpZeroInv,
                'req' => (int) ($listingCounts['REQ'] ?? 0),
                'nrl' => (int) ($listingCounts['NRL'] ?? 0),
                'listed' => (int) ($listingCounts['Listed'] ?? 0),
                'missing_listing' => (int) ($listingCounts['Pending'] ?? 0),
                'inactive_parent' => (int) ($inactive['parent'] ?? 0),
                'inactive_child' => (int) ($inactive['child'] ?? 0),
                'inactive_listings_url' => $inactive['url'] ?? null,
                'listing_mode' => $this->listingModeFor($master),
                'type' => $this->listingTypeFor($master),
                'seller_portal' => $this->sellerPortalFor($master, $hasSellerLink),
            ];
        })->values();

        $this->persistListingMissingHistory($data);

        $totalMissingL = (int) $data
            ->filter(fn ($row) => in_array($row['data_source'] ?? '', ['API', 'CSV'], true))
            ->sum(fn ($row) => (int) ($row['missing_listing'] ?? 0));
        ListingChannelCounts::storeTotalMissingL($totalMissingL);

        return [
            'success' => true,
            'data' => $this->appendFbMarketplaceRow($data->all()),
            'count' => $data->count(),
            'total_missing_l' => $totalMissingL,
            'computed_at' => now()->toIso8601String(),
            'partial' => false,
        ];
    }

    /**
     * @return array{SKU: int, ZeroInv: int}
     */
    private function cachedCpMasterCounts(): array
    {
        $cached = Cache::get('cp_master_sku_zero_inv_v1');
        if (is_array($cached)) {
            return [
                'SKU' => (int) ($cached['SKU'] ?? 0),
                'ZeroInv' => (int) ($cached['ZeroInv'] ?? 0),
            ];
        }

        return ['SKU' => 0, 'ZeroInv' => 0];
    }

    /**
     * Channel rows only — last-resort payload when even the snapshot skeleton fails.
     *
     * @return array{success: bool, data: list<array<string, mixed>>, count: int, total_missing_l: int, computed_at: string, partial: bool}
     */
    private function buildMinimalChannelPayload(): array
    {
        $hasLogo = Schema::hasTable('channel_master') && Schema::hasColumn('channel_master', 'logo');
        $hasSellerLink = Schema::hasTable('channel_master') && Schema::hasColumn('channel_master', 'seller_link');

        $data = $this->loadMasterRows()->map(function ($master) use ($hasLogo, $hasSellerLink) {
            $channel = (string) $master->channel;

            return [
                'id' => $master->id,
                'image' => $hasLogo ? ($master->logo ?? null) : null,
                'channel' => $channel,
                'listing_url' => ListingChannelCounts::listingUrl($channel),
                'data_source' => ListingChannelCounts::dataSource($channel),
                'allows_csv_upload' => ListingChannelCounts::isCsvCatalogSource($channel),
                'csv_import_url' => ListingChannelCounts::csvImportUrl($channel),
                'sku' => 0,
                'zero_inv' => 0,
                'req' => null,
                'nrl' => null,
                'listed' => null,
                'missing_listing' => null,
                'inactive_parent' => 0,
                'inactive_child' => 0,
                'inactive_listings_url' => null,
                'listing_mode' => $this->listingModeFor($master),
                'type' => $this->listingTypeFor($master),
                'seller_portal' => $this->sellerPortalFor($master, $hasSellerLink),
            ];
        })->values();

        return [
            'success' => true,
            'data' => $this->appendFbMarketplaceRow($data->all()),
            'count' => $data->count(),
            'total_missing_l' => 0,
            'computed_at' => now()->toIso8601String(),
            'partial' => true,
        ];
    }

    /**
     * Rebuild on a real queue worker only. afterResponse keeps nginx waiting
     * until every channel recount finishes, which is what 504s this page.
     *
     * @param  array<string, mixed>|null  $cached
     */
    private function dispatchPageRebuildIfNeeded(?array $cached): void
    {
        if (app()->runningInConsole() || config('queue.default') === 'sync') {
            return;
        }

        $computedAt = trim((string) ($cached['computed_at'] ?? ''));
        $partial = ! empty($cached['partial']);
        if (! $partial && $computedAt !== '') {
            try {
                if (now()->diffInSeconds(Carbon::parse($computedAt)) < 180) {
                    return;
                }
            } catch (\Throwable $e) {
                // rebuild
            }
        }

        try {
            RebuildMissingListingPageJob::dispatch();
        } catch (\Throwable $e) {
            Log::warning('Missing Listing page rebuild dispatch skipped: '.$e->getMessage());
        }
    }

    public static function normalizeListingMode(mixed $value): ?string
    {
        $key = strtolower(trim((string) $value));
        $map = [
            'auto' => 'Auto',
            'csv' => 'CSV',
            'manual' => 'Manual',
            'semi' => 'Semi',
        ];

        return $map[$key] ?? null;
    }

    private function listingModeFor(ChannelMaster $master): ?string
    {
        if (! Schema::hasColumn('channel_master', 'listing_mode')) {
            return null;
        }

        $mode = self::normalizeListingMode($master->listing_mode ?? null);
        if ($mode === null && ListingChannelCounts::isCsvCatalogSource((string) $master->channel)) {
            return 'CSV';
        }

        return $mode;
    }

    private function listingTypeFor(?ChannelMaster $master): string
    {
        $raw = strtoupper(trim((string) ($master->type ?? '')));

        return in_array($raw, ['B2B', 'B2C', 'C2C', 'DROPSHIP', 'WHOLESALE'], true)
            ? ($raw === 'DROPSHIP' ? 'Dropship' : ($raw === 'WHOLESALE' ? 'Wholesale' : $raw))
            : '';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function appendFbMarketplaceRow(array $rows): array
    {
        foreach ($rows as $row) {
            $key = ListingChannelCounts::normalize((string) ($row['channel'] ?? ''));
            if (in_array($key, ['fbmarketplace', 'facebookmarketplace'], true)) {
                return $rows;
            }
        }

        $master = $this->fbMarketplaceMaster();
        if ($master === null) {
            return $rows;
        }

        $snap = $this->latestListingSnapshots()['fbmarketplace']
            ?? $this->latestListingSnapshots()['facebookmarketplace']
            ?? [];
        $cp = $this->cachedCpMasterCounts();

        $rows[] = [
            'id' => $master->id,
            'image' => $master->logo ?? null,
            'channel' => (string) ($master->channel ?: 'FB Marketplace'),
            'listing_url' => ListingChannelCounts::listingUrl('fbmarketplace'),
            'data_source' => 'CSV',
            'allows_csv_upload' => true,
            'csv_import_url' => ListingChannelCounts::csvImportUrl('fbmarketplace'),
            'sku' => (int) ($cp['SKU'] ?? 0),
            'zero_inv' => (int) ($cp['ZeroInv'] ?? 0),
            'req' => (int) ($snap['listing_req'] ?? 0),
            'nrl' => (int) ($snap['listing_nrl'] ?? 0),
            'listed' => (int) ($snap['listing_listed'] ?? 0),
            'missing_listing' => (int) ($snap['listing_miss_count'] ?? 0),
            'inactive_parent' => 0,
            'inactive_child' => 0,
            'inactive_listings_url' => null,
            'listing_mode' => $this->listingModeFor($master),
            'type' => $this->listingTypeFor($master) !== '' ? $this->listingTypeFor($master) : 'C2C',
            'seller_portal' => $this->sellerPortalFor($master, Schema::hasColumn('channel_master', 'seller_link')),
        ];

        return $rows;
    }

    private function fbMarketplaceMaster(): ?ChannelMaster
    {
        if (! Schema::hasTable('channel_master')) {
            return null;
        }

        $found = ChannelMaster::query()
            ->whereNotNull('channel')
            ->get()
            ->first(function ($master) {
                $key = ListingChannelCounts::normalize((string) $master->channel);

                return in_array($key, ['fbmarketplace', 'facebookmarketplace'], true);
            });
        if ($found) {
            return $found;
        }

        try {
            return ChannelMaster::create([
                'channel' => 'FB Marketplace',
                'status' => 'active',
                'type' => 'C2C',
                'listing_mode' => 'CSV',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Missing Listing could not add FB Marketplace: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function overlayListingModes(array $rows): array
    {
        if (! Schema::hasColumn('channel_master', 'listing_mode') || $rows === []) {
            return $rows;
        }

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return $rows;
        }

        $masters = ChannelMaster::query()
            ->whereIn('id', $ids)
            ->get(['id', 'listing_mode', 'type'])
            ->keyBy('id');

        foreach ($rows as $i => $row) {
            $id = (int) ($row['id'] ?? 0);
            $master = $id > 0 ? $masters->get($id) : null;
            $rows[$i]['listing_mode'] = $master
                ? self::normalizeListingMode($master->listing_mode ?? null)
                : null;
            $rows[$i]['type'] = $master ? $this->listingTypeFor($master) : '';
        }

        return $rows;
    }

    private function patchCachedListingMode(int $id, ?string $mode): void
    {
        $cached = Cache::get(self::PAGE_CACHE_KEY);
        if (! is_array($cached) || empty($cached['data']) || ! is_array($cached['data'])) {
            return;
        }

        foreach ($cached['data'] as $i => $row) {
            if ((int) ($row['id'] ?? 0) !== $id) {
                continue;
            }
            $cached['data'][$i]['listing_mode'] = $mode;
        }

        Cache::put(self::PAGE_CACHE_KEY, $cached, now()->addDays(self::PAGE_CACHE_TTL_DAYS));
    }

    private function patchCachedChannelField(int $id, string $field, mixed $value): void
    {
        $cached = Cache::get(self::PAGE_CACHE_KEY);
        if (! is_array($cached) || empty($cached['data']) || ! is_array($cached['data'])) {
            return;
        }

        foreach ($cached['data'] as $i => $row) {
            if ((int) ($row['id'] ?? 0) !== $id) {
                continue;
            }
            $cached['data'][$i][$field] = $value;
        }

        Cache::put(self::PAGE_CACHE_KEY, $cached, now()->addDays(self::PAGE_CACHE_TTL_DAYS));
    }

    private function sellerPortalFor(ChannelMaster $master, bool $hasSellerLink): ?string
    {
        if (! $hasSellerLink) {
            return null;
        }

        $stored = trim((string) ($master->seller_link ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        $key = strtolower(trim((string) $master->channel));
        $default = self::DEFAULT_SELLER_PORTALS[$key] ?? null;
        if ($default === null) {
            return null;
        }

        $master->seller_link = $default;
        $master->save();

        return $default;
    }

    public function updateSellerPortal(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:channel_master,id',
            'seller_portal' => 'nullable|string|max:1000|url',
        ]);

        if (! Schema::hasColumn('channel_master', 'seller_link')) {
            return response()->json([
                'success' => false,
                'message' => 'channel_master.seller_link column is not available.',
            ], 500);
        }

        try {
            $channel = ChannelMaster::find($request->integer('id'));
            if (! $channel) {
                return response()->json(['success' => false, 'message' => 'Channel not found.'], 404);
            }

            $value = trim((string) $request->input('seller_portal', ''));
            $channel->seller_link = $value === '' ? null : $value;
            $channel->save();

            return response()->json([
                'success' => true,
                'message' => 'Seller Portal updated.',
                'data' => [
                    'id' => $channel->id,
                    'seller_portal' => $channel->seller_link,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Missing Listing updateSellerPortal failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function importSheetCsv(Request $request, string $channel)
    {
        $slug = SheetListingCatalog::canonical($channel);
        if (! SheetListingCatalog::has($slug)) {
            return response()->json([
                'success' => false,
                'message' => 'This channel does not accept a sheet catalog.',
            ], 404);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        try {
            $result = app(SheetListingCatalogService::class)->importUploadedCatalog($slug, $request->file('file'));
            $counts = ListingChannelCounts::forChannel($slug, false);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'processed' => $result['processed'],
                'listed' => $result['listed'],
                'skipped' => $result['skipped'],
                'unmatched' => $result['unmatched'],
                'counts' => $counts,
            ]);
        } catch (\Throwable $e) {
            Log::error('Missing Listing CSV import failed for '.$slug.': '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function updateListingMode(Request $request)
    {
        if ($request->exists('type') && ! $request->exists('listing_mode')) {
            return $this->updateChannelType($request);
        }

        $request->validate([
            'id' => 'required|integer|exists:channel_master,id',
            'listing_mode' => 'nullable|string|in:Auto,CSV,Manual,Semi',
        ]);

        if (! Schema::hasColumn('channel_master', 'listing_mode')) {
            return response()->json([
                'success' => false,
                'message' => 'channel_master.listing_mode column is not available.',
            ], 500);
        }

        try {
            $channel = ChannelMaster::find($request->integer('id'));
            if (! $channel) {
                return response()->json(['success' => false, 'message' => 'Channel not found.'], 404);
            }

            $value = $this->normalizeListingMode($request->input('listing_mode'));
            $channel->listing_mode = $value;
            $channel->save();
            $this->patchCachedListingMode((int) $channel->id, $value);

            return response()->json([
                'success' => true,
                'message' => 'Mode updated.',
                'data' => [
                    'id' => $channel->id,
                    'listing_mode' => $channel->listing_mode,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Missing Listing updateListingMode failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateChannelType(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:channel_master,id',
            'type' => 'required|string|in:B2B,B2C,C2C',
        ]);

        if (! Schema::hasColumn('channel_master', 'type')) {
            return response()->json([
                'success' => false,
                'message' => 'channel_master.type column is not available.',
            ], 500);
        }

        try {
            $channel = ChannelMaster::find($request->integer('id'));
            if (! $channel) {
                return response()->json(['success' => false, 'message' => 'Channel not found.'], 404);
            }

            $channel->type = strtoupper(trim((string) $request->input('type')));
            $channel->save();
            $this->patchCachedChannelField((int) $channel->id, 'type', (string) $channel->type);

            return response()->json([
                'success' => true,
                'message' => 'Type updated.',
                'data' => [
                    'id' => $channel->id,
                    'type' => $channel->type,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Missing Listing updateChannelType failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function submitDar(Request $request)
    {
        $request->validate([
            'report' => 'required|string|max:5000',
        ]);

        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'You must be logged in to submit a DAR.',
            ], 401);
        }

        try {
            $dar = MissingListingDar::create([
                'user_id' => $user->id,
                'report' => trim((string) $request->input('report')),
                'submitted_at' => now(self::TZ),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'DAR submitted successfully.',
                'data' => [
                    'id' => $dar->id,
                    'user_name' => $user->name,
                    'report' => $dar->report,
                    'submitted_at' => $dar->submitted_at?->timezone(self::TZ)->toIso8601String(),
                    'submitted_at_california' => $dar->submitted_at
                        ? $dar->submitted_at->timezone(self::TZ)->format('M j, Y g:i A T')
                        : null,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Missing Listing submitDar failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function darHistory(Request $request)
    {
        try {
            $rows = MissingListingDar::with('user:id,name')
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->get(['id', 'user_id', 'report', 'submitted_at']);

            $data = $rows->map(function ($r) {
                $ca = $r->submitted_at?->timezone(self::TZ);

                return [
                    'id' => $r->id,
                    'user_name' => $r->user->name ?? 'Unknown',
                    'report' => $r->report,
                    'submitted_at' => $ca?->toIso8601String(),
                    // Pre-formatted California / Pacific display
                    'submitted_at_california' => $ca ? $ca->format('M j, Y g:i A T') : '-',
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Missing Listing darHistory failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function persistListingMissingHistory(Collection $rows): void
    {
        if (! Schema::hasTable('channel_master_daily_data') || $rows->isEmpty()) {
            return;
        }

        try {
            foreach ($rows as $row) {
                $channelKey = ListingChannelCounts::normalize((string) ($row['channel'] ?? ''));
                if ($channelKey === '') {
                    continue;
                }

                // Do not snapshot Sheet / disconnected channels (no live listing counts)
                if (! in_array($row['data_source'] ?? '', ['API', 'CSV'], true)
                    || ! ListingChannelCounts::showsComputedCounts((string) ($row['channel'] ?? ''))) {
                    continue;
                }

                ChannelMasterSummary::mergeTodaySummary($channelKey, [
                    'listing_miss_count' => (int) ($row['missing_listing'] ?? 0),
                    'listing_req' => (int) ($row['req'] ?? 0),
                    'listing_nrl' => (int) ($row['nrl'] ?? 0),
                    'listing_listed' => (int) ($row['listed'] ?? 0),
                    'listing_captured_at' => now(self::TZ)->toDateTimeString(),
                ], 'Listing Missing L snapshot (California)', self::TZ);
            }
        } catch (\Throwable $e) {
            Log::warning('Missing Listing persistListingMissingHistory failed: ' . $e->getMessage());
        }
    }

    private function liveMissingL(bool $isAll, string $channelKey): float
    {
        if ($isAll) {
            $total = 0;
            $seen = [];
            $masters = Schema::hasTable('channel_master')
                ? ChannelMaster::whereNotNull('channel')->get(['channel', 'status'])
                : collect();

            foreach ($masters as $master) {
                $name = (string) $master->channel;
                if (! ListingChannelCounts::shouldShowOnMissingListing($name, $master->status ?? '')) {
                    continue;
                }
                $key = ListingChannelCounts::normalize((string) $name);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                if (! ListingChannelCounts::showsComputedCounts((string) $name)) {
                    continue;
                }
                $c = ListingChannelCounts::forChannel((string) $name, false);
                $total += (int) ($c['Pending'] ?? 0);
            }

            return (float) $total;
        }

        if (! ListingChannelCounts::showsComputedCounts($channelKey)) {
            return 0.0;
        }

        $c = ListingChannelCounts::forChannel($channelKey, false);

        return (float) ($c['Pending'] ?? 0);
    }

    /**
     * @return list<array{date: string, value: float}>
     */
    private function chartWithLiveOnly(bool $isAll, string $channelKey, mixed $badgeValue): array
    {
        $live = ($badgeValue !== null && $badgeValue !== '' && is_numeric($badgeValue))
            ? (float) $badgeValue
            : $this->liveMissingL($isAll, $channelKey);

        return [[
            'date' => now(self::TZ)->format('M d'),
            'value' => round($live, 2),
        ]];
    }

    /**
     * Collapse spellings of the same marketplace (eBay 2 / ebaytwo) so the overall
     * history does not add that marketplace twice.
     */
    private function chartCanonicalKey(string $channel): string
    {
        $flat = strtolower((string) preg_replace('/[^a-z0-9]/', '', $channel));
        $groups = [
            'ebay' => ['ebay', 'ebay1', 'ebayone'],
            'ebaytwo' => ['ebay2', 'ebaytwo'],
            'ebaythree' => ['ebay3', 'ebaythree'],
            'tiktokshop' => ['tiktok', 'tiktokshop'],
            'tiktokshop2' => ['tiktok2', 'tiktokshop2'],
            'temu2' => ['temu2', 'temutwo'],
            'temu3' => ['temu3', 'temuthree'],
            'bestbuyusa' => ['bestbuy', 'bestbuyusa'],
            'fbmarketplace' => ['fbmarketplace', 'facebookmarketplace'],
            'shopifyb2c' => ['shopify', 'shopifyb2c'],
            'business5coreb2b' => ['business5coreb2b', 'b5cb2b'],
        ];
        foreach ($groups as $canonical => $aliases) {
            if (in_array($flat, $aliases, true)) {
                return $canonical;
            }
        }

        return $flat !== '' ? $flat : ListingChannelCounts::normalize($channel);
    }

    /**
     * @return list<string>
     */
    private function channelAliases(string $normalizedKey): array
    {
        $aliases = [$normalizedKey];

        $map = [
            'ebaytwo' => ['ebay2', 'ebaytwo'],
            'ebay2' => ['ebay2', 'ebaytwo'],
            'ebaythree' => ['ebay3', 'ebaythree'],
            'ebay3' => ['ebay3', 'ebaythree'],
            'ebay' => ['ebay', 'ebay1', 'ebayone'],
            'tiktokshop' => ['tiktokshop', 'tiktok'],
            'tiktok' => ['tiktokshop', 'tiktok'],
            'tiktokshop2' => ['tiktokshop2', 'tiktok2'],
            'tiktok2' => ['tiktokshop2', 'tiktok2'],
            'temu2' => ['temu2', 'temutwo'],
            'temutwo' => ['temu2', 'temutwo'],
            'temu3' => ['temu3', 'temuthree'],
            'temuthree' => ['temu3', 'temuthree'],
            'bestbuyusa' => ['bestbuyusa', 'bestbuy'],
            'bestbuy' => ['bestbuyusa', 'bestbuy'],
            'fbmarketplace' => ['fbmarketplace', 'facebookmarketplace', 'fb marketplace'],
            'facebookmarketplace' => ['fbmarketplace', 'facebookmarketplace', 'fb marketplace'],
            'business5coreb2b' => ['business5coreb2b', 'business5core(b2b)', 'b5cb2b', 'shopifyb2b'],
            'business5core(b2b)' => ['business5coreb2b', 'business5core(b2b)', 'b5cb2b', 'shopifyb2b'],
            'b5cb2b' => ['business5coreb2b', 'business5core(b2b)', 'b5cb2b', 'shopifyb2b'],
            'shopifyb2c' => ['shopifyb2c', 'shopify'],
            'newegg' => ['newegg', 'neweggb2c', 'neweggb2b'],
            'neweggb2c' => ['newegg', 'neweggb2c'],
            'neweggb2b' => ['newegg', 'neweggb2b'],
            'pls' => ['pls', 'shopifypls'],
            'topdawg' => ['topdawg'],
        ];

        foreach ($map[$normalizedKey] ?? [] as $a) {
            $aliases[] = $a;
        }

        $flat = [];
        foreach ($aliases as $alias) {
            $flat[] = strtolower(trim($alias));
            $stripped = strtolower((string) preg_replace('/[^a-z0-9]/', '', $alias));
            if ($stripped !== '') {
                $flat[] = $stripped;
            }
            $flat[] = ListingChannelCounts::normalize($alias);
        }

        return array_values(array_unique(array_merge($aliases, $flat)));
    }
}
