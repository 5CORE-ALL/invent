<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\ChannelMasterCalculatedData;
use App\Models\MissingListingDar;
use App\Support\Marketplace\CpMasterCounts;
use App\Support\Marketplace\EbayTwoListingCounts;
use App\Support\Marketplace\ListingChannelCounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Missing Listing page — Tabulator view powered by channel_master.
 *
 * Columns:
 *   - Image / Channel
 *   - SKU / 0 Inv         (from CP Master / product master)
 *   - REQ / NRL / Listed  (from each channel's listing page getNrReqCount)
 *   - Missing Listing     (calculated miss, with EbayTwo live overlay)
 *   - Seller Portal
 */
class MissingListingController extends Controller
{
    /**
     * Render the Missing Listing page.
     */
    public function index()
    {
        return view('market-places.Missing_listing');
    }

    /**
     * JSON payload for the Tabulator table on the Missing Listing page.
     */
    public function getData(Request $request)
    {
        try {
            if (!Schema::hasTable('channel_master_calculated_data')) {
                return response()->json([
                    'success' => true,
                    'data'    => [],
                    'count'   => 0,
                ]);
            }

            $hasLogo       = Schema::hasColumn('channel_master', 'logo');
            $hasSellerLink = Schema::hasColumn('channel_master', 'seller_link');

            $masterColumns = ['id', 'channel'];
            if ($hasLogo) {
                $masterColumns[] = 'logo';
            }
            if ($hasSellerLink) {
                $masterColumns[] = 'seller_link';
            }

            $masterRows = ChannelMaster::whereRaw('LOWER(TRIM(status)) = ?', ['active'])
                ->whereNotNull('channel')
                ->where('channel', '!=', '')
                ->get($masterColumns);

            $masterByExact = [];
            $masterByNorm  = [];
            foreach ($masterRows as $row) {
                $masterByExact[$row->channel] = $row;
                $masterByNorm[$this->normalizeChannelKey($row->channel)] = $row;
            }

            $calculatedRows = ChannelMasterCalculatedData::orderBy('channel')->get(['channel', 'miss']);

            $ebayTwoMissingL = EbayTwoListingCounts::missingL();
            $cpMasterCounts = CpMasterCounts::counts();
            $cpSkuCount = (int) ($cpMasterCounts['SKU'] ?? 0);
            $cpZeroInv = (int) ($cpMasterCounts['ZeroInv'] ?? 0);

            $data = $calculatedRows->map(function ($row) use (
                $hasLogo,
                $hasSellerLink,
                $masterByExact,
                $masterByNorm,
                $ebayTwoMissingL,
                $cpSkuCount,
                $cpZeroInv
            ) {
                $channel = $row->channel;
                $master  = $masterByExact[$channel]
                    ?? $masterByNorm[$this->normalizeChannelKey($channel)]
                    ?? null;

                $miss = (int) ($row->miss ?? 0);
                $norm = $this->normalizeChannelKey((string) $channel);
                if (in_array($norm, ['ebaytwo', 'ebay2'], true)
                    || in_array(trim((string) $channel), ['EbayTwo', 'Ebay 2', 'eBay 2', 'eBay Two'], true)
                ) {
                    $miss = $ebayTwoMissingL;
                }

                // REQ / NRL / Listed from listing pages (cached)
                $listingCounts = ListingChannelCounts::forChannel((string) $channel);

                return [
                    'id'              => $master?->id,
                    'image'           => ($hasLogo && $master) ? ($master->logo ?? null) : null,
                    'channel'         => $channel,
                    'listing_url'     => ListingChannelCounts::listingUrl((string) $channel),
                    'sku'             => $cpSkuCount,
                    'zero_inv'        => $cpZeroInv,
                    'req'             => (int) ($listingCounts['REQ'] ?? 0),
                    'nrl'             => (int) ($listingCounts['NRL'] ?? 0),
                    'listed'          => (int) ($listingCounts['Listed'] ?? 0),
                    'missing_listing' => $miss,
                    'seller_portal'   => ($hasSellerLink && $master) ? ($master->seller_link ?? null) : null,
                ];
            })->values();

            $totalMissingL = (int) $data->sum('missing_listing');

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
                'total_missing_l' => $totalMissingL,
            ]);
        } catch (\Throwable $e) {
            Log::error('Missing Listing getData failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function normalizeChannelKey(string $channel): string
    {
        return strtolower(str_replace([' ', '-', '&', '/'], '', trim($channel)));
    }

    public function updateSellerPortal(Request $request)
    {
        $request->validate([
            'id'            => 'required|integer|exists:channel_master,id',
            'seller_portal' => 'nullable|string|max:1000|url',
        ]);

        if (!Schema::hasColumn('channel_master', 'seller_link')) {
            return response()->json([
                'success' => false,
                'message' => 'channel_master.seller_link column is not available.',
            ], 500);
        }

        try {
            $channel = ChannelMaster::find($request->integer('id'));
            if (!$channel) {
                return response()->json(['success' => false, 'message' => 'Channel not found.'], 404);
            }

            $value = trim((string) $request->input('seller_portal', ''));
            $channel->seller_link = $value === '' ? null : $value;
            $channel->save();

            return response()->json([
                'success' => true,
                'message' => 'Seller Portal updated.',
                'data'    => [
                    'id'            => $channel->id,
                    'seller_portal' => $channel->seller_link,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Missing Listing updateSellerPortal failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function submitDar(Request $request)
    {
        $request->validate([
            'report' => 'required|string|max:5000',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'You must be logged in to submit a DAR.',
            ], 401);
        }

        try {
            $dar = MissingListingDar::create([
                'user_id'      => $user->id,
                'report'       => trim((string) $request->input('report')),
                'submitted_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'DAR submitted successfully.',
                'data'    => [
                    'id'           => $dar->id,
                    'user_name'    => $user->name,
                    'report'       => $dar->report,
                    'submitted_at' => $dar->submitted_at?->toIso8601String(),
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

            $data = $rows->map(fn ($r) => [
                'id'           => $r->id,
                'user_name'    => $r->user->name ?? 'Unknown',
                'report'       => $r->report,
                'submitted_at' => $r->submitted_at?->toIso8601String(),
            ])->values();

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Missing Listing darHistory failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
