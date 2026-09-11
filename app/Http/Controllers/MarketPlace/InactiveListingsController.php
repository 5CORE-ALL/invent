<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Support\Marketplace\ListingInactiveParentChildCounts;
use App\Support\Marketplace\MappingChannelCounts;
use App\Services\MarketplaceManager\MarketplaceListingQtyMatchService;
use App\Services\MarketplaceManager\MarketplaceLiveInventoryRules;
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
            $total = (int) $data->sum(fn ($row) => (int) ($row['inactive_child'] ?? $row['inactive_listings'] ?? 0));
            $cpTotal = (int) $data->sum(fn ($row) => (int) ($row['cp_inactive_child'] ?? 0));
            MappingChannelCounts::storeInactiveTotal($total);
            MappingChannelCounts::storeCpInactiveTotal($cpTotal);

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
                'total_inactive' => $total,
                'total_inactive_child' => $total,
                'total_inactive_parent' => (int) $data->sum(fn ($row) => (int) ($row['inactive_parent'] ?? 0)),
                'total_cp_inactive' => $cpTotal,
                'total_cp_inactive_child' => $cpTotal,
                'total_cp_inactive_parent' => (int) $data->sum(fn ($row) => (int) ($row['cp_inactive_parent'] ?? 0)),
            ]);
        } catch (\Throwable $e) {
            Log::error('Inactive Listings masterData failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function channel(Request $request, string $channel)
    {
        $resolved = $this->resolveChannel($channel);
        if ($resolved === null) {
            abort(404, 'Channel not found');
        }

        $slug = $resolved['slug'];
        $source = strtolower(trim((string) $request->query('source', '')));
        $cpOnly = $source === 'cp';
        $hasSkuDetail = true;
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
            'cpOnly' => $cpOnly,
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
            $cpOnly = strtolower(trim((string) $request->query('source', ''))) === 'cp';
            $rows = $cpOnly
                ? ListingInactiveParentChildCounts::cpMasterListingRowsForChannel($slug)
                : ListingInactiveParentChildCounts::listingRowsForChannel($slug);

            $data = collect($rows)
                ->map(function (array $row) use ($resolved) {
                    $sku = (string) ($row['sku'] ?? '');
                    $kind = (string) ($row['kind'] ?? (MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku) ? 'parent' : 'child'));

                    return $row + [
                        'channel' => $resolved['name'],
                        'kind' => $kind,
                        'parent' => (string) ($row['parent'] ?? ''),
                    ];
                })
                ->values();

            $childCount = $data->filter(fn (array $row) => ($row['kind'] ?? '') === 'child')->count();

            $payload = [
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
                'child_count' => $childCount,
                'parent_count' => $data->count() - $childCount,
                'channel' => $resolved['name'],
                'source' => $cpOnly ? 'cp' : 'marketplace',
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
