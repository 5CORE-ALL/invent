<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Variations Verify Masters — Tabulator of active channels from channel_master
 * (same source as /all-marketplace-master Active Channels Master).
 *
 * Columns:
 *   - Channel Image (channel_master.logo)
 *   - Channels      (channel_master.channel)
 *   - REQ / N-REQ   (channel_master.nr; default REQ)
 *   - Mismatch      (mismatch_count from each channel's Listing Variation Verify page)
 */
class VariationsVerifyMasterController extends Controller
{
    public const TOTAL_MISMATCH_CACHE_KEY = 'variations_verify_masters.total_mismatch';

    public function index()
    {
        return view('market-places.variations_verify_masters');
    }

    /**
     * Sidebar / page badge — sum of all channel LVV mismatch counts.
     * Populated when /variations-verify-masters/data runs (cached).
     */
    public static function totalMismatchCountForSidebar(): int
    {
        try {
            $cached = Cache::get(self::TOTAL_MISMATCH_CACHE_KEY);
            if ($cached !== null) {
                return (int) $cached;
            }
        } catch (\Throwable $e) {
            // File cache dirs may be missing mid-request after optimize:clear.
        }

        return 0;
    }

    public function data(Request $request)
    {
        try {
            if (! Schema::hasTable('channel_master')) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'count' => 0,
                    'req_count' => 0,
                    'nreq_count' => 0,
                    'total_mismatch' => 0,
                ]);
            }

            $hasLogo = Schema::hasColumn('channel_master', 'logo');
            $hasSellerLink = Schema::hasColumn('channel_master', 'seller_link');
            $hasAlias = Schema::hasColumn('channel_master', 'alias');
            $hasNr = Schema::hasColumn('channel_master', 'nr');

            $columns = ['id', 'channel', 'status'];
            if ($hasLogo) {
                $columns[] = 'logo';
            }
            if ($hasSellerLink) {
                $columns[] = 'seller_link';
            }
            if ($hasAlias) {
                $columns[] = 'alias';
            }
            if ($hasNr) {
                $columns[] = 'nr';
            }

            $rows = ChannelMaster::query()
                ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
                ->whereNotNull('channel')
                ->where('channel', '!=', '')
                ->orderBy('channel')
                ->get($columns);

            $verifyByKey = $this->listingVariationVerifyMap();
            $mismatchByRoute = [];
            $totalMismatch = 0;
            $reqCount = 0;
            $nreqCount = 0;

            $data = $rows->map(function ($row) use (
                $hasLogo,
                $hasSellerLink,
                $hasAlias,
                $hasNr,
                $verifyByKey,
                &$mismatchByRoute,
                &$totalMismatch,
                &$reqCount,
                &$nreqCount
            ) {
                $channel = trim((string) $row->channel);
                $key = $this->normalizeChannelKey($channel);
                $mapped = $verifyByKey[$key] ?? null;
                $routeName = $mapped['route'] ?? null;
                $controllerClass = $mapped['controller'] ?? null;

                $verifyUrl = ($routeName && Route::has($routeName))
                    ? route($routeName)
                    : null;

                $mismatch = null;
                if ($routeName && $controllerClass) {
                    if (! array_key_exists($routeName, $mismatchByRoute)) {
                        $mismatchByRoute[$routeName] = $this->fetchMismatchCount($routeName, $controllerClass);
                    }
                    $mismatch = $mismatchByRoute[$routeName];
                    if (is_int($mismatch)) {
                        $totalMismatch += $mismatch;
                    }
                }

                $nrReq = $hasNr
                    ? $this->nrFlagToLabel($row->nr ?? null)
                    : 'REQ';
                if ($nrReq === 'N-REQ') {
                    $nreqCount++;
                } else {
                    $reqCount++;
                }

                return [
                    'id' => $row->id,
                    'image' => $hasLogo ? ($row->logo ?? null) : null,
                    'channel' => $channel,
                    'alias' => $hasAlias ? ($row->alias ?? null) : null,
                    'seller_link' => $hasSellerLink ? ($row->seller_link ?? null) : null,
                    'verify_url' => $verifyUrl,
                    'nr_req' => $nrReq,
                    'mismatch_count' => $mismatch,
                ];
            })->values();

            try {
                Cache::put(self::TOTAL_MISMATCH_CACHE_KEY, $totalMismatch, now()->addDay());
            } catch (\Throwable $e) {
                // ignore cache write failures
            }

            // Daily California snapshot for Listing Catalogue rolling history
            ListingCatalogueController::persistTodaySnapshot((int) $totalMismatch);

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
                'req_count' => $reqCount,
                'nreq_count' => $nreqCount,
                'total_mismatch' => $totalMismatch,
            ]);
        } catch (\Throwable $e) {
            Log::error('Variations Verify Masters data failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle REQ / N-REQ for a channel (stored on channel_master.nr).
     * nr = 0/null → REQ (green), nr = 1 → N-REQ (red).
     */
    public function updateNrReq(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:channel_master,id',
            'nr_req' => 'required|string|in:REQ,N-REQ',
        ]);

        if (! Schema::hasColumn('channel_master', 'nr')) {
            return response()->json([
                'success' => false,
                'message' => 'channel_master.nr column is not available.',
            ], 500);
        }

        try {
            $channel = ChannelMaster::find($request->integer('id'));
            if (! $channel) {
                return response()->json(['success' => false, 'message' => 'Channel not found.'], 404);
            }

            $nrReq = strtoupper(trim((string) $request->input('nr_req')));
            $channel->nr = $nrReq === 'N-REQ' ? 1 : 0;
            $channel->save();

            return response()->json([
                'success' => true,
                'message' => 'REQ / N-REQ updated.',
                'data' => [
                    'id' => $channel->id,
                    'nr_req' => $nrReq === 'N-REQ' ? 'N-REQ' : 'REQ',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Variations Verify Masters updateNrReq failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * channel_master.nr boolean/flag → REQ / N-REQ label (default REQ).
     */
    private function nrFlagToLabel(mixed $nr): string
    {
        if ($nr === null || $nr === '' || $nr === false || $nr === 0 || $nr === '0') {
            return 'REQ';
        }

        return 'N-REQ';
    }

    /**
     * Pull mismatch_count from each channel LVV controller meta (cached briefly).
     */
    private function fetchMismatchCount(string $routeName, string $controllerClass): ?int
    {
        $cacheKey = 'variations_verify_masters.mismatch.'.$routeName;

        try {
            return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($controllerClass, $routeName) {
                if (! class_exists($controllerClass) || ! method_exists($controllerClass, 'data')) {
                    return null;
                }

                /** @var Controller $controller */
                $controller = app($controllerClass);
                $response = $controller->data(Request::create('/', 'GET'));
                $payload = $response instanceof \Illuminate\Http\JsonResponse
                    ? $response->getData(true)
                    : (is_array($response) ? $response : []);

                $count = $payload['meta']['mismatch_count'] ?? null;
                if ($count === null && isset($payload['data']) && is_array($payload['data'])) {
                    $count = count(array_filter(
                        $payload['data'],
                        static fn ($r) => ($r['match_status'] ?? null) === false
                    ));
                }

                return is_numeric($count) ? (int) $count : null;
            });
        } catch (\Throwable $e) {
            Log::warning('Variations Verify Masters: mismatch fetch failed', [
                'route' => $routeName,
                'controller' => $controllerClass,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Map normalized channel_master.channel names → LVV index route + controller.
     *
     * @return array<string, array{route: string, controller: class-string}>
     */
    private function listingVariationVerifyMap(): array
    {
        $entries = [
            'amazon' => ['route' => 'amz.listing.variation.verify', 'controller' => AmzListingVariationVerifyController::class],
            'ebay' => ['route' => 'ebay.listing.variation.verify', 'controller' => EbayListingVariationVerifyController::class],
            'ebaytwo' => ['route' => 'ebay2.listing.variation.verify', 'controller' => Ebay2ListingVariationVerifyController::class],
            'ebay2' => ['route' => 'ebay2.listing.variation.verify', 'controller' => Ebay2ListingVariationVerifyController::class],
            'ebaythree' => ['route' => 'ebay3.listing.variation.verify', 'controller' => Ebay3ListingVariationVerifyController::class],
            'ebay3' => ['route' => 'ebay3.listing.variation.verify', 'controller' => Ebay3ListingVariationVerifyController::class],
            'shopify' => ['route' => 'shopify.b2c.listing.variation.verify', 'controller' => ShopifyB2cListingVariationVerifyController::class],
            'shopifyb2c' => ['route' => 'shopify.b2c.listing.variation.verify', 'controller' => ShopifyB2cListingVariationVerifyController::class],
            'shopifyb2b' => ['route' => 'shopify.b2b.listing.variation.verify', 'controller' => ShopifyB2bListingVariationVerifyController::class],
            'macys' => ['route' => 'macys.listing.variation.verify', 'controller' => MacysListingVariationVerifyController::class],
            'bestbuyusa' => ['route' => 'bestbuy.listing.variation.verify', 'controller' => BestbuyListingVariationVerifyController::class],
            'bestbuy' => ['route' => 'bestbuy.listing.variation.verify', 'controller' => BestbuyListingVariationVerifyController::class],
            'temu2' => ['route' => 'temu2.listing.variation.verify', 'controller' => Temu2ListingVariationVerifyController::class],
            'temutwo' => ['route' => 'temu2.listing.variation.verify', 'controller' => Temu2ListingVariationVerifyController::class],
            'tiktok' => ['route' => 'tiktok.listing.variation.verify', 'controller' => TikTokListingVariationVerifyController::class],
            'tiktokshop' => ['route' => 'tiktok.listing.variation.verify', 'controller' => TikTokListingVariationVerifyController::class],
            'tiktok1' => ['route' => 'tiktok.listing.variation.verify', 'controller' => TikTokListingVariationVerifyController::class],
            'tiktok2' => ['route' => 'tiktok2.listing.variation.verify', 'controller' => TikTok2ListingVariationVerifyController::class],
            'tiktoktwo' => ['route' => 'tiktok2.listing.variation.verify', 'controller' => TikTok2ListingVariationVerifyController::class],
            'aliexpress' => ['route' => 'aliexpress.listing.variation.verify', 'controller' => AliexpressListingVariationVerifyController::class],
            'faire' => ['route' => 'faire.listing.variation.verify', 'controller' => FaireListingVariationVerifyController::class],
            'shein' => ['route' => 'shein.listing.variation.verify', 'controller' => SheinListingVariationVerifyController::class],
            'wayfair' => ['route' => 'wayfair.listing.variation.verify', 'controller' => WayfairListingVariationVerifyController::class],
            'pls' => ['route' => 'pls.listing.variation.verify', 'controller' => PlsListingVariationVerifyController::class],
            'purchasingpower' => ['route' => 'purchasing.power.listing.variation.verify', 'controller' => PurchasingPowerListingVariationVerifyController::class],
            'newegg' => ['route' => 'newegg.listing.variation.verify', 'controller' => NeweggListingVariationVerifyController::class],
        ];

        return $entries;
    }

    private function normalizeChannelKey(string $channel): string
    {
        return strtolower(str_replace([' ', '-', '&', '/', "'", '"'], '', trim($channel)));
    }
}
