<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ReverbBumpRaw;
use App\Models\ReverbProduct;
use App\Services\ReverbApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * /reverb-bump — Bump push & pull, with the raw API response per listing in a Tabulator.
 *   Pull: GET  /api/listings/{id}/bump           (stored untouched in reverb_bump_raw)
 *   Push: PUT  /api/bump/v2/bids (via ReverbApiService::applyListingBumpBid), then re-pull.
 */
class ReverbBumpController extends Controller
{
    private const PULL_CHUNK_MAX = 25;

    public function index()
    {
        return view('market-places.reverb_bump_raw_tabulator_view');
    }

    /**
     * One row per Reverb listing: product info + the latest stored raw API response.
     */
    public function data(): JsonResponse
    {
        $hasRawTable = Schema::hasTable('reverb_bump_raw');

        $products = ReverbProduct::query()
            ->whereNotNull('reverb_listing_id')
            ->where('reverb_listing_id', '!=', '')
            // Ended listings can't carry a Bump bid — hide them (also keeps them out of Pull All).
            ->where(function ($q) {
                $q->whereNull('listing_state')
                    ->orWhereRaw('LOWER(TRIM(listing_state)) != ?', ['ended']);
            })
            ->orderBy('sku')
            ->get(['id', 'sku', 'reverb_listing_id', 'product_title', 'listing_state', 'bump_bid']);

        $rawByListing = [];
        if ($hasRawTable) {
            foreach (ReverbBumpRaw::query()->get() as $raw) {
                $rawByListing[(string) $raw->reverb_listing_id] = $raw;
            }
        }

        $rows = [];
        foreach ($products as $p) {
            $listingId = trim((string) $p->reverb_listing_id);
            $raw = $rawByListing[$listingId] ?? null;
            $decoded = null;
            if ($raw && $raw->raw_response !== null && $raw->raw_response !== '') {
                $decoded = json_decode($raw->raw_response, true);
            }

            $rows[] = [
                'id' => $p->id,
                'sku' => $p->sku,
                'listing_id' => $listingId,
                'title' => $p->product_title,
                'listing_state' => $p->listing_state,
                'bump_bid' => $p->bump_bid,
                'http_status' => $raw?->http_status,
                'error' => $raw?->error,
                'fetched_at' => $raw?->fetched_at?->toDateTimeString(),
                'raw' => is_array($decoded) ? $decoded : null,
                'raw_text' => $raw && $decoded === null ? $raw->raw_response : null,
            ];
        }

        return response()->json([
            'rows' => $rows,
            'table_ready' => $hasRawTable,
        ]);
    }

    /**
     * Pull raw bump data for a chunk of listings (client loops over chunks).
     * Body: { listing_ids: [..] }
     */
    public function pull(Request $request): JsonResponse
    {
        $request->validate([
            'listing_ids' => 'required|array|min:1|max:'.self::PULL_CHUNK_MAX,
            'listing_ids.*' => 'required|string|max:64',
        ]);

        if (! Schema::hasTable('reverb_bump_raw')) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate (reverb_bump_raw table missing).'], 500);
        }

        $api = app(ReverbApiService::class);
        $results = [];
        $ok = 0;
        $fail = 0;

        foreach (array_unique($request->input('listing_ids')) as $listingId) {
            $res = $this->pullOne($api, trim((string) $listingId));
            $res['success'] ? $ok++ : $fail++;
            $results[] = $res;
            usleep(150000);
        }

        return response()->json([
            'success' => $fail === 0,
            'ok' => $ok,
            'failed' => $fail,
            'results' => $results,
        ]);
    }

    /**
     * Push a Bump % (0 removes) for one SKU, then re-pull its raw data.
     * Body: { sku, bump }
     */
    public function push(Request $request): JsonResponse
    {
        $request->validate([
            'sku' => 'required|string|max:191',
            'bump' => 'required|numeric|min:0|max:30',
        ]);

        $api = app(ReverbApiService::class);
        $res = $api->applyListingBumpBid((string) $request->input('sku'), (float) $request->input('bump'));

        $pulled = null;
        if (! empty($res['success']) && ! empty($res['listing_id']) && Schema::hasTable('reverb_bump_raw')) {
            $pulled = $this->pullOne($api, (string) $res['listing_id'], (string) $request->input('sku'));
        }

        return response()->json([
            'success' => ! empty($res['success']),
            'message' => $res['message'] ?? '',
            'display' => $res['display'] ?? null,
            'listing_id' => $res['listing_id'] ?? null,
            'pulled' => $pulled,
        ], ! empty($res['success']) ? 200 : 422);
    }

    /**
     * @return array{listing_id: string, success: bool, status: ?int, message: ?string, fetched_at: ?string, raw: ?array}
     */
    private function pullOne(ReverbApiService $api, string $listingId, ?string $sku = null): array
    {
        $fetch = $api->fetchListingBumpRaw($listingId);
        $now = now();

        $sku ??= ReverbProduct::query()->where('reverb_listing_id', $listingId)->value('sku');

        ReverbBumpRaw::query()->updateOrCreate(
            ['reverb_listing_id' => $listingId],
            [
                'sku' => $sku,
                'http_status' => $fetch['status'],
                'raw_response' => $fetch['json'] !== null
                    ? json_encode($fetch['json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : $fetch['body'],
                'error' => $fetch['success'] ? null : $fetch['message'],
                'fetched_at' => $now,
            ]
        );

        // Keep the live Bump% column in step with what Reverb just returned.
        if ($fetch['success'] && is_array($fetch['json']) && Schema::hasColumn('reverb_products', 'bump_bid')) {
            $ads = ReverbApiService::parseListingBumpAds($fetch['json']);
            if ($ads['bump_bid'] !== null && $ads['bump_bid'] !== '') {
                DB::table('reverb_products')
                    ->where('reverb_listing_id', $listingId)
                    ->update(['bump_bid' => $ads['bump_bid'], 'updated_at' => $now]);
            }
        }

        return [
            'listing_id' => $listingId,
            'success' => $fetch['success'],
            'status' => $fetch['status'],
            'message' => $fetch['message'],
            'fetched_at' => $now->toDateTimeString(),
            'raw' => $fetch['json'],
        ];
    }
}
