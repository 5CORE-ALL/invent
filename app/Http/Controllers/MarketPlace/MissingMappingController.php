<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MapIssuesController;
use App\Http\Controllers\MarketPlace\SheinController;
use App\Http\Controllers\MarketPlace\TikTokPricingController;
use App\Models\ChannelMaster;
use App\Support\Badges\AllMarketplaceMasterBadgeCalculator;
use App\Support\Marketplace\MappingChannelCounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Missing Mapping master (Channel | Missing Mapping) + per-channel SKU pages.
 * Master counts come from marketplace pricing / tabulator pages (not Active Channel).
 * Channel SKU lists reuse MapIssuesController not-map logic.
 */
class MissingMappingController extends Controller
{
    /**
     * MapIssues row flag / inventory field per normalized channel slug.
     *
     * @var array<string, array{flag: string, inv: string, msku: string, label: string}>
     */
    private const MAP_ISSUE_CHANNELS = [
        'ebay' => ['flag' => 'is_not_map', 'inv' => 'Ebay Inv', 'msku' => 'ebay_sku', 'label' => 'eBay'],
        'ebay1' => ['flag' => 'is_not_map', 'inv' => 'Ebay Inv', 'msku' => 'ebay_sku', 'label' => 'eBay'],
        'ebayone' => ['flag' => 'is_not_map', 'inv' => 'Ebay Inv', 'msku' => 'ebay_sku', 'label' => 'eBay'],
        'ebay2' => ['flag' => 'ebay2_not_map', 'inv' => 'Ebay2 Inv', 'msku' => 'ebay2_sku', 'label' => 'eBay 2'],
        'ebaytwo' => ['flag' => 'ebay2_not_map', 'inv' => 'Ebay2 Inv', 'msku' => 'ebay2_sku', 'label' => 'eBay 2'],
        'ebay3' => ['flag' => 'ebay3_not_map', 'inv' => 'Ebay3 Inv', 'msku' => 'ebay3_sku', 'label' => 'eBay 3'],
        'ebaythree' => ['flag' => 'ebay3_not_map', 'inv' => 'Ebay3 Inv', 'msku' => 'ebay3_sku', 'label' => 'eBay 3'],
        'amazon' => ['flag' => 'amazon_not_map', 'inv' => 'Amazon Inv', 'msku' => 'amazon_sku', 'label' => 'Amazon'],
        'reverb' => ['flag' => 'reverb_not_map', 'inv' => 'Reverb Inv', 'msku' => 'reverb_sku', 'label' => 'Reverb'],
        'macys' => ['flag' => 'macys_not_map', 'inv' => 'Macys Inv', 'msku' => 'macys_sku', 'label' => 'Macys'],
        'bestbuy' => ['flag' => 'bestbuy_not_map', 'inv' => 'Bestbuy Inv', 'msku' => 'bestbuy_sku', 'label' => 'BestBuy USA'],
        'bestbuyusa' => ['flag' => 'bestbuy_not_map', 'inv' => 'Bestbuy Inv', 'msku' => 'bestbuy_sku', 'label' => 'BestBuy USA'],
        'shein' => ['flag' => 'shein_not_map', 'inv' => 'Shein Inv', 'msku' => 'shein_sku', 'label' => 'Shein'],
        'newegg' => ['flag' => 'newegg_not_map', 'inv' => 'Newegg Inv', 'msku' => 'newegg_sku', 'label' => 'Newegg'],
        'neweggb2c' => ['flag' => 'newegg_not_map', 'inv' => 'Newegg Inv', 'msku' => 'newegg_sku', 'label' => 'Newegg'],
        'aliexpress' => ['flag' => 'aliexpress_not_map', 'inv' => 'Ali Inv', 'msku' => 'aliexpress_sku', 'label' => 'Aliexpress'],
    ];

    public function index()
    {
        return view('market-places.Missing_mapping');
    }

    /**
     * Channel master rows: Channel + Missing Mapping (live N Map from pricing pages).
     */
    public function masterData(Request $request)
    {
        try {
            $data = collect(MappingChannelCounts::masterRows(false))->values();
            $totalNmap = (int) $data->sum('missing_mapping');

            MappingChannelCounts::storeTotalNmap($totalNmap);
            Cache::put(AllMarketplaceMasterBadgeCalculator::NMAP_CACHE_KEY, $totalNmap, now()->addDay());

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
                'total_nmap' => $totalNmap,
            ]);
        } catch (\Throwable $e) {
            Log::error('Missing Mapping masterData failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Missing Mapping {Channel} — SKU list page.
     */
    public function channel(string $channel)
    {
        $resolved = $this->resolveChannel($channel);
        if ($resolved === null) {
            abort(404, 'Channel not found');
        }

        $slug = $resolved['slug'];
        $hasSkuDetail = isset(self::MAP_ISSUE_CHANNELS[$slug])
            || in_array($slug, ['tiktok', 'tiktokshop', 'tiktok2', 'tiktokshop2', 'temu', 'temu2'], true);

        $channelInvLabel = match (true) {
            in_array($slug, ['tiktok', 'tiktokshop'], true) => 'TikTok 1 inv',
            in_array($slug, ['tiktok2', 'tiktokshop2'], true) => 'TikTok 2 inv',
            $slug === 'temu' => 'Temu Inv',
            $slug === 'temu2' => 'Temu 2 Inv',
            $slug === 'shein' => 'Shein Inv',
            default => 'Channel Inv',
        };

        return view('market-places.Missing_mapping_channel', [
            'channelSlug' => $slug,
            'channelName' => $resolved['name'],
            'hasSkuDetail' => $hasSkuDetail,
            'mapIssueKey' => self::MAP_ISSUE_CHANNELS[$slug]['flag'] ?? null,
            'channelInvLabel' => $channelInvLabel,
        ]);
    }

    /**
     * SKUs with Missing Mapping (N Map) for one channel.
     */
    public function channelData(Request $request, string $channel)
    {
        try {
            $resolved = $this->resolveChannel($channel);
            if ($resolved === null) {
                return response()->json(['success' => false, 'message' => 'Channel not found'], 404);
            }

            $slug = $resolved['slug'];

            // TikTok 1 / 2 — SKU list from pricing tabular (same N Map rules as /tiktok-pricing)
            if (in_array($slug, ['tiktok', 'tiktokshop', 'tiktok2', 'tiktokshop2'], true)) {
                $variant = in_array($slug, ['tiktok2', 'tiktokshop2'], true) ? 'v2' : 'v1';
                $raw = app(TikTokPricingController::class)->getViewTikTokTabularData(
                    Request::create($variant === 'v2' ? '/tiktok-2-data-json' : '/tiktok-data-json', 'GET'),
                    $variant
                );
                $payload = $raw instanceof \Illuminate\Http\JsonResponse
                    ? json_decode($raw->getContent(), true)
                    : [];
                $rows = is_array($payload['data'] ?? null) ? $payload['data'] : (is_array($payload) ? $payload : []);
                $data = collect(TikTokPricingController::nmapSkuRowsFromTabular($rows))
                    ->map(fn (array $row) => $row + ['channel' => $resolved['name']])
                    ->values();

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'count' => $data->count(),
                    'channel' => $resolved['name'],
                ]);
            }

            // Shein — SKU list from /shein-pricing table (same N Map rules as shein pricing badges)
            if ($slug === 'shein') {
                $raw = app(SheinController::class)->getSheinPricingData(
                    Request::create('/shein/pricing-data', 'GET')
                );
                $payload = $raw instanceof \Illuminate\Http\JsonResponse
                    ? json_decode($raw->getContent(), true)
                    : [];
                $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $data = collect(SheinController::nmapSkuRowsFromPricing($rows))
                    ->map(fn (array $row) => $row + ['channel' => $resolved['name']])
                    ->values();

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'count' => $data->count(),
                    'channel' => $resolved['name'],
                ]);
            }

            // Temu 1 / 2 — SKU list from temu-decrease (API metrics for Temu 1; same N Map rules)
            if (in_array($slug, ['temu', 'temu2'], true)) {
                $temu = app(TemuController::class);
                $raw = $slug === 'temu2'
                    ? $temu->getTemu2DecreaseData(Request::create('/temu2-decrease-data', 'GET'))
                    : $temu->getTemuDecreaseData(Request::create('/temu-decrease-data', 'GET'));
                $payload = $raw instanceof \Illuminate\Http\JsonResponse
                    ? json_decode($raw->getContent(), true)
                    : [];
                $rows = is_array($payload['data'] ?? null) ? $payload['data'] : (is_array($payload) ? $payload : []);
                $data = collect(TemuController::nmapSkuRowsFromDecrease($rows))
                    ->map(fn (array $row) => $row + ['channel' => $resolved['name']])
                    ->values();

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'count' => $data->count(),
                    'channel' => $resolved['name'],
                ]);
            }

            $meta = self::MAP_ISSUE_CHANNELS[$slug] ?? null;
            if ($meta === null) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'count' => 0,
                    'channel' => $resolved['name'],
                    'message' => 'SKU-level Missing Mapping is not available for this channel yet.',
                ]);
            }

            $raw = app(MapIssuesController::class)->data($request);
            $payload = $raw instanceof \Illuminate\Http\JsonResponse
                ? json_decode($raw->getContent(), true)
                : [];
            $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $flag = $meta['flag'];
            $invField = $meta['inv'];
            $mskuField = $meta['msku'];

            $data = collect($rows)
                ->filter(fn ($row) => is_array($row) && ! empty($row[$flag]))
                ->map(function ($row) use ($invField, $mskuField, $resolved) {
                    $pmSku = (string) ($row['(Child) sku'] ?? '');
                    $inv = (float) ($row['INV'] ?? 0);
                    $channelInv = (float) ($row[$invField] ?? 0);
                    $diff = abs($inv - $channelInv);

                    return [
                        'sku' => $pmSku,
                        'channel_sku' => (string) ($row[$mskuField] ?? ''),
                        'inv' => $inv,
                        'channel_inv' => $channelInv,
                        'diff' => $diff,
                        'channel' => $resolved['name'],
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
                'channel' => $resolved['name'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Missing Mapping channelData failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @return array{slug: string, name: string}|null
     */
    private function resolveChannel(string $raw): ?array
    {
        $slug = MappingChannelCounts::normalize($raw);
        if ($slug === '') {
            return null;
        }

        if (MappingChannelCounts::hasMappingSource($slug) || isset(self::MAP_ISSUE_CHANNELS[$slug])) {
            $name = $this->channelDisplayName($slug)
                ?? (self::MAP_ISSUE_CHANNELS[$slug]['label'] ?? $slug);

            return ['slug' => $slug, 'name' => $name];
        }

        if (! Schema::hasTable('channel_master')) {
            return null;
        }

        foreach (ChannelMaster::query()->whereNotNull('channel')->where('channel', '!=', '')->get(['channel']) as $master) {
            if (MappingChannelCounts::normalize((string) $master->channel) === $slug) {
                return ['slug' => $slug, 'name' => (string) $master->channel];
            }
        }

        return null;
    }

    private function channelDisplayName(string $slug): ?string
    {
        if (! Schema::hasTable('channel_master')) {
            return null;
        }

        $masters = ChannelMaster::query()
            ->whereNotNull('channel')
            ->where('channel', '!=', '')
            ->get(['channel', 'status']);

        $fallback = null;
        foreach ($masters as $master) {
            if (MappingChannelCounts::normalize((string) $master->channel) !== $slug) {
                continue;
            }
            $name = (string) $master->channel;
            if (strtolower(trim((string) ($master->status ?? ''))) === 'active') {
                return $name;
            }
            $fallback = $fallback ?? $name;
        }

        return $fallback;
    }
}
