<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ReverbBumpChangeLog;
use App\Models\ReverbBumpRaw;
use App\Models\ReverbProduct;
use App\Models\ShopifySku;
use App\Services\ReverbApiService;
use App\Services\Support\ReverbBumpRawPuller;
use App\Services\Support\ReverbBumpRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        // Ended listings can't carry a Bump bid — hidden here and skipped by every pull.
        $products = ReverbBumpRawPuller::pullableProductsQuery()
            ->orderBy('sku')
            ->get(['id', 'sku', 'reverb_listing_id', 'product_title', 'listing_state', 'bump_bid', 'r_l30', 'recommended_bid', 'price']);

        // Same sources as /reverb-pricing: Sold = reverb_products.r_l30 (RV L30), INV = Shopify inventory, S Bump% = recommended_bid.
        $shopifyData = ShopifySku::mapByProductSkus($products->pluck('sku')->filter()->unique()->values()->all());

        $rawByListing = [];
        if ($hasRawTable) {
            foreach (ReverbBumpRaw::query()->get() as $raw) {
                $rawByListing[(string) $raw->reverb_listing_id] = $raw;
            }
        }

        $rules = ReverbBumpRuleService::rules();
        $ruleSettings = ReverbBumpRuleService::settings();
        $rows = [];
        foreach ($products as $p) {
            $listingId = trim((string) $p->reverb_listing_id);
            $invNow = (float) ($shopifyData->get($p->sku)?->inv ?? 0);
            $priceNow = is_numeric($p->price) ? (float) $p->price : null;
            $verdict = ReverbBumpRuleService::evaluate(
                ['sold' => (int) ($p->r_l30 ?? 0), 'inv' => $invNow, 'price' => $priceNow],
                $rules,
                $ruleSettings
            );
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
                'sold' => (int) ($p->r_l30 ?? 0),
                'inv' => $invNow,
                's_bump' => $p->recommended_bid,
                'price' => $priceNow,
                'skip_reason' => $verdict['status'] === 'skip' ? $verdict['reason'] : null,
                'http_status' => $raw?->http_status,
                'error' => $raw?->error,
                'fetched_at' => $raw?->fetched_at?->toDateTimeString(),
                'raw' => is_array($decoded) ? self::withoutHiddenFields($decoded) : null,
                'raw_text' => $raw && $decoded === null ? $raw->raw_response : null,
            ];
        }

        return response()->json([
            'rows' => $rows,
            'table_ready' => $hasRawTable,
        ]);
    }

    /**
     * Saved Sold→Bump slab rules (shared with /reverb-pricing) + checklist exception settings.
     */
    public function ruleConfig(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'rules' => ReverbBumpRuleService::rules(),
            'settings' => ReverbBumpRuleService::settings(),
        ]);
    }

    /**
     * Save rules + settings and fill S Bump% (reverb_products.recommended_bid) server-side for the given listings,
     * honouring the exceptions. Every fill / skip is written to the change log.
     * Body: { rules: [{key,bump}], settings: {...}, listing_ids: [..] }
     */
    public function ruleApply(Request $request): JsonResponse
    {
        $request->validate([
            'rules' => 'required|array|min:1',
            'settings' => 'required|array',
            'listing_ids' => 'required|array|min:1',
            'listing_ids.*' => 'required|string|max:64',
        ]);

        if (! Schema::hasTable('reverb_bump_change_log')) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate (reverb_bump_change_log table missing).'], 500);
        }

        $rules = ReverbBumpRuleService::saveRules($request->input('rules'));
        $settings = ReverbBumpRuleService::saveSettings($request->input('settings'));

        $plan = ReverbBumpRuleService::plan(array_values(array_unique($request->input('listing_ids'))), $rules, $settings);
        $res = ReverbBumpRuleService::applyFill($plan, ReverbBumpRuleService::newRunId(), 'manual');

        $rows = [];
        foreach ($plan as $r) {
            $rows[] = [
                'listing_id' => $r['listing_id'],
                's_bump' => $r['eval']['status'] === 'fill' ? ReverbBumpRuleService::formatBump($r['eval']['bump']) : $r['old_s_bump'],
                'skip_reason' => $r['eval']['status'] === 'skip' ? $r['eval']['reason'] : null,
            ];
        }

        return response()->json([
            'success' => true,
            'filled' => $res['filled'],
            'skipped' => $res['skipped'],
            'rows' => $rows,
            'settings' => $settings,
        ]);
    }

    /** Change log + monitoring page. */
    public function logIndex()
    {
        return view('market-places.reverb_bump_log_view');
    }

    public function logData(): JsonResponse
    {
        $ready = Schema::hasTable('reverb_bump_change_log');
        if (! $ready) {
            return response()->json(['table_ready' => false, 'history' => [], 'monitor' => []]);
        }

        $history = ReverbBumpChangeLog::query()
            ->orderByDesc('id')
            ->limit(5000)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'at' => $l->created_at?->toDateTimeString(),
                'run_id' => $l->run_id,
                'source' => $l->source,
                'action' => $l->action,
                'status' => $l->status,
                'sku' => $l->sku,
                'sold' => $l->sold,
                'inv' => (float) $l->inv,
                'price' => $l->price !== null ? (float) $l->price : null,
                'old_s_bump' => $l->old_s_bump,
                'old_live_bump' => $l->old_live_bump,
                'new_bump' => $l->new_bump,
                'reason' => $l->reason,
                'message' => $l->message,
            ])->values();

        return response()->json([
            'table_ready' => true,
            'history' => $history,
            'monitor' => ReverbBumpRuleService::monitorRows(),
            'settings' => ReverbBumpRuleService::settings(),
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
            $res['raw'] = is_array($res['raw'] ?? null) ? self::withoutHiddenFields($res['raw']) : ($res['raw'] ?? null);
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
            if (is_array($pulled['raw'] ?? null)) {
                $pulled['raw'] = self::withoutHiddenFields($pulled['raw']);
            }
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
     * Drop every "description", "_links", "deleted_bid", "bids" and "budgets" key (any depth) from a raw response before it goes to the page.
     * The stored reverb_bump_raw row stays untouched; only what the table receives is trimmed.
     */
    private static function withoutHiddenFields(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($key === '' || $key === null) {
                continue; // a nameless key would become a column with no header
            }
            if (is_string($key) && in_array(strtolower($key), ['description', '_links', 'deleted_bid', 'bids', 'budgets'], true)) {
                continue;
            }
            $out[$key] = is_array($value) ? self::withoutHiddenFields($value) : $value;
        }

        return $out;
    }

    /**
     * @return array{listing_id: string, success: bool, status: ?int, message: ?string, fetched_at: ?string, raw: ?array}
     */
    private function pullOne(ReverbApiService $api, string $listingId, ?string $sku = null): array
    {
        return ReverbBumpRawPuller::pullOne($api, $listingId, $sku);
    }
}
