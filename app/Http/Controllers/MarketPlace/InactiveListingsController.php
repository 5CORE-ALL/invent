<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Support\Marketplace\MappingChannelCounts;
use App\Services\MarketplaceManager\MarketplaceListingQtyMatchService;
use App\Services\ShopifyPlsTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Inactive Listings master + per-channel SKU pages.
 * Same Inactive SKU rows as Marketplace Manager listings.
 */
class InactiveListingsController extends Controller
{
    public function index()
    {
        return view('market-places.Inactive_listings');
    }

    public function masterData(Request $request)
    {
        try {
            @set_time_limit(400);
            $data = collect(MappingChannelCounts::inactiveMasterRows(true))->values();
            $total = (int) $data->sum('inactive_listings');
            MappingChannelCounts::storeInactiveTotal($total);

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
                'total_inactive' => $total,
            ]);
        } catch (\Throwable $e) {
            Log::error('Inactive Listings masterData failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function channel(string $channel)
    {
        $resolved = $this->resolveChannel($channel);
        if ($resolved === null) {
            abort(404, 'Channel not found');
        }

        $slug = $resolved['slug'];
        $hasSkuDetail = MarketplaceListingQtyMatchService::fromMapIssuesSlug($slug) !== null;
        $channelInvLabel = match (true) {
            in_array($slug, ['tiktok', 'tiktokshop'], true) => 'TikTok 1 inv',
            in_array($slug, ['tiktok2', 'tiktokshop2'], true) => 'TikTok 2 inv',
            $slug === 'temu' => 'Temu Inv',
            $slug === 'temu2' => 'Temu 2 Inv',
            $slug === 'shein' => 'Shein Inv',
            $slug === 'pls' => 'PLS Inv',
            default => 'Channel Inv',
        };

        $plsApi = null;
        if ($slug === 'pls') {
            try {
                $plsApi = app(ShopifyPlsTokenService::class)->pingShopCached();
            } catch (\Throwable $e) {
                $plsApi = ['connected' => false, 'message' => 'PLS API check failed'];
            }
        }

        return view('market-places.Inactive_listings_channel', [
            'channelSlug' => $slug,
            'channelName' => $resolved['name'],
            'hasSkuDetail' => $hasSkuDetail,
            'channelInvLabel' => $channelInvLabel,
            'listingsUrl' => MappingChannelCounts::listingsInactiveUrlForSlug($slug),
            'plsApi' => $plsApi,
        ]);
    }

    public function channelData(Request $request, string $channel)
    {
        try {
            $resolved = $this->resolveChannel($channel);
            if ($resolved === null) {
                return response()->json(['success' => false, 'message' => 'Channel not found'], 404);
            }

            $slug = $resolved['slug'];
            $mmChannel = MarketplaceListingQtyMatchService::fromMapIssuesSlug($slug);
            if ($mmChannel === null) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'count' => 0,
                    'channel' => $resolved['name'],
                    'message' => 'SKU-level Inactive Listings is not available for this channel yet.',
                ]);
            }

            $data = collect(app(MarketplaceListingQtyMatchService::class)->inactiveListingRows($mmChannel, true))
                ->map(fn (array $row) => $row + ['channel' => $resolved['name']])
                ->values();

            $payload = [
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
                'channel' => $resolved['name'],
            ];

            if ($slug === 'pls') {
                $plsApi = ['connected' => false, 'message' => 'PLS API check failed'];
                try {
                    $plsApi = app(ShopifyPlsTokenService::class)->pingShopCached();
                } catch (\Throwable $e) {
                    // keep default
                }
                $payload['api_connected'] = (bool) ($plsApi['connected'] ?? false);
                $payload['api_label'] = (string) ($plsApi['message'] ?? '');
            }

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('Inactive Listings channelData failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @return array{slug: string, name: string}|null
     */
    private function resolveChannel(string $raw): ?array
    {
        $slug = MappingChannelCounts::normalize($raw);
        if ($slug === '' || ! MappingChannelCounts::hasMappingSource($slug)) {
            return null;
        }

        $name = $this->channelDisplayName($slug)
            ?? (MarketplaceListingQtyMatchService::fromMapIssuesSlug($slug) ? $slug : null);
        if ($name === null) {
            return null;
        }

        return ['slug' => $slug, 'name' => $name];
    }

    private function channelDisplayName(string $slug): ?string
    {
        $fallback = match ($slug) {
            'ebay', 'ebay1', 'ebayone' => 'eBay',
            'ebay2', 'ebaytwo' => 'eBay 2',
            'ebay3', 'ebaythree' => 'eBay 3',
            'amazon' => 'Amazon',
            'reverb' => 'Reverb',
            'macys', 'macy' => 'Macys',
            'bestbuy', 'bestbuyusa' => 'BestBuy USA',
            'temu' => 'Temu',
            'temu2' => 'Temu 2',
            'shein' => 'Shein',
            'newegg', 'neweggb2c' => 'Newegg',
            'aliexpress' => 'Aliexpress',
            'pls' => 'PLS',
            'wayfair' => 'Wayfair',
            'faire' => 'Faire',
            'topdawg' => 'TopDawg',
            'tiktok', 'tiktokshop' => 'TikTok Shop',
            'tiktok2', 'tiktokshop2' => 'TikTok 2',
            default => null,
        };

        if (! Schema::hasTable('channel_master')) {
            return $fallback;
        }

        $masters = ChannelMaster::query()
            ->whereNotNull('channel')
            ->where('channel', '!=', '')
            ->get(['channel', 'status']);

        $fromMaster = null;
        foreach ($masters as $master) {
            if (MappingChannelCounts::normalize((string) $master->channel) !== $slug) {
                continue;
            }
            $name = (string) $master->channel;
            if (strtolower(trim((string) ($master->status ?? ''))) === 'active') {
                return $name;
            }
            $fromMaster = $fromMaster ?? $name;
        }

        return $fromMaster ?? $fallback;
    }
}
