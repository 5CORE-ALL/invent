<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\ChannelMasterCalculatedData;
use App\Models\MissingListingDar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Missing Listing page — Tabulator view powered by channel_master.
 *
 * Renders one row per active channel with three columns:
 *   - Image           (channel_master.logo)
 *   - Channel         (channel_master.channel)
 *   - Missing Listing (channel_master_calculated_data.miss for the channel —
 *                      same source the /all-marketplace-master page uses
 *                      for its "Missing L" column)
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
     *
     * Uses channel_master_calculated_data as the row source — the same table
     * /all-marketplace-master reads in getViewChannelDataFast() — so badge
     * totals and per-row Miss values stay in sync. Logo / seller_link are
     * joined from channel_master when a name match is found.
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
            if ($hasLogo)       { $masterColumns[] = 'logo'; }
            if ($hasSellerLink) { $masterColumns[] = 'seller_link'; }

            $masterRows = ChannelMaster::whereRaw('LOWER(TRIM(status)) = ?', ['active'])
                ->whereNotNull('channel')
                ->where('channel', '!=', '')
                ->get($masterColumns);

            // Index channel_master rows by exact name and by the same normalized
            // key the chart APIs use, so we can attach logo / seller_link even
            // when calculated_data stores a slightly different label.
            $masterByExact = [];
            $masterByNorm  = [];
            foreach ($masterRows as $row) {
                $masterByExact[$row->channel] = $row;
                $masterByNorm[$this->normalizeChannelKey($row->channel)] = $row;
            }

            $calculatedRows = ChannelMasterCalculatedData::orderBy('channel')->get(['channel', 'miss']);

            $data = $calculatedRows->map(function ($row) use ($hasLogo, $hasSellerLink, $masterByExact, $masterByNorm) {
                $channel = $row->channel;
                $master  = $masterByExact[$channel]
                    ?? $masterByNorm[$this->normalizeChannelKey($channel)]
                    ?? null;

                return [
                    'id'              => $master?->id,
                    'image'           => ($hasLogo && $master) ? ($master->logo ?? null) : null,
                    'channel'         => $channel,
                    'missing_listing' => (int) ($row->miss ?? 0),
                    'seller_portal'   => ($hasSellerLink && $master) ? ($master->seller_link ?? null) : null,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Missing Listing getData failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Same channel normalizer used by ChannelMasterController chart lookups.
     */
    private function normalizeChannelKey(string $channel): string
    {
        return strtolower(str_replace([' ', '-', '&', '/'], '', trim($channel)));
    }

    /**
     * Update the Seller Portal URL for a single channel directly into
     * channel_master.seller_link — the exact same column the
     * /all-marketplace-master "Edit Channel" modal writes to. Keeps the
     * two pages in sync so an edit here is visible there immediately.
     */
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

    /**
     * Persist a Daily Activity Report submission from the Missing Listing page.
     * Captures the logged-in user and the server-side submission timestamp so
     * the History panel can show "who, when" without trusting the client clock.
     */
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

    /**
     * Return all Missing Listing DAR submissions, newest first, joined with
     * the submitter's name. Powers both the History modal table and the
     * "History: N" badge count in the page header.
     */
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
