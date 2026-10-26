<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\GoogleAdsCampaign;
use App\Models\GoogleAdsNegativeKeyword;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\GoogleAdsSbidService;
use App\Services\GoogleShoppingLiveBidBgtService;
use App\Support\GoogleShoppingLiveSyncStatus;
use App\Support\GoogleShoppingBgtCvrRule;
use App\Support\GoogleShoppingBgtParts;
use App\Support\GoogleShoppingBgtDilRule;
use App\Support\GoogleShoppingBgtPrcRule;
use App\Support\GoogleShoppingBgtSkuMetrics;
use App\Support\GoogleShoppingBgtViewsRule;
use App\Support\GoogleShoppingCampaignsRawRule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GoogleShoppingCampaignsController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Tabular view of google_ads_campaigns rows only (no SKU matching or transforms).
     */
    public function index()
    {
        return view('campaign.google-shopping', [
            'googleShoppingRule' => GoogleShoppingCampaignsRawRule::resolvedRule(),
        ]);
    }

    /**
     * Scope the raw grid to non-SERP Shopping campaigns: exclude names containing
     * the word "SEARCH" (e.g. "DRUM THRONES SEARCH" or "PARENT GS REST SEARCH.")
     * and names ending with " YT" (YouTube ads on /google/shopping/youtube-ads).
     * Those rows live on dedicated pages via {@see GoogleSerpCampaignsController}
     * and {@see GoogleYoutubeAdsCampaignsController}.
     *
     * Leading space gives word-boundary matching so substrings like "RESEARCH" are
     * not affected. Subclasses (SERP) override this to flip the condition.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    protected function applyCampaignNameScope($query, string $columnExpression = 'campaign_name'): void
    {
        $query->whereRaw("UPPER({$columnExpression}) NOT LIKE ?", ['% SEARCH%'])
            ->whereRaw("UPPER({$columnExpression}) NOT LIKE ?", ['% YT']);
    }

    /**
     * Short channel key stored alongside each SBGT snapshot so the three grids
     * (which share google_ads_campaigns) stay distinguishable. Subclasses override.
     */
    protected function channelKey(): string
    {
        return 'shopping';
    }

    /**
     * The audit page view for this channel. Subclasses (YouTube) override with their own view.
     */
    protected function auditView(): string
    {
        return 'campaign.google-shopping-audit';
    }

    /**
     * The route name of this channel's grid page (used for the audit "Link" column).
     */
    protected function auditGridRouteName(): string
    {
        return 'google.shopping.campaigns';
    }

    /**
     * Amazon-style audit page (Fixed? + details, red/green 30-day dot + history) for this channel.
     */
    public function auditPage()
    {
        return view($this->auditView());
    }

    /**
     * One row per campaign with Cvr / CTR / ACOS, its audit history and dot status.
     * Sorted oldest-audited (or never audited) first — same shape as /amazon-ads/audit.
     */
    public function auditPageData(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $rows = [];
        $page = 1;
        do {
            $req = \Illuminate\Http\Request::create('', 'GET', ['size' => 1000, 'page' => $page]);
            $j = json_decode($this->data($req)->getContent(), true);
            foreach (($j['data'] ?? []) as $r) {
                $rows[] = $r;
            }
            $lastPage = (int) ($j['last_page'] ?? 1);
            $page++;
        } while ($page <= $lastPage && $page <= 20);

        $histByCid = \App\Models\GoogleAdsAuditHistory::where('channel', $this->channelKey())
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn ($h) => (string) $h->campaign_id);

        $now = \Illuminate\Support\Carbon::now();
        $greenDays = 30;
        $link = route($this->auditGridRouteName());
        $data = [];
        $seen = [];
        foreach ($rows as $r) {
            $cid = trim((string) ($r['campaign_id'] ?? ''));
            if ($cid === '' || isset($seen[$cid])) {
                continue;
            }
            $seen[$cid] = true;

            $cvr = isset($r['cvr_l30']) && is_numeric($r['cvr_l30']) ? round((float) $r['cvr_l30'], 2) : null;
            $ctr = isset($r['ctr_l30']) && is_numeric($r['ctr_l30']) ? round((float) $r['ctr_l30'], 2) : null;
            $acos = isset($r['acos_l30']) && is_numeric($r['acos_l30']) ? round((float) $r['acos_l30'], 2) : null;

            $hist = $histByCid->get($cid, collect());
            $historyArr = [];
            foreach ($hist as $h) {
                $historyArr[] = [
                    'fixed' => (bool) $h->fixed,
                    'details' => (string) $h->details,
                    'created_at' => optional($h->created_at)->format('Y-m-d H:i'),
                ];
            }
            $latest = $hist->last();
            $latestAt = $latest && $latest->created_at ? $latest->created_at : null;
            $isGreen = $latestAt !== null && $latestAt->gt($now->copy()->subDays($greenDays));

            $data[] = [
                'campaign_id' => $cid,
                'campaign_name' => (string) ($r['campaign_name'] ?? ''),
                'cvr' => $cvr,
                'ctr' => $ctr,
                'acos' => $acos,
                'link' => $link,
                'dot' => $isGreen ? 'green' : 'red',
                'latest_audit_at' => $latestAt ? $latestAt->format('Y-m-d H:i') : null,
                'latest_audit_ts' => $latestAt ? $latestAt->getTimestamp() : 0,
                'history' => $historyArr,
            ];
        }

        usort($data, fn ($a, $b) => $a['latest_audit_ts'] <=> $b['latest_audit_ts']);

        return response()->json(['data' => $data]);
    }

    /**
     * Record an audit submission for a campaign in this channel (both fields mandatory).
     */
    public function auditPageSave(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => ['required', 'string', 'max:255'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'fixed' => ['required', 'boolean'],
            'details' => ['required', 'string'],
        ]);

        \App\Models\GoogleAdsAuditHistory::create([
            'channel' => $this->channelKey(),
            'campaign_id' => $validated['campaign_id'],
            'campaign_name' => $validated['campaign_name'] ?? null,
            'fixed' => (bool) $validated['fixed'],
            'details' => $validated['details'],
            'user_id' => auth()->id(),
            'created_at' => \Illuminate\Support\Carbon::now(),
        ]);

        $hist = \App\Models\GoogleAdsAuditHistory::where('channel', $this->channelKey())
            ->where('campaign_id', $validated['campaign_id'])
            ->orderBy('created_at')->get();
        $historyArr = $hist->map(fn ($h) => [
            'fixed' => (bool) $h->fixed,
            'details' => (string) $h->details,
            'created_at' => optional($h->created_at)->format('Y-m-d H:i'),
        ])->all();
        $latest = $hist->last();
        $latestAt = $latest && $latest->created_at ? $latest->created_at : null;
        $isGreen = $latestAt !== null && $latestAt->gt(\Illuminate\Support\Carbon::now()->subDays(30));

        return response()->json([
            'ok' => true,
            'dot' => $isGreen ? 'green' : 'red',
            'latest_audit_at' => $latestAt ? $latestAt->format('Y-m-d H:i') : null,
            'history' => $historyArr,
        ]);
    }

    /**
     * SQL expression used to compute "L30 Sales" inside aggregate queries (sort, summary,
     * ACOS color filter, sales_l30_agg). Returns the L30 sum of `ga4_actual_revenue` —
     * the actual GA4 Analytics Data API revenue that matches the GA4 dashboard "Total
     * revenue".
     *
     * The grid intentionally does NOT fall back to Google Ads `metrics.conversionsValue`
     * (column `ga4_ad_sales`) when GA4 reports $0. Earlier behaviour silently swapped
     * to that fallback, producing rows like `RETRO MICS SEARCH` (SERP) showing 102 and
     * `PARENT GSTOOL RND REST` (Shopping) showing 56 even though GA4 had $0.00 — Google
     * Ads conversionsValue was being labelled as GA4 sales. After this change, `0` on
     * the grid means GA4 sees zero, which is what the column header promises.
     *
     * Subclasses can still override this if a future page legitimately needs the old
     * fallback semantics.
     */
    protected static function salesL30SqlExpression(): string
    {
        return 'COALESCE(agg.sum_ga4_actual, 0)';
    }

    /**
     * SQL expression for L30 Sold. Shopping/SERP stay on GA4 actual purchases.
     * YouTube overrides to fall back to Google Ads conversions when GA4 is 0.
     */
    protected static function soldL30SqlExpression(): string
    {
        return 'COALESCE(agg.sum_ga4_actual_sold, 0)';
    }

    /**
     * PHP-side counterpart of {@see salesL30SqlExpression()} used while enriching each row
     * for the Tabulator grid. Kept in sync with the SQL so column value, sort, and ACOS
     * agree to the dollar.
     *
     * The `$sumGoogleAdsConversionsValue` argument is intentionally unused here — it is
     * kept in the signature so the hook can be overridden by a subclass that wants the
     * old fallback semantics without having to widen the signature later.
     */
    protected static function resolveSalesL30Value(float $sumGa4ActualRevenue, float $sumGoogleAdsConversionsValue): float
    {
        return $sumGa4ActualRevenue;
    }

    /**
     * PHP-side counterpart of {@see soldL30SqlExpression()}.
     */
    protected static function resolveSoldL30Value(float $sumGa4ActualSoldUnits, float $sumGoogleAdsConversions): float
    {
        return $sumGa4ActualSoldUnits;
    }

    public function getRule(): JsonResponse
    {
        return response()->json([
            'rule' => GoogleShoppingCampaignsRawRule::resolvedRule(),
        ]);
    }

    public function saveRule(Request $request): JsonResponse
    {
        try {
            $normalized = GoogleShoppingCampaignsRawRule::normalizeRule($request->all());
            GoogleShoppingCampaignsRawRule::persistRule($normalized);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not save Google Shopping rule.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'message' => 'Rule saved. Refresh the grid to apply SBGT and SBID.',
            'rule' => GoogleShoppingCampaignsRawRule::resolvedRule(),
            'status' => 200,
        ]);
    }

    public function getBgtViewsRule(): JsonResponse
    {
        return $this->jsonBgtRuleGet(GoogleShoppingBgtViewsRule::class);
    }

    public function saveBgtViewsRule(Request $request): JsonResponse
    {
        return $this->jsonBgtRuleSave(
            $request,
            GoogleShoppingBgtViewsRule::class,
            'Could not save BGT Vs VIEWS rule.',
            'BGT Vs VIEWS saved. Bgt Views on the grid will use the new View L7 bands after reload.'
        );
    }

    public function getBgtCvrRule(): JsonResponse
    {
        return $this->jsonBgtRuleGet(GoogleShoppingBgtCvrRule::class);
    }

    public function saveBgtCvrRule(Request $request): JsonResponse
    {
        return $this->jsonBgtRuleSave(
            $request,
            GoogleShoppingBgtCvrRule::class,
            'Could not save BGT Vs CVR rule.',
            'BGT Vs CVR saved. Bgt Cvr on the grid will use the new CVR L30 bands after reload.'
        );
    }

    public function getBgtPrcRule(): JsonResponse
    {
        return $this->jsonBgtRuleGet(GoogleShoppingBgtPrcRule::class);
    }

    public function saveBgtPrcRule(Request $request): JsonResponse
    {
        return $this->jsonBgtRuleSave(
            $request,
            GoogleShoppingBgtPrcRule::class,
            'Could not save BGT PRC rule.',
            'BGT PRC saved. BGT PRC on the grid will use the new Price bands after reload.'
        );
    }

    public function getBgtDilRule(): JsonResponse
    {
        return $this->jsonBgtRuleGet(GoogleShoppingBgtDilRule::class);
    }

    public function saveBgtDilRule(Request $request): JsonResponse
    {
        return $this->jsonBgtRuleSave(
            $request,
            GoogleShoppingBgtDilRule::class,
            'Could not save BGT Vs DIL rule.',
            'BGT Vs DIL saved. BGT DIL on the grid will use the new Dil bands after reload.'
        );
    }

    /**
     * @param  class-string  $class
     */
    private function jsonBgtRuleGet(string $class): JsonResponse
    {
        $class::forgetResolvedCache();

        return response()->json([
            'rule' => $class::resolvedRule(),
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * @param  class-string  $class
     */
    private function jsonBgtRuleSave(Request $request, string $class, string $failMsg, string $okMsg): JsonResponse
    {
        try {
            $normalized = $class::normalizeRule($request->all());
            $class::persistRule($normalized);
            $class::forgetResolvedCache();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $failMsg,
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'message' => $okMsg,
            'rule' => $class::resolvedRule(),
            'status' => 200,
            'timestamp' => time(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Push SBGT to Google Ads for each sent campaign_id using the same values as the grid
     * (no product-master SKU matching).
     *
     * Request JSON: `{ "campaign_ids": ["…"] }` — rows on the current grid page (max 1000).
     */
    public function pushSbgtShoppingBudgets(Request $request): JsonResponse
    {
        $ids = $this->validatedPushCampaignIds($request);
        if ($ids === []) {
            return $this->pushCampaignIdsMissingResponse('push-sbgt');
        }

        return $this->pushGridSbgt($ids);
    }

    /**
     * Push SBID to Google Ads for each sent campaign_id using the same values as the grid
     * (no product-master SKU matching).
     *
     * Request JSON: `{ "campaign_ids": ["…"] }` — rows on the current grid page (max 1000).
     */
    public function pushSbidShopping(Request $request): JsonResponse
    {
        $ids = $this->validatedPushCampaignIds($request);
        if ($ids === []) {
            return $this->pushCampaignIdsMissingResponse('push-sbid');
        }

        return $this->pushGridSbid($ids);
    }

    /**
     * Pause / enable campaigns straight from the grid's toggle switch (single or bulk).
     * Each campaign is updated in Google Ads first; only on success is the local status changed.
     *
     * Request: `campaign_ids[]` (digits), `status` = ENABLED | PAUSED.
     * Response: `{ ok, status, updated, skipped, failed, results: [{campaign_id, name, ok, status, message}] }`.
     */
    public function toggleCampaignStatus(Request $request): JsonResponse
    {
        $target = strtoupper(trim((string) $request->input('status', '')));
        if (! in_array($target, ['ENABLED', 'PAUSED'], true)) {
            return response()->json(['ok' => false, 'message' => 'status must be ENABLED or PAUSED.'], 422);
        }

        $ids = $this->validatedPushCampaignIds($request);
        if ($ids === []) {
            return response()->json(['ok' => false, 'message' => 'No campaign_ids to update.'], 422);
        }
        if (count($ids) > 50) {
            return response()->json(['ok' => false, 'message' => 'Too many campaigns in one request (max 50). Send in chunks.'], 422);
        }

        @ini_set('max_execution_time', '120');
        set_time_limit(120);

        $customerId = config('services.google_ads.login_customer_id');
        if (! $customerId) {
            return response()->json(['ok' => false, 'message' => 'Google Ads customer ID is not configured.'], 500);
        }

        // Latest known row per campaign (limited to this channel's campaigns).
        $latestQuery = DB::table('google_ads_campaigns')->whereIn('campaign_id', $ids);
        $this->applyCampaignNameScope($latestQuery);
        $known = [];
        foreach ($latestQuery->orderByDesc('date')->get(['campaign_id', 'campaign_name', 'campaign_status']) as $r) {
            $cid = (string) $r->campaign_id;
            if (! isset($known[$cid])) {
                $known[$cid] = [
                    'name' => (string) $r->campaign_name,
                    'status' => strtoupper(trim((string) $r->campaign_status)),
                ];
            }
        }

        /** @var GoogleAdsSbidService $sbidService */
        $sbidService = app(GoogleAdsSbidService::class);
        $results = [];
        $updated = $skipped = $failed = 0;

        foreach ($ids as $cid) {
            $cid = (string) $cid;
            $info = $known[$cid] ?? null;
            if ($info === null) {
                $results[] = ['campaign_id' => $cid, 'name' => '', 'ok' => false, 'status' => null, 'message' => 'Campaign not found in this grid.'];
                $skipped++;

                continue;
            }
            if ($info['status'] === 'REMOVED') {
                $results[] = ['campaign_id' => $cid, 'name' => $info['name'], 'ok' => false, 'status' => 'REMOVED', 'message' => 'Campaign is REMOVED in Google Ads and cannot be changed.'];
                $skipped++;

                continue;
            }

            try {
                $resource = "customers/{$customerId}/campaigns/{$cid}";
                if ($target === 'PAUSED') {
                    $sbidService->pauseCampaign($customerId, $resource);
                } else {
                    $sbidService->enableCampaign($customerId, $resource);
                }
                GoogleAdsCampaign::where('campaign_id', $cid)->update(['campaign_status' => $target]);
                $results[] = ['campaign_id' => $cid, 'name' => $info['name'], 'ok' => true, 'status' => $target, 'message' => $target === 'PAUSED' ? 'Paused in Google Ads.' : 'Enabled in Google Ads.'];
                $updated++;
            } catch (\Throwable $e) {
                $results[] = ['campaign_id' => $cid, 'name' => $info['name'], 'ok' => false, 'status' => $info['status'], 'message' => GoogleAdsSbidService::briefGoogleError($e)];
                $failed++;
            }
        }

        if ($updated > 0) {
            $this->bumpRawGridRowsCache();
        }

        return response()->json([
            'ok' => $failed === 0 && $updated > 0,
            'status' => $target,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => "{$updated} updated, {$failed} failed, {$skipped} skipped.",
            'results' => $results,
        ]);
    }

    /**
     * Fix Item ID verification issues: add the Merchant Center Item IDs missing from the campaign's
     * Google Ads listing groups and remove the ones that do not belong to the SKU(s) of the campaign.
     *
     * Request: `campaign_ids[]`, `dry_run` (1 = only return the plan, nothing is changed).
     */
    public function fixItemIds(Request $request): JsonResponse
    {
        $ids = $this->validatedPushCampaignIds($request);
        if ($ids === []) {
            return response()->json(['ok' => false, 'message' => 'No campaign_ids to fix.'], 422);
        }
        if (count($ids) > 10) {
            return response()->json(['ok' => false, 'message' => 'Too many campaigns in one request (max 10). Send in chunks.'], 422);
        }
        $customerId = config('services.google_ads.login_customer_id');
        if (! $customerId) {
            return response()->json(['ok' => false, 'message' => 'Google Ads customer ID is not configured.'], 500);
        }
        $dryRun = $request->boolean('dry_run');

        @ini_set('max_execution_time', '120');
        set_time_limit(120);

        $latestQuery = DB::table('google_ads_campaigns')->whereIn('campaign_id', $ids);
        $this->applyCampaignNameScope($latestQuery);
        $names = [];
        $statuses = [];
        foreach ($latestQuery->orderByDesc('date')->get(['campaign_id', 'campaign_name', 'campaign_status']) as $r) {
            $key = (string) $r->campaign_id;
            if (! isset($names[$key])) {
                $names[$key] = (string) $r->campaign_name;
                $statuses[$key] = strtoupper(trim((string) $r->campaign_status));
            }
        }

        /** @var GoogleAdsSbidService $sbidService */
        $sbidService = app(GoogleAdsSbidService::class);
        $invResolver = $this->buildInventoryResolver();
        $results = [];
        $applied = 0;

        foreach ($ids as $cid) {
            $cid = (string) $cid;
            if (! isset($names[$cid])) {
                $results[] = ['campaign_id' => $cid, 'name' => '', 'status' => 'error', 'add' => [], 'reinclude' => [], 'enable' => [], 'remove' => [], 'skip' => [], 'applied' => false, 'message' => 'Campaign not found in this grid.'];

                continue;
            }
            $skus = $this->childSkusForCampaignName($names[$cid]);
            // SKUs with no stock are not expected in the ad (keeps Fix consistent with the Verification column).
            $inStock = array_values(array_filter($skus, static function ($sku) use ($invResolver) {
                $inv = $invResolver($sku);

                return $inv === null || (float) $inv > 0;
            }));
            $expectedBySku = $this->resolveMerchantCenterItemIds($skus);
            $expected = [];
            foreach ($inStock !== [] ? $inStock : $skus as $sku) {
                $itemId = $expectedBySku[$sku] ?? '';
                if ($itemId !== '') {
                    $expected[$itemId] = $itemId;
                }
            }
            $expected = array_values($expected);

            // Item IDs are fixed regardless of inventory.
            $res = $sbidService->syncShoppingItemIds((string) $customerId, $cid, $expected, $dryRun);
            $missingSkus = count($skus) - count($expected);
            if ($missingSkus > 0 && $res['status'] !== 'error') {
                $res['message'] .= " ({$missingSkus} SKU(s) have no Merchant Center ID in the catalog and cannot be added.)";
            }
            if ($res['applied']) {
                $applied++;
            }

            // INV 0 (parent = sum of children) → the ad is paused automatically.
            $inv = $invResolver($names[$cid]);
            $invN = $inv !== null ? (int) $inv : 0;
            $pauseRule = (bool) (GoogleShoppingCampaignsRawRule::resolvedRule()['sbgt']['pause_if_inv_zero'] ?? true);
            $needsPause = $pauseRule && $invN <= 0 && ($statuses[$cid] ?? '') === 'ENABLED';
            $res['pause_campaign'] = $needsPause;
            $res['paused'] = false;
            if ($needsPause) {
                if ($dryRun) {
                    $res['message'] .= ' Campaign will be paused (INV 0).';
                } else {
                    try {
                        $sbidService->pauseCampaign((string) $customerId, "customers/{$customerId}/campaigns/{$cid}");
                        GoogleAdsCampaign::where('campaign_id', $cid)->update(['campaign_status' => 'PAUSED']);
                        $res['paused'] = true;
                        $res['message'] .= ' Campaign paused (INV 0).';
                        $this->bumpRawGridRowsCache();
                    } catch (\Throwable $e) {
                        $res['message'] .= ' Pause failed (INV 0): '.GoogleAdsSbidService::briefGoogleError($e);
                        $res['status'] = $res['status'] === 'ok' ? 'error' : $res['status'];
                    }
                }
            }

            // Remember the last problem so the grid's Reason column can show it after a reload.
            $noteKey = 'gads_shopping_fix_note:'.$cid;
            if (in_array($res['status'], ['error', 'unsupported'], true) || ($res['skip'] ?? []) !== []) {
                $detail = $res['message'];
                if (($res['skip'] ?? []) !== []) {
                    $detail .= ' '.implode('; ', $res['skip']);
                }
                Cache::put($noteKey, '['.now()->format('M j H:i').'] '.$detail, now()->addDays(7));
            } elseif ($res['applied'] || $res['status'] === 'nothing') {
                Cache::forget($noteKey);
            }

            $results[] = ['campaign_id' => $cid, 'name' => $names[$cid]] + $res;
        }

        if ($applied > 0) {
            // Live Ads Item IDs are cached for the ID / Verification check — invalidate them.
            Cache::forever('gads_shopping_item_ids_gen', ((int) Cache::get('gads_shopping_item_ids_gen', 0)) + 1);
        }

        return response()->json([
            'ok' => true,
            'dry_run' => $dryRun,
            'applied' => $applied,
            'results' => $results,
        ]);
    }

    /**
     * "+" modal: the SKUs behind a campaign (parent → its children) with INV, Merchant Center Item ID and the
     * Item ID's current state in the Google Ads listing groups. Item IDs attached in the ad that belong to no
     * SKU of the campaign are listed too (sku "—") so they can be removed.
     */
    public function campaignSkus(Request $request): JsonResponse
    {
        $cid = preg_replace('/\D+/', '', (string) $request->input('campaign_id', ''));
        if ($cid === '') {
            return response()->json(['ok' => false, 'message' => 'campaign_id is required.'], 422);
        }
        $customerId = config('services.google_ads.login_customer_id');
        if (! $customerId) {
            return response()->json(['ok' => false, 'message' => 'Google Ads customer ID is not configured.'], 500);
        }
        @ini_set('max_execution_time', '60');

        $q = DB::table('google_ads_campaigns')->where('campaign_id', $cid);
        $this->applyCampaignNameScope($q);
        $latest = $q->orderByDesc('date')->first(['campaign_name', 'campaign_status']);
        if (! $latest) {
            return response()->json(['ok' => false, 'message' => 'Campaign not found in this grid.'], 404);
        }
        $name = (string) $latest->campaign_name;

        $skus = $this->childSkusForCampaignName($name);
        sort($skus, SORT_NATURAL | SORT_FLAG_CASE);
        $itemBySku = $this->resolveMerchantCenterItemIds($skus);
        $invResolver = $this->buildInventoryResolver();

        /** @var GoogleAdsSbidService $sbidService */
        $sbidService = app(GoogleAdsSbidService::class);
        $live = $sbidService->listShoppingItemStates((string) $customerId, $cid);

        $rows = [];
        $known = [];
        foreach ($skus as $sku) {
            $item = (string) ($itemBySku[$sku] ?? '');
            $inv = $invResolver($sku);
            if ($item !== '') {
                $known[strtolower($item)] = true;
            }
            $state = $item === '' ? 'NO_ITEM_ID' : ($live['items'][strtolower($item)]['state'] ?? 'NOT_ATTACHED');
            $rows[] = ['sku' => $sku, 'inv' => $inv, 'item_id' => $item, 'state' => $state, 'extra' => false];
        }
        foreach ($live['items'] as $key => $info) {
            if (! isset($known[$key])) {
                $rows[] = ['sku' => '—', 'inv' => null, 'item_id' => $info['item'], 'state' => $info['state'], 'extra' => true];
            }
        }

        return response()->json([
            'ok' => true,
            'campaign_id' => $cid,
            'name' => $name,
            'campaign_status' => strtoupper(trim((string) $latest->campaign_status)),
            'inv_total' => $invResolver($name),
            'live_error' => $live['status'] === 'error' ? $live['message'] : '',
            'live_note' => $live['status'] === 'ok' ? $live['message'] : '',
            'rows' => $rows,
        ]);
    }

    /**
     * N Target click → Keyword Planner modal ("Discover new keywords").
     * Request: keywords (comma / new-line separated), site, scope (page|site), language, geos[], network, include_adult.
     * Returns keyword ideas with search volume, 3-month / YoY change, competition, bids, monthly trend and concepts.
     */
    public function keywordPlannerIdeas(Request $request): JsonResponse
    {
        $customerId = (string) config('services.google_ads.login_customer_id');
        if ($customerId === '') {
            return response()->json(['ok' => false, 'message' => 'Google Ads customer ID is not configured.', 'rows' => [], 'concepts' => []], 500);
        }
        $keywords = preg_split('/[\r\n,]+/', (string) $request->input('keywords', ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keywords = array_slice(array_values(array_unique(array_map(static fn ($k) => mb_substr(trim($k), 0, 80), $keywords))), 0, 20);
        $site = trim((string) $request->input('site', ''));
        if ($site !== '' && ! preg_match('#^https?://#i', $site)) {
            $site = 'https://'.$site;
        }
        if ($site !== '' && ! filter_var($site, FILTER_VALIDATE_URL)) {
            return response()->json(['ok' => false, 'message' => 'Enter a valid site URL.', 'rows' => [], 'concepts' => []], 422);
        }
        @ini_set('max_execution_time', '90');
        /** @var GoogleAdsSbidService $svc */
        $svc = app(GoogleAdsSbidService::class);
        $res = $svc->keywordPlannerIdeas($customerId, [
            'keywords' => $keywords,
            'url' => $site,
            'scope' => (string) $request->input('scope', 'page'),
            'language' => (string) $request->input('language', '1000'),
            'geos' => array_slice((array) $request->input('geos', ['2840']), 0, 10),
            'network' => (string) $request->input('network', 'partners'),
            'include_adult' => $request->boolean('include_adult'),
            'limit' => 800,
        ]);

        return response()->json($res, $res['ok'] ? 200 : 422);
    }

    /**
     * Keyword Planner forecast for the keywords the user added to the plan.
     * Request: keywords[] ({text, match}), language, geos[], max_cpc, daily_budget, days.
     */
    public function keywordPlannerForecast(Request $request): JsonResponse
    {
        $customerId = (string) config('services.google_ads.login_customer_id');
        if ($customerId === '') {
            return response()->json(['ok' => false, 'message' => 'Google Ads customer ID is not configured.', 'forecast' => null], 500);
        }
        $kw = [];
        foreach (array_slice((array) $request->input('keywords', []), 0, 100) as $k) {
            if (is_array($k) && trim((string) ($k['text'] ?? '')) !== '') {
                $kw[] = ['text' => mb_substr(trim((string) $k['text']), 0, 80), 'match' => (string) ($k['match'] ?? 'BROAD')];
            }
        }
        @ini_set('max_execution_time', '90');
        /** @var GoogleAdsSbidService $svc */
        $svc = app(GoogleAdsSbidService::class);
        $res = $svc->keywordPlannerForecast($customerId, $kw, [
            'language' => (string) $request->input('language', '1000'),
            'geos' => array_slice((array) $request->input('geos', ['2840']), 0, 10),
            'max_cpc' => (float) $request->input('max_cpc', 1),
            'daily_budget' => (float) $request->input('daily_budget', 50),
            'days' => (int) $request->input('days', 30),
        ]);

        return response()->json($res, $res['ok'] ? 200 : 422);
    }

    /**
     * "+" modal dropdown: enable / pause / remove one Item ID in the campaign's Google Ads listing groups.
     * Request: campaign_id, item_id, action (enable|pause|remove).
     */
    public function campaignSkuAction(Request $request): JsonResponse
    {
        $cid = preg_replace('/\D+/', '', (string) $request->input('campaign_id', ''));
        $item = trim((string) $request->input('item_id', ''));
        $action = strtolower(trim((string) $request->input('action', '')));
        if ($cid === '' || $item === '' || ! in_array($action, ['enable', 'pause', 'remove'], true)) {
            return response()->json(['ok' => false, 'message' => 'campaign_id, item_id and a valid action are required.'], 422);
        }
        $customerId = config('services.google_ads.login_customer_id');
        if (! $customerId) {
            return response()->json(['ok' => false, 'message' => 'Google Ads customer ID is not configured.'], 500);
        }

        $q = DB::table('google_ads_campaigns')->where('campaign_id', $cid);
        $this->applyCampaignNameScope($q);
        if (! $q->exists()) {
            return response()->json(['ok' => false, 'message' => 'Campaign not found in this grid.'], 404);
        }

        @ini_set('max_execution_time', '60');
        /** @var GoogleAdsSbidService $sbidService */
        $sbidService = app(GoogleAdsSbidService::class);
        $res = $sbidService->setShoppingItemState((string) $customerId, $cid, $item, $action);
        if ($res['ok']) {
            // Live Ads Item IDs are cached for the ID / Verification check — invalidate them.
            Cache::forever('gads_shopping_item_ids_gen', ((int) Cache::get('gads_shopping_item_ids_gen', 0)) + 1);
            $this->bumpRawGridRowsCache();
        }

        return response()->json($res, $res['ok'] ? 200 : 422);
    }

    /**
     * Manually trigger `app:fetch-google-ads-campaigns` so missing rows can be back-filled
     * without waiting for the daily 09:00 IST cron. Runs synchronously — the request blocks
     * until the fetch completes or fails so the UI can show real success/failure.
     *
     * Request JSON (all optional): `{ "days": 1 }` — capped to 1..30 to keep API usage sane.
     */
    public function pullData(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 1);
        if ($days < 1) {
            $days = 1;
        }
        if ($days > 30) {
            $days = 30;
        }

        $response = $this->runArtisanPush(
            'app:fetch-google-ads-campaigns',
            ['--days' => (string) $days],
            'app:fetch-google-ads-campaigns'
        );
        $this->bumpRawGridRowsCache();

        return $response;
    }

    /**
     * Shopping campaign ids with the SBID and SBGT the grid would show.
     * Read from the database only. The background job compares these with Google Ads.
     *
     * @return array<string, array{sbid: mixed, sbgt: mixed}>
     */
    public function shoppingLiveVerificationTargets(): array
    {
        if ($this->channelKey() !== 'shopping') {
            return [];
        }

        @ini_set('memory_limit', '512M');
        $query = $this->buildRawGridBaseQuery();
        $rawRule = GoogleShoppingCampaignsRawRule::resolvedRule();
        $invResolver = $this->buildInventoryResolver();
        $bgtResolver = $this->shoppingBgtMetricsResolver();
        $out = [];
        foreach ($query->get() as $row) {
            $arr = $this->hydrateRawGridRow($row, $rawRule, $invResolver, $bgtResolver);
            $cid = preg_replace('/\D+/', '', (string) ($arr['campaign_id'] ?? '')) ?? '';
            if ($cid === '' || isset($out[$cid])) {
                continue;
            }
            $out[$cid] = [
                'sbid' => $arr['sbid'] ?? null,
                'sbgt' => $arr['sbgt'] ?? null,
                'status' => strtoupper(trim((string) ($arr['campaign_status'] ?? ''))),
            ];
        }

        return $out;
    }

    /**
     * Background push of SBID for campaigns whose live bid differs. Same rules and Google Ads
     * calls as the page's Push SBID button (ENABLED only, no SBID means skip).
     *
     * @param  list<string>  $campaignIds
     * @return array{ok: bool, message: string, output: string}
     */
    public function pushSbidForLiveMatch(array $campaignIds): array
    {
        return $this->pushResultArray($this->pushGridSbid($campaignIds));
    }

    /**
     * Background push of SBGT for campaigns whose live budget differs. Same rules and Google Ads
     * calls as the page's Push SBGT button.
     *
     * @param  list<string>  $campaignIds
     * @return array{ok: bool, message: string, output: string}
     */
    public function pushSbgtForLiveMatch(array $campaignIds): array
    {
        return $this->pushResultArray($this->pushGridSbgt($campaignIds));
    }

    /**
     * @return array{ok: bool, message: string, output: string}
     */
    private function pushResultArray(JsonResponse $response): array
    {
        $data = $response->getData(true);

        return [
            'ok' => (bool) ($data['ok'] ?? false),
            'message' => (string) ($data['message'] ?? ''),
            'output' => (string) ($data['output'] ?? ''),
        ];
    }

    /**
     * @return list<string>
     */
    private function validatedPushCampaignIds(Request $request): array
    {
        $raw = $request->input('campaign_ids');
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $id) {
            if (! is_scalar($id)) {
                continue;
            }
            $d = preg_replace('/\D/', '', (string) $id);
            if ($d !== '' && strlen($d) <= 32) {
                $out[$d] = true;
            }
            if (count($out) >= 1000) {
                break;
            }
        }

        return array_keys($out);
    }

    private function pushCampaignIdsMissingResponse(string $command): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'exit_code' => 1,
            'command' => $command,
            'message' => 'No campaign_ids to process. Load a page with data, or select rows with the checkboxes.',
            'output' => '',
        ], 422);
    }

    /**
     * Run an Artisan command synchronously and return its exit code + console output.
     * Web requests block until the command finishes so Push/Pull buttons show real success or failure.
     *
     * @param  array<string, bool|string>  $options
     */
    private function runArtisanPush(string $command, array $options, string $labelForLog): JsonResponse
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '0');
        set_time_limit(0);

        try {
            $exitCode = Artisan::call($command, $options);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'exit_code' => 1,
                'command' => $labelForLog,
                'message' => $e->getMessage(),
                'output' => '',
            ], 500);
        }

        $output = trim(Artisan::output());
        $max = 16000;
        if (strlen($output) > $max) {
            $output = substr($output, 0, $max)."\n… (truncated)";
        }

        return response()->json([
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'command' => $labelForLog,
            'message' => $exitCode === 0 ? 'Command finished successfully.' : 'Command failed — see output below.',
            'output' => $output,
        ], $exitCode === 0 ? 200 : 422);
    }

    /**
     * @param  list<string>  $campaignIds
     */
    private function pushGridSbgt(array $campaignIds): JsonResponse
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '0');
        set_time_limit(0);

        $customerId = config('services.google_ads.login_customer_id');
        if (! $customerId) {
            return response()->json([
                'ok' => false,
                'exit_code' => 1,
                'command' => $this->pushSbgtCommandLabel(),
                'message' => 'Google Ads customer ID is not configured.',
                'output' => '',
            ], 500);
        }

        /** @var GoogleAdsSbidService $sbidService */
        $sbidService = app(GoogleAdsSbidService::class);
        $rowsById = $this->enrichedRowsForCampaignIds($campaignIds);
        $fallbackBudgetIds = $this->latestBudgetIdsByCampaignId($campaignIds);
        $lines = [];
        $updated = 0;
        $paused = 0;
        $skipped = 0;
        $errors = 0;

        $lines[] = 'Pushing SBGT for '.count($campaignIds).' campaign id(s) from grid (direct, no SKU matching)...';
        $lines[] = 'SBGT 0 cannot be pushed as daily budget — those ENABLED campaigns are paused instead.';

        foreach ($campaignIds as $campaignId) {
            $row = $rowsById[$campaignId] ?? null;
            if ($row === null) {
                $lines[] = "[SKIP] {$campaignId}: not found in grid data (no L30 rows or outside page scope).";
                $skipped++;

                continue;
            }

            $name = (string) ($row['campaign_name'] ?? $campaignId);
            $status = strtoupper(trim((string) ($row['campaign_status'] ?? '')));
            if ($status !== 'ENABLED') {
                $lines[] = "[SKIP] {$name} ({$campaignId}): campaign status is {$status}, not ENABLED.";
                $skipped++;

                continue;
            }

            $currentBudget = (float) ($row['bgt'] ?? 0);
            $newBudget = (int) ($row['sbgt'] ?? 0);
            $acos = round((float) ($row['acos_l30'] ?? 0), 1);
            $inv = (int) ($row['inventory'] ?? 0);

            if ($newBudget < 1) {
                try {
                    $campaignResourceName = "customers/{$customerId}/campaigns/{$campaignId}";
                    $sbidService->pauseCampaign($customerId, $campaignResourceName);
                    GoogleAdsCampaign::where('campaign_id', $campaignId)
                        ->update(['campaign_status' => 'PAUSED']);
                    $reason = $inv <= 0 ? 'INV ≤ 0 zeros SBGT' : 'SBGT 0';
                    $lines[] = "[PAUSED] {$name} ({$campaignId}): {$reason} — cannot push \$0 budget.";
                    $paused++;
                } catch (\Throwable $e) {
                    $lines[] = "[ERROR] {$name} ({$campaignId}): pause failed — ".$e->getMessage();
                    $errors++;
                }

                continue;
            }

            $budgetId = $this->normalizeBudgetId($row['budget_id'] ?? null);
            if ($budgetId === '') {
                $budgetId = $fallbackBudgetIds[$campaignId] ?? '';
            }
            if ($budgetId === '') {
                $lines[] = "[SKIP] {$name} ({$campaignId}): missing budget_id.";
                $skipped++;

                continue;
            }

            try {
                $budgetResourceName = "customers/{$customerId}/campaignBudgets/{$budgetId}";
                $sbidService->updateCampaignBudget($customerId, $budgetResourceName, $newBudget);
                $unchanged = (int) round($currentBudget) === $newBudget;
                $changeNote = $unchanged ? ' (already at SBGT — confirmed in Google Ads)' : '';
                $lines[] = "[OK] {$name} ({$campaignId}): Budget=\${$currentBudget} → \${$newBudget}{$changeNote} (ACOS={$acos}%, SBGT={$newBudget})";
                $this->rememberShoppingPush('bgt', $campaignId, (float) $newBudget);
                $updated++;
            } catch (\Throwable $e) {
                $lines[] = "[ERROR] {$name} ({$campaignId}): ".$e->getMessage();
                $errors++;
            }
        }

        $lines[] = '';
        $lines[] = "Done. Updated: {$updated}, Paused (SBGT 0): {$paused}, Skipped: {$skipped}, Errors: {$errors}.";

        if ($paused > 0) {
            $this->bumpRawGridRowsCache();
        }

        $output = implode("\n", $lines);
        $okBits = [];
        if ($updated > 0) {
            $okBits[] = "{$updated} budget(s) updated";
        }
        if ($paused > 0) {
            $okBits[] = "{$paused} paused (SBGT 0)";
        }

        $payload = [
            'ok' => $errors === 0,
            'exit_code' => $errors === 0 ? 0 : 1,
            'command' => $this->pushSbgtCommandLabel(),
            'message' => $errors === 0
                ? 'SBGT push finished'.($okBits !== [] ? ' — '.implode(', ', $okBits).'.' : '.')
                : "SBGT push finished with {$errors} error(s).",
            'output' => $output,
            'paused_zero_sbgt' => $paused,
        ];

        return response()->json($payload, $errors === 0 ? 200 : 422);
    }

    /**
     * @param  list<string>  $campaignIds
     */
    private function pushGridSbid(array $campaignIds): JsonResponse
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '0');
        set_time_limit(0);

        $customerId = config('services.google_ads.login_customer_id');
        if (! $customerId) {
            return response()->json([
                'ok' => false,
                'exit_code' => 1,
                'command' => $this->pushSbidCommandLabel(),
                'message' => 'Google Ads customer ID is not configured.',
                'output' => '',
            ], 500);
        }

        /** @var GoogleAdsSbidService $sbidService */
        $sbidService = app(GoogleAdsSbidService::class);
        $rowsById = $this->enrichedRowsForCampaignIds($campaignIds);
        $lines = [];
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        $lines[] = 'Pushing SBID for '.count($campaignIds).' campaign id(s) from grid (direct, no SKU matching)...';

        foreach ($campaignIds as $campaignId) {
            $row = $rowsById[$campaignId] ?? null;
            if ($row === null) {
                $lines[] = "[SKIP] {$campaignId}: not found in grid data (no L30 rows or outside page scope).";
                $skipped++;

                continue;
            }

            $name = (string) ($row['campaign_name'] ?? $campaignId);
            $status = strtoupper(trim((string) ($row['campaign_status'] ?? '')));
            if ($status !== 'ENABLED') {
                $lines[] = "[SKIP] {$name} ({$campaignId}): campaign status is {$status}, not ENABLED.";
                $skipped++;

                continue;
            }

            $sbid = $row['sbid'] ?? null;
            if ($sbid === null || $sbid === '' || (float) $sbid <= 0) {
                $ub7 = round((float) ($row['ub7'] ?? 0), 1);
                $ub1 = round((float) ($row['ub1'] ?? 0), 1);
                $lines[] = "[SKIP] {$name} ({$campaignId}): no SBID to push (7UB={$ub7}%, 1UB={$ub1}% — mid band shows —).";
                $skipped++;

                continue;
            }

            $sbid = round((float) $sbid, 2);
            $ub7 = round((float) ($row['ub7'] ?? 0), 1);
            $ub1 = round((float) ($row['ub1'] ?? 0), 1);

            try {
                $pushNote = $this->pushSbidToGoogleAds($sbidService, $customerId, $campaignId, $sbid, $row);
                $noteSuffix = $pushNote !== '' ? " — {$pushNote}" : '';
                $lines[] = "[OK] {$name} ({$campaignId}): SBID=\${$sbid} (7UB={$ub7}%, 1UB={$ub1}%){$noteSuffix}";
                $this->rememberShoppingPush('bid', $campaignId, $sbid);
                $updated++;
            } catch (\Throwable $e) {
                $lines[] = "[ERROR] {$name} ({$campaignId}): ".$e->getMessage();
                $errors++;
            }
        }

        $lines[] = '';
        $lines[] = "Done. Updated: {$updated}, Skipped: {$skipped}, Errors: {$errors}.";

        $output = implode("\n", $lines);

        $payload = [
            'ok' => $errors === 0,
            'exit_code' => $errors === 0 ? 0 : 1,
            'command' => $this->pushSbidCommandLabel(),
            'message' => $errors === 0
                ? "SBID push finished — {$updated} campaign(s) updated."
                : "SBID push finished with {$errors} error(s).",
            'output' => $output,
        ];

        return response()->json($payload, $errors === 0 ? 200 : 422);
    }

    /**
     * @param  list<string>  $campaignIds
     * @return array<string, array<string, mixed>>
     */
    private function liveBidBgtStatesForPage(array $campaignIds): array
    {
        if ($this->channelKey() !== 'shopping' || $campaignIds === []) {
            return [];
        }

        return app(GoogleShoppingLiveBidBgtService::class)->statesFor('shopping', $campaignIds);
    }

    /**
     * @param  array<string, mixed>  $arr
     * @param  array<string, array<string, mixed>>  $states
     * @return array<string, mixed>
     */
    private function mergeLiveBidBgtColumns(array $arr, array $states): array
    {
        if ($this->channelKey() !== 'shopping') {
            return $arr;
        }
        $cid = preg_replace('/\D+/', '', (string) ($arr['campaign_id'] ?? '')) ?? '';
        $presented = GoogleShoppingLiveSyncStatus::columns(
            $states[$cid] ?? null,
            $arr['sbid'] ?? null,
            $arr['sbgt'] ?? null
        );
        foreach ($presented as $key => $value) {
            $arr[$key] = $value;
        }
        [$arr['live_cpc'], $arr['live_cpc_tip']] = $this->liveCpcCell($states[$cid] ?? null);

        return $arr;
    }

    /**
     * LT CPC cell: today's average CPC read from Google Ads by `google-shopping:sync-live-cpc` (the page never calls Google).
     *
     * @param  array<string, mixed>|null  $state
     * @return array{0: float|null, 1: string}
     */
    private function liveCpcCell(?array $state): array
    {
        $at = $state['cpc_fetched_at'] ?? null;
        if ($at === null || $at === '') {
            return [null, 'Live CPC has not been pulled from Google Ads yet'];
        }
        $when = Carbon::parse((string) $at)->timezone('Asia/Kolkata')->format('M j, g:i A').' IST';
        $failed = ! in_array($state['cpc_fetch_ok'] ?? false, [true, 1, '1'], true);
        $suffix = $failed ? ' — the last pull failed, showing the previous value' : '';
        $cpc = isset($state['live_cpc']) && is_numeric($state['live_cpc']) && (float) $state['live_cpc'] > 0
            ? round((float) $state['live_cpc'], 2) : null;
        $clicks = (int) ($state['live_cpc_clicks'] ?? 0);
        if ($cpc === null) {
            return [null, 'No clicks today yet (Google Ads, as of '.$when.')'.$suffix];
        }
        $cost = number_format((float) ($state['live_cpc_cost'] ?? 0), 2);

        return [$cpc, 'Today so far: $'.$cost.' ÷ '.number_format($clicks).' clicks = $'.number_format($cpc, 2).' (Google Ads, as of '.$when.')'.$suffix];
    }

    /**
     * Enabled Shopping campaign ids (database only) for the live CPC pull.
     *
     * @return list<string>
     */
    public function shoppingEnabledCampaignIds(): array
    {
        if ($this->channelKey() !== 'shopping') {
            return [];
        }
        $ids = [];
        foreach ($this->buildRawGridBaseQuery()->get() as $row) {
            $arr = self::rawGridRowToArray($row);
            if (strtoupper(trim((string) ($arr['campaign_status'] ?? ''))) !== 'ENABLED') {
                continue;
            }
            $cid = preg_replace('/\D+/', '', (string) ($arr['campaign_id'] ?? '')) ?? '';
            if ($cid !== '') {
                $ids[$cid] = true;
            }
        }

        return array_map('strval', array_keys($ids));
    }

    private function rememberShoppingPush(string $field, string $campaignId, float $value): void
    {
        if ($this->channelKey() !== 'shopping') {
            return;
        }
        app(GoogleShoppingLiveBidBgtService::class)->recordPush('shopping', $campaignId, $field, true, $value);
    }

    /**
     * Grid rows enriched with SBGT/SBID — keyed by campaign_id string.
     *
     * @param  list<string>  $campaignIds
     * @return array<string, array<string, mixed>>
     */
    protected function enrichedRowsForCampaignIds(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        $query = $this->buildRawGridBaseQuery();
        $query->whereIn('g.campaign_id', $campaignIds);
        $rawRule = GoogleShoppingCampaignsRawRule::resolvedRule();
        $invResolver = $this->buildInventoryResolver();
        $bgtResolver = $this->shoppingBgtMetricsResolver();
        $byId = [];

        foreach ($query->get() as $row) {
            $arr = $this->hydrateRawGridRow($row, $rawRule, $invResolver, $bgtResolver);
            $byId[(string) ($arr['campaign_id'] ?? '')] = $arr;
        }

        return $byId;
    }

    /**
     * Latest non-empty budget_id per campaign — used when the grid's latest daily
     * row omitted the column or stored an empty id (common on PARENT rows).
     *
     * @param  list<string>  $campaignIds
     * @return array<string, string>
     */
    private function latestBudgetIdsByCampaignId(array $campaignIds): array
    {
        $campaignIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (string) $id, $campaignIds),
            static fn (string $id) => $id !== ''
        )));
        if ($campaignIds === []) {
            return [];
        }

        $rows = DB::table('google_ads_campaigns')
            ->select('campaign_id', 'budget_id')
            ->whereIn('campaign_id', $campaignIds)
            ->whereNotNull('budget_id')
            ->where('budget_id', '!=', '')
            ->orderByDesc('date')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $cid = (string) ($row->campaign_id ?? '');
            if ($cid === '' || isset($out[$cid])) {
                continue;
            }
            $budgetId = $this->normalizeBudgetId($row->budget_id ?? null);
            if ($budgetId !== '') {
                $out[$cid] = $budgetId;
            }
        }

        return $out;
    }

    private function normalizeBudgetId(mixed $budgetId): string
    {
        return preg_replace('/\D/', '', (string) $budgetId) ?? '';
    }

    /**
     * Push SBID for one campaign. Shopping pages update ad groups + product groups;
     * SERP/YouTube subclasses override to strategy-aware Search/Video bid updates.
     *
     * @param  array<string, mixed>  $row
     * @return string Optional note appended to push log lines
     */
    protected function pushSbidToGoogleAds(GoogleAdsSbidService $sbidService, string $customerId, string $campaignId, float $sbid, array $row = []): string
    {
        $sbidService->updateCampaignSbids($customerId, $campaignId, $sbid, true);

        return '';
    }

    /**
     * Label shown in push API responses / UI toasts.
     */
    protected function pushSbgtCommandLabel(): string
    {
        return 'push-sbgt';
    }

    protected function pushSbidCommandLabel(): string
    {
        return 'push-sbid';
    }

    /**
     * Paginated JSON for Tabulator — one row per campaign_id in the date window.
     * Spend = SUM(metrics_cost_micros) / 1e6 for the 30d window; l7/l2/l1_spend = trailing N inclusive days to max date.
     * Adds utilized-style metrics (CPC L30/L7/L2/L1, L30 sales, ACOS, UB%, BGT, SBGT, SBID) using the same formulas as
     * `/google/shopping/utilized`, anchored to this page’s max-date window (not calendar “yesterday”).
     * Other columns from the latest `date` row in the 30d window.
     * Column "spend" (dollars) is ordered immediately after campaign_name for the grid.
     */
    public function data(Request $request)
    {
        $perPage = (int) $request->input('size', 100);
        $perPage = max(10, min(1000, $perPage));
        $page = max(1, (int) $request->input('page', 1));
        $verifyId = $request->boolean('filter_verify_id');
        // ID filter (Matched / No ID / Mismatch): checks every filtered campaign against Merchant Center, so it needs the PHP path.
        $idStatus = $this->normalizeIdStatusFilter($request->input('filter_id_status'));
        // Verification filter (attached Item IDs count vs SKU count): all / match (green) / mismatch (red).
        $verFilter = $this->normalizeVerificationFilter($request->input('filter_verification'));
        $needsIdCheck = $idStatus !== 'all' || $verFilter !== 'all';

        $query = $this->buildRawGridBaseQuery();
        $this->applyRawGridDataFilters($query, $request);
        $this->applyRawGridSort($query, $request);

        $rawRule = GoogleShoppingCampaignsRawRule::resolvedRule();

        // One filtered result set (~hundreds of campaigns) — paginate + badge
        // totals in PHP so we do not run the aggregation three times
        // (COUNT, page SELECT, summary wrap).
        $collection = $this->rememberRawGridRows($request, $query);

        $invResolver = $this->buildInventoryResolver();
        $bgtResolver = $this->shoppingBgtMetricsResolver();
        $invFilter = $this->normalizeInvFilter($request->input('filter_inv'));
        $collection = $this->filterRawGridRowsByInventory($collection, $invFilter, $invResolver);
        $collection = $this->filterRawGridRowsByPrice($collection, $request, $bgtResolver);
        $collection = $this->filterRawGridRowsByNegativeTargets($collection, $request->input('filter_ntarget'));
        $fixNeeded = $this->channelKey() === 'shopping'
            ? $this->fixNeededAllCampaigns($invResolver)
            : ['ids' => [], 'total' => 0, 'error' => ''];
        if ($request->boolean('filter_fix') && $this->channelKey() === 'shopping') {
            // "FIX" badge click: only the campaigns that currently show the Fix button.
            $fixSet = array_flip($fixNeeded['ids']);
            $collection = $collection->filter(static fn ($row) => isset($fixSet[(string) (is_object($row) ? ($row->campaign_id ?? '') : ($row['campaign_id'] ?? ''))]))->values();
        }
        $summary = $this->summarizeRawGridRows($collection);
        $summary = array_merge($summary, $this->rawGridL30TotalsAllStatuses($request, $collection, $invFilter, $invResolver));
        $summary = array_merge($summary, $this->rawGridUtilizationAllCampaigns($request, $collection, $invFilter, $invResolver));
        // BGT "Sum" card: SBGT distribution over ALL campaigns (every status), independent of filters / page.
        $summary['sbgt_dist'] = $this->sbgtDistributionAllCampaigns($rawRule, $invResolver, $bgtResolver);
        // Same campaigns bucketed by ACOS, so the Sum card can recompute live from the ACOS bands being edited.
        $summary['sbgt_acos_dist'] = $this->sbgtAcosDistributionAllCampaigns($rawRule, $invResolver, $bgtResolver);
        // "Ads Needed": SKUs with inventory whose ad is not running (all enabled campaigns).
        $adsNeeded = $this->channelKey() === 'shopping'
            ? $this->adsNeededAllCampaigns($invResolver)
            : ['map' => [], 'total' => 0, 'campaigns' => 0, 'pause_total' => 0, 'no_id_total' => 0, 'error' => ''];
        $summary['ads_needed_total'] = $adsNeeded['total'];
        $summary['ads_needed_campaigns'] = $adsNeeded['campaigns'];
        $summary['ads_needed_pause'] = $adsNeeded['pause_total'];
        $summary['ads_needed_no_id'] = $adsNeeded['no_id_total'];
        $summary['ads_needed_error'] = $adsNeeded['error'];
        $summary['fix_needed_total'] = $fixNeeded['total'];
        $summary['fix_needed_error'] = $fixNeeded['error'];

        [$sortField, $sortDir] = $this->rawGridSortFromRequest($request);
        $needsPhpSort = $sortField !== null && $this->isPhpSortField($sortField);

        // Verify ID or PHP-only sort fields (INV / Dil / Views / Price / Bgt Views / BGT PRC / SBGT)
        // need hydrated rows before paginate.
        if ($verifyId || $needsPhpSort || $needsIdCheck) {
            if ($verifyId || $needsIdCheck) {
                @ini_set('max_execution_time', '120');
                set_time_limit(120);
            }
            $enriched = $collection->map(function ($row) use ($rawRule, $invResolver, $bgtResolver) {
                return $this->hydrateRawGridRow($row, $rawRule, $invResolver, $bgtResolver);
            });
            if ($verifyId) {
                $enriched = $enriched->filter(static function (array $arr) {
                    return (int) ($arr['inventory'] ?? 0) > 0;
                })->values();
                $summary['filtered_row_count'] = $enriched->count();
            }
            if ($needsIdCheck) {
                $this->attachMerchantIdVerification($enriched);
                $enriched = $enriched->filter(static function (array $arr) use ($idStatus, $verFilter) {
                    if ($idStatus !== 'all') {
                        $st = $arr['id_status'] ?? '';
                        if ($st !== $idStatus && ! ($idStatus === 'mismatch' && $st === 'error')) {
                            return false;
                        }
                    }
                    if ($verFilter !== 'all' && ($arr['ver_status'] ?? '') !== $verFilter) {
                        return false;
                    }

                    return true;
                })->values();
                $summary['filtered_row_count'] = $enriched->count();
            }
            if ($needsPhpSort) {
                $enriched = $this->sortHydratedRawGridRows($enriched, $sortField, $sortDir);
            }

            $total = $enriched->count();
            $lastPage = max(1, (int) ceil($total / $perPage));
            $page = min($page, $lastPage);
            $pageRows = $enriched->slice(($page - 1) * $perPage, $perPage)->values();

            $pageCampaignIds = $pageRows
                ->pluck('campaign_id')
                ->filter(fn ($v) => $v !== null && $v !== '')
                ->map(fn ($v) => (string) $v)
                ->unique()
                ->values()
                ->all();
            $prevSbgtMap = $this->previousSbgtMap($pageCampaignIds);
            $negCounts = $this->negativeTargetCountsForPage($pageCampaignIds);
            $liveStates = $this->liveBidBgtStatesForPage($pageCampaignIds);
            foreach ($pageRows as $i => $arr) {
                $this->attachSbgtTrend($arr, $prevSbgtMap);
                $this->attachNegativeTargetCount($arr, $negCounts);
                $arr = $this->mergeLiveBidBgtColumns($arr, $liveStates);
                if (! $verifyId && ! $needsIdCheck) {
                    $arr['id_mismatch'] = false;
                    $arr['id_status'] = '';
                    $arr['id_alert_title'] = '';
                    $arr['ver_attached'] = null;
                    $arr['ver_total'] = null;
                    $arr['ver_status'] = '';
                    $arr['ver_reason'] = '';
                }
                $pageRows[$i] = $arr;
            }
            if ($verifyId && ! $needsIdCheck) {
                $this->attachMerchantIdVerification($pageRows);
            }

            $rows = $pageRows->map(function (array $arr) use ($adsNeeded) {
                $this->attachAdsNeeded($arr, $adsNeeded);

                return self::prepareRawRowForTabulator($arr);
            })->values();

            return response()->json([
                'last_page' => $lastPage,
                'last_row' => $total,
                'data' => $rows,
                'total' => $total,
                'summary' => $summary,
            ]);
        }

        $total = $collection->count();
        $lastPage = max(1, (int) ceil($total / $perPage) ?: 1);
        $page = min($page, $lastPage);
        $pageCollection = $collection->slice(($page - 1) * $perPage, $perPage)->values();

        $pageCampaignIds = $pageCollection
            ->pluck('campaign_id')
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values()
            ->all();
        $prevSbgtMap = $this->previousSbgtMap($pageCampaignIds);
        $negCounts = $this->negativeTargetCountsForPage($pageCampaignIds);
        $liveStates = $this->liveBidBgtStatesForPage($pageCampaignIds);

        $rows = $pageCollection->map(function ($row) use ($rawRule, $prevSbgtMap, $negCounts, $invResolver, $bgtResolver, $liveStates, $adsNeeded) {
            $arr = $this->hydrateRawGridRow($row, $rawRule, $invResolver, $bgtResolver);
            $this->attachSbgtTrend($arr, $prevSbgtMap);
            $this->attachNegativeTargetCount($arr, $negCounts);
            $arr = $this->mergeLiveBidBgtColumns($arr, $liveStates);
            $arr['id_mismatch'] = false;
            $arr['id_status'] = '';
            $arr['id_alert_title'] = '';
            $arr['ver_attached'] = null;
            $arr['ver_total'] = null;
            $arr['ver_status'] = '';
            $arr['ver_reason'] = '';
            $this->attachAdsNeeded($arr, $adsNeeded);

            return self::prepareRawRowForTabulator($arr);
        })->values();

        return response()->json([
            'last_page' => $lastPage,
            'last_row' => $total,
            'data' => $rows,
            'total' => $total,
            'summary' => $summary,
        ]);
    }

    /**
     * Campaigns that show the Fix button: Item IDs attached on Google Ads differ from the SKUs with stock
     * (Verification mismatch, or Item ID mismatch / missing), and the campaign has inventory (INV > 0).
     * Covers every ENABLED and PAUSED campaign; cached 5 minutes (cleared whenever Item IDs change).
     *
     * @return array{ids: list<string>, total: int, error: string}
     */
    public function fixNeededAllCampaigns(?\Closure $invResolver = null, bool $fresh = false): array
    {
        $empty = ['ids' => [], 'total' => 0, 'error' => ''];
        if ((string) config('services.google_ads.login_customer_id') === '') {
            return ['error' => 'Google Ads customer ID is not configured.'] + $empty;
        }
        $key = 'gads_fix_needed_v1:'.$this->channelKey().':'.((int) Cache::get('gads_shopping_item_ids_gen', 0));
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 300, function () use ($invResolver, $empty) {
            $maxDate = DB::table('google_ads_campaigns')->where('advertising_channel_type', 'SHOPPING')->max('date');
            if (! $maxDate) {
                return $empty;
            }
            $q = DB::table('google_ads_campaigns')
                ->where('advertising_channel_type', 'SHOPPING')
                ->where('date', '>=', Carbon::parse($maxDate)->subDays(10)->toDateString());
            $this->applyCampaignNameScope($q);
            $rows = [];
            $seen = [];
            foreach ($q->orderByDesc('date')->get(['campaign_id', 'campaign_name', 'campaign_status']) as $r) {
                $cid = (string) $r->campaign_id;
                if (isset($seen[$cid])) {
                    continue;
                }
                $seen[$cid] = true;
                if (in_array(strtoupper(trim((string) $r->campaign_status)), ['ENABLED', 'PAUSED'], true)) {
                    $rows[] = ['campaign_id' => $cid, 'campaign_name' => (string) $r->campaign_name];
                }
            }
            if ($rows === []) {
                return $empty;
            }
            $invResolver ??= $this->buildInventoryResolver();
            $coll = collect($rows);
            try {
                $this->attachMerchantIdVerification($coll);
            } catch (\Throwable $e) {
                report($e);

                return ['error' => GoogleAdsSbidService::briefGoogleError($e)] + $empty;
            }
            $ids = [];
            $errors = 0;
            foreach ($coll as $arr) {
                $ids_ = (string) ($arr['id_status'] ?? '');
                if ($ids_ === 'error') {
                    $errors++;

                    continue;
                }
                $needs = ($arr['ver_status'] ?? '') === 'mismatch' || $ids_ === 'mismatch' || $ids_ === 'missing';
                $inv = $invResolver((string) ($arr['campaign_name'] ?? ''));
                if ($needs && $inv !== null && (int) $inv > 0) {
                    $ids[] = (string) $arr['campaign_id'];
                }
            }

            return ['ids' => $ids, 'total' => count($ids), 'error' => $errors === $coll->count() ? 'Could not read live Item IDs from Google Ads.' : ''];
        });
    }

    /**
     * "Ads Needed": for every ENABLED Shopping campaign, the SKUs that have inventory (INV > 0) but whose Item ID is not
     * running in the ad (not in the listing groups, paused or excluded), and the SKUs without inventory that still run.
     * Cached for 5 minutes (cleared whenever Item IDs change).
     *
     * @return array{map: array<string, array{n: int, skus: list<string>, no_id: list<string>, pause: list<string>, enable_items: list<string>, remove_items: list<string>, name: string}>, total: int, campaigns: int, pause_total: int, no_id_total: int, error: string}
     */
    public function adsNeededAllCampaigns(?\Closure $invResolver = null, bool $fresh = false): array
    {
        $customerId = (string) config('services.google_ads.login_customer_id');
        $empty = ['map' => [], 'total' => 0, 'campaigns' => 0, 'pause_total' => 0, 'no_id_total' => 0, 'error' => ''];
        if ($customerId === '') {
            return ['error' => 'Google Ads customer ID is not configured.'] + $empty;
        }
        $key = 'gads_ads_needed_v1:'.$this->channelKey().':'.((int) Cache::get('gads_shopping_item_ids_gen', 0));
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 300, function () use ($customerId, $invResolver, $empty) {
            $maxDate = DB::table('google_ads_campaigns')->where('advertising_channel_type', 'SHOPPING')->max('date');
            if (! $maxDate) {
                return $empty;
            }
            $q = DB::table('google_ads_campaigns')
                ->where('advertising_channel_type', 'SHOPPING')
                ->where('date', '>=', Carbon::parse($maxDate)->subDays(10)->toDateString());
            $this->applyCampaignNameScope($q);
            $names = [];
            $seen = [];
            foreach ($q->orderByDesc('date')->get(['campaign_id', 'campaign_name', 'campaign_status']) as $r) {
                $cid = (string) $r->campaign_id;
                if (isset($seen[$cid])) {
                    continue;
                }
                $seen[$cid] = true;
                if (strtoupper(trim((string) $r->campaign_status)) === 'ENABLED') {
                    $names[$cid] = (string) $r->campaign_name;
                }
            }
            if ($names === []) {
                return $empty;
            }

            $invResolver ??= $this->buildInventoryResolver();
            try {
                /** @var GoogleAdsSbidService $sbid */
                $sbid = app(GoogleAdsSbidService::class);
                $nodesBy = $sbid->fetchListingGroupNodesByCampaign($customerId, array_keys($names));
            } catch (\Throwable $e) {
                report($e);

                return ['error' => GoogleAdsSbidService::briefGoogleError($e)] + $empty;
            }

            $skusByCampaign = [];
            $allSkus = [];
            foreach ($names as $cid => $name) {
                $skusByCampaign[$cid] = $this->childSkusForCampaignName($name);
                foreach ($skusByCampaign[$cid] as $s) {
                    $allSkus[$s] = true;
                }
            }
            $itemBySku = $this->resolveMerchantCenterItemIds(array_keys($allSkus));

            $map = [];
            $total = $campaigns = $pauseTotal = $noIdTotal = 0;
            foreach ($names as $cid => $name) {
                $states = GoogleAdsSbidService::itemStatesFromNodes($nodesBy[$cid] ?? []);
                $needed = $noId = $pause = $enableItems = $removeItems = [];
                foreach ($skusByCampaign[$cid] as $sku) {
                    $inv = $invResolver($sku);
                    if ($inv === null) {
                        continue;
                    }
                    $item = (string) ($itemBySku[$sku] ?? '');
                    $state = $item === '' ? '' : ($states[strtolower($item)] ?? 'NOT_ATTACHED');
                    if ((float) $inv > 0) {
                        if ($item === '') {
                            $noId[] = $sku;
                        } elseif ($state !== 'ENABLED') {
                            $needed[] = $sku;
                            $enableItems[] = $item;
                        }
                    } elseif ($state === 'ENABLED') {
                        $pause[] = $sku;
                        $removeItems[] = $item;
                    }
                }
                if ($needed !== [] || $pause !== [] || $noId !== []) {
                    $map[$cid] = [
                        'n' => count($needed), 'skus' => $needed, 'no_id' => $noId, 'pause' => $pause,
                        'enable_items' => $enableItems, 'remove_items' => $removeItems, 'name' => $name,
                    ];
                }
                if ($needed !== []) {
                    $campaigns++;
                    $total += count($needed);
                }
                $pauseTotal += count($pause);
                $noIdTotal += count($noId);
            }

            return ['map' => $map, 'total' => $total, 'campaigns' => $campaigns, 'pause_total' => $pauseTotal, 'no_id_total' => $noIdTotal, 'error' => ''];
        });
    }

    /**
     * Adds the "Ads Needed" count and tooltip to a grid row.
     *
     * @param  array<string, mixed>  $arr
     * @param  array{map: array<string, mixed>, error: string}  $needed
     */
    private function attachAdsNeeded(array &$arr, array $needed): void
    {
        if ($this->channelKey() !== 'shopping') {
            return; // SERP / YouTube grids share this controller but have no Ads Needed column
        }
        $arr['ads_needed'] = null;
        $arr['ads_needed_title'] = '';
        if (($needed['error'] ?? '') !== '') {
            $arr['ads_needed_title'] = 'Could not read live ads: '.$needed['error'];

            return;
        }
        if (strtoupper((string) ($arr['campaign_status'] ?? '')) !== 'ENABLED') {
            return; // only running campaigns are checked
        }
        $cid = (string) ($arr['campaign_id'] ?? '');
        $m = $needed['map'][$cid] ?? null;
        $arr['ads_needed'] = $m ? (int) $m['n'] : 0;
        if ($m) {
            $parts = [];
            if ($m['skus'] !== []) {
                $parts[] = 'Has INV but no running ad: '.implode(', ', array_slice($m['skus'], 0, 8)).(count($m['skus']) > 8 ? ', …' : '');
            }
            if ($m['no_id'] !== []) {
                $parts[] = 'No Merchant Center Item ID (cannot be created): '.implode(', ', array_slice($m['no_id'], 0, 5));
            }
            if ($m['pause'] !== []) {
                $parts[] = 'INV 0 but still running (will be paused): '.implode(', ', array_slice($m['pause'], 0, 5));
            }
            $arr['ads_needed_title'] = implode(' | ', $parts);
        }
    }

    /**
     * Last completed USA (Pacific) calendar day to use for charts / L30 snapshots.
     * The current US day is incomplete, so we never anchor on "today" Pacific —
     * same convention as all-marketplace-master (preceding completed day). Also
     * never past the latest date present in google_ads_campaigns.
     */
    public function badgeChartCompletedEndDate(): Carbon
    {
        $tz = 'America/Los_Angeles';
        $usYesterday = Carbon::now($tz)->subDay()->startOfDay();

        $maxDateStr = DB::table('google_ads_campaigns')->whereNotNull('date')->max('date');
        if ($maxDateStr !== null && $maxDateStr !== '') {
            $maxData = Carbon::parse((string) $maxDateStr, $tz)->startOfDay();
            if ($maxData->lt($usYesterday)) {
                return $maxData;
            }
        }

        return $usYesterday;
    }

    /**
     * Upsert the preceding completed US day's SBGT + rolling L30 badge metrics for
     * every campaign in this channel's scope. Gated once per calendar day per channel
     * (cache lock) so paging/filtering the grid doesn't recompute on every request.
     *
     * @param  array{sbgt: array<string, float|int>, sbid: array<string, float>}  $rawRule
     */
    protected function snapshotSbgtForToday(array $rawRule): void
    {
        $anchor = $this->badgeChartCompletedEndDate()->toDateString();
        $channel = $this->channelKey();
        // v3 lock — snapshots are keyed to the completed US day (not incomplete "today").
        $lockKey = "gads_sbgt_snap_v3:{$channel}:{$anchor}";

        // Cache::add is atomic — only the first caller today for this channel proceeds.
        if (! Cache::add($lockKey, 1, now()->addDay())) {
            return;
        }

        $this->persistBadgeL30SnapshotsForDate($anchor, true, $rawRule);
    }

    /**
     * Persist rolling L30 badge metrics (+ SBGT / ACOS) for every campaign in this
     * channel as of $endYmd. Used by the daily grid snapshot, the artisan cron, and
     * on-demand chart backfill. Returns number of campaign rows upserted.
     *
     * @param  array{sbgt: array<string, float|int>, sbid: array<string, float>}|null  $rawRule
     */
    public function persistBadgeL30SnapshotsForDate(string $endYmd, bool $force = false, ?array $rawRule = null): int
    {
        $endYmd = Carbon::parse($endYmd)->toDateString();
        $channel = $this->channelKey();
        $rawRule = $rawRule ?? GoogleShoppingCampaignsRawRule::resolvedRule();
        $now = now();
        $count = 0;
        $invResolver = $this->buildInventoryResolver();
        $bgtResolver = $this->shoppingBgtMetricsResolver();

        foreach ($this->buildRawGridBaseQuery($endYmd)->get() as $row) {
            $arr = $this->hydrateRawGridRow($row, $rawRule, $invResolver, $bgtResolver);

            $cid = (string) ($arr['campaign_id'] ?? '');
            if ($cid === '') {
                continue;
            }

            DB::table('google_ads_sbgt_snapshots')->updateOrInsert(
                ['campaign_id' => $cid, 'snapshot_date' => $endYmd],
                [
                    'channel' => $channel,
                    'sbgt' => (float) ($arr['sbgt'] ?? 0),
                    'acos' => round((float) ($arr['acos_l30'] ?? 0), 2),
                    'spend_l30' => round((float) ($arr['spend'] ?? 0), 2),
                    'sales_l30' => round((float) ($arr['ad_sales_L30'] ?? 0), 2),
                    'clicks_l30' => round((float) ($arr['metrics_clicks'] ?? 0), 2),
                    'sold_l30' => round((float) ($arr['ad_sold_L30'] ?? 0), 2),
                    'bgt' => round((float) ($arr['bgt'] ?? 0), 2),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Best-effort: persist the preceding completed US day's L30 badge snapshot if the
     * daily lock is free. Full historical backfill:
     * `google:save-badge-l30-snapshots --backfill=N`.
     */
    protected function ensureTodayBadgeL30Snapshot(): void
    {
        if (! Schema::hasTable('google_ads_sbgt_snapshots')
            || ! Schema::hasColumn('google_ads_sbgt_snapshots', 'spend_l30')) {
            return;
        }

        try {
            $this->snapshotSbgtForToday(GoogleShoppingCampaignsRawRule::resolvedRule());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Fast account-level L30 totals ending on $endYmd (for chart gaps before snapshots exist).
     *
     * @param  list<string>  $campaignIds
     * @return array{spend: float, sales: float, clicks: float, sold: float, bgt: float}
     */
    protected function computeL30BadgeTotalsForDate(string $endYmd, array $campaignIds = []): array
    {
        $end = Carbon::parse($endYmd)->startOfDay();
        $start = $end->copy()->subDays(29);

        $query = DB::table('google_ads_campaigns')
            ->whereNotNull('date')
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
        $this->applyCampaignNameScope($query);
        if ($campaignIds !== []) {
            $query->whereIn('campaign_id', $campaignIds);
        }

        $endStr = $end->format('Y-m-d');
        $row = $query->selectRaw("
                COALESCE(SUM(metrics_cost_micros), 0) / 1000000.0 AS spend,
                COALESCE(SUM(ga4_actual_revenue), 0) AS actual_sales,
                COALESCE(SUM(ga4_ad_sales), 0) AS ad_sales,
                COALESCE(SUM(metrics_clicks), 0) AS clicks,
                COALESCE(SUM(ga4_actual_sold_units), 0) AS actual_sold,
                COALESCE(SUM(ga4_sold_units), 0) AS ads_sold,
                COALESCE(SUM(CASE WHEN date = '{$endStr}' THEN COALESCE(budget_amount_micros, 0) ELSE 0 END), 0) / 1000000.0 AS bgt
            ")
            ->first();

        $actual = (float) ($row->actual_sales ?? 0);
        $ads = (float) ($row->ad_sales ?? 0);
        $sales = static::resolveSalesL30Value($actual, $ads);
        $sold = static::resolveSoldL30Value(
            (float) ($row->actual_sold ?? 0),
            (float) ($row->ads_sold ?? 0)
        );

        return [
            'spend' => round((float) ($row->spend ?? 0), 2),
            'sales' => round($sales, 2),
            'clicks' => (float) ($row->clicks ?? 0),
            'sold' => (float) $sold,
            'bgt' => round((float) ($row->bgt ?? 0), 2),
        ];
    }

    /**
     * Most recent SBGT snapshot strictly before today, per campaign id.
     *
     * @param  list<string>  $campaignIds
     * @return array<string, array{sbgt: float, date: string}>
     */
    protected function previousSbgtMap(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        $today = Carbon::now(config('app.timezone'))->toDateString();

        $latest = DB::table('google_ads_sbgt_snapshots')
            ->select('campaign_id', DB::raw('MAX(snapshot_date) as md'))
            ->whereIn('campaign_id', $campaignIds)
            ->where('snapshot_date', '<', $today)
            ->groupBy('campaign_id');

        $rows = DB::table('google_ads_sbgt_snapshots as s')
            ->joinSub($latest, 'l', function ($join) {
                $join->on('s.campaign_id', '=', 'l.campaign_id')
                    ->on('s.snapshot_date', '=', 'l.md');
            })
            ->get(['s.campaign_id', 's.sbgt', 's.snapshot_date']);

        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r->campaign_id] = [
                'sbgt' => (float) $r->sbgt,
                'date' => (string) $r->snapshot_date,
            ];
        }

        return $map;
    }

    /**
     * Attach the previous-day SBGT + a trend token ('up' | 'down' | 'flat' | 'na') to a
     * grid row so the view can render the coloured dot without any client-side math.
     *
     * @param  array<string, mixed>  $arr
     * @param  array<string, array{sbgt: float, date: string}>  $prevMap
     */
    protected function attachSbgtTrend(array &$arr, array $prevMap): void
    {
        $cid = (string) ($arr['campaign_id'] ?? '');
        $prev = $prevMap[$cid] ?? null;
        $current = (float) ($arr['sbgt'] ?? 0);

        if ($prev === null) {
            $arr['sbgt_prev'] = null;
            $arr['sbgt_prev_date'] = null;
            $arr['sbgt_trend'] = 'na';

            return;
        }

        $prevSbgt = (float) $prev['sbgt'];
        $arr['sbgt_prev'] = $prevSbgt;
        $arr['sbgt_prev_date'] = $prev['date'];

        if ($current > $prevSbgt) {
            $arr['sbgt_trend'] = 'up';
        } elseif ($current < $prevSbgt) {
            $arr['sbgt_trend'] = 'down';
        } else {
            $arr['sbgt_trend'] = 'flat';
        }
    }

    /**
     * Daily SBGT history for a single campaign (for the SBGT dot's popup).
     * GET ?campaign_id=123&days=30 — newest first.
     */
    public function sbgtHistory(Request $request): JsonResponse
    {
        $cid = preg_replace('/\D/', '', (string) $request->input('campaign_id', ''));
        if ($cid === '' || strlen($cid) > 32) {
            return response()->json(['ok' => false, 'data' => [], 'message' => 'Invalid campaign_id.'], 422);
        }

        $days = (int) $request->input('days', 30);
        $days = max(1, min(180, $days));
        $start = Carbon::now(config('app.timezone'))->subDays($days - 1)->toDateString();

        $rows = DB::table('google_ads_sbgt_snapshots')
            ->where('campaign_id', $cid)
            ->where('snapshot_date', '>=', $start)
            ->orderBy('snapshot_date')
            ->get(['snapshot_date', 'sbgt', 'acos']);

        $out = [];
        $prev = null;
        foreach ($rows as $r) {
            $sbgt = (float) $r->sbgt;
            $trend = 'na';
            if ($prev !== null) {
                $trend = $sbgt > $prev ? 'up' : ($sbgt < $prev ? 'down' : 'flat');
            }
            $out[] = [
                'date' => (string) $r->snapshot_date,
                'sbgt' => $sbgt,
                'acos' => $r->acos !== null ? (float) $r->acos : null,
                'trend' => $trend,
            ];
            $prev = $sbgt;
        }

        // Newest first for display.
        $out = array_reverse($out);

        return response()->json([
            'ok' => true,
            'campaign_id' => $cid,
            'days' => $days,
            'data' => $out,
        ]);
    }

    /**
     * Stored Google Ads negative keywords for one campaign — the campaign-level negatives
     * plus every ad group-level negative that belongs to that campaign. Populated by
     * `app:fetch-google-ads-negative-keywords`. Read-only; newest keyword text first
     * within each level. GET ?campaign_id=123
     */
    public function negativeKeywords(Request $request): JsonResponse
    {
        $cid = preg_replace('/\D/', '', (string) $request->input('campaign_id', ''));
        if ($cid === '' || strlen($cid) > 32) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid campaign_id.',
                'data' => [],
            ], 422);
        }

        $rows = GoogleAdsNegativeKeyword::query()
            ->where('campaign_id', $cid)
            ->whereIn('level', [
                GoogleAdsNegativeKeyword::LEVEL_CAMPAIGN,
                GoogleAdsNegativeKeyword::LEVEL_AD_GROUP,
            ])
            ->orderByRaw("FIELD(level, ?, ?)", [
                GoogleAdsNegativeKeyword::LEVEL_CAMPAIGN,
                GoogleAdsNegativeKeyword::LEVEL_AD_GROUP,
            ])
            ->orderBy('ad_group_name')
            ->orderBy('keyword_text')
            ->get(['level', 'campaign_name', 'ad_group_name', 'keyword_text', 'match_type', 'status']);

        $campaignName = optional($rows->first(fn ($r) => ! empty($r->campaign_name)))->campaign_name;

        $data = $rows->map(fn ($r) => [
            'level' => $r->level,
            'ad_group_name' => $r->ad_group_name,
            'keyword_text' => $r->keyword_text,
            'match_type' => $r->match_type,
            'status' => $r->status,
        ])->values();

        return response()->json([
            'ok' => true,
            'campaign_id' => $cid,
            'campaign_name' => $campaignName,
            'counts' => [
                'campaign' => $rows->where('level', GoogleAdsNegativeKeyword::LEVEL_CAMPAIGN)->count(),
                'ad_group' => $rows->where('level', GoogleAdsNegativeKeyword::LEVEL_AD_GROUP)->count(),
                'total' => $rows->count(),
            ],
            'data' => $data,
        ]);
    }

    /**
     * Add campaign-level negative keywords to Google Ads (Negative keywords modal and Keyword Planner modal).
     * Request: campaign_id, keywords (array or comma / new-line text), match_type (PHRASE | EXACT | BROAD).
     */
    public function addNegativeKeywords(Request $request): JsonResponse
    {
        $cid = preg_replace('/\D/', '', (string) $request->input('campaign_id', ''));
        if ($cid === '' || strlen($cid) > 32) {
            return response()->json(['ok' => false, 'message' => 'Invalid campaign_id.'], 422);
        }
        $raw = $request->input('keywords', []);
        if (is_string($raw)) {
            $raw = preg_split('/[\r\n,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        $keywords = [];
        foreach ((array) $raw as $k) {
            $t = preg_replace('/\s+/', ' ', trim((string) $k));
            if ($t !== '' && ! preg_match('/[@%!{}<>=;\\\\]/', $t)) { // characters Google rejects in keyword text
                $keywords[mb_strtolower($t)] = mb_substr($t, 0, 80);
            }
        }
        $keywords = array_slice(array_values($keywords), 0, 200);
        if ($keywords === []) {
            return response()->json(['ok' => false, 'message' => 'Enter at least one valid keyword.'], 422);
        }
        $match = strtoupper(trim((string) $request->input('match_type', 'PHRASE')));
        if (! in_array($match, ['PHRASE', 'EXACT', 'BROAD'], true)) {
            $match = 'PHRASE';
        }
        $customerId = (string) config('services.google_ads.login_customer_id');
        if ($customerId === '') {
            return response()->json(['ok' => false, 'message' => 'Google Ads customer ID is not configured.'], 500);
        }
        $q = DB::table('google_ads_campaigns')->where('campaign_id', $cid);
        $this->applyCampaignNameScope($q);
        $latest = $q->orderByDesc('date')->first(['campaign_name']);
        if (! $latest) {
            return response()->json(['ok' => false, 'message' => 'Campaign not found in this grid.'], 404);
        }

        @ini_set('max_execution_time', '60');
        try {
            /** @var GoogleAdsSbidService $svc */
            $svc = app(GoogleAdsSbidService::class);
            $res = $svc->pushCampaignNegativeKeywords($customerId, $cid, $keywords, $match);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => GoogleAdsSbidService::briefGoogleError($e)], 422);
        }

        // Mirror the new negatives locally so the list and N Target update now (the sync job keys on the resource name).
        $rows = [];
        foreach ($res['created'] ?? [] as $c) {
            $rows[] = [
                'level' => GoogleAdsNegativeKeyword::LEVEL_CAMPAIGN,
                'customer_id' => $customerId,
                'campaign_id' => $cid,
                'campaign_name' => (string) $latest->campaign_name,
                'criterion_id' => (string) (str_contains($c['resource'], '~') ? substr($c['resource'], strrpos($c['resource'], '~') + 1) : ''),
                'criterion_resource_name' => $c['resource'],
                'keyword_text' => $c['text'],
                'match_type' => $match,
                'criterion_type' => 'KEYWORD',
                'status' => 'ENABLED',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($rows !== []) {
            try {
                GoogleAdsNegativeKeyword::upsert($rows, ['criterion_resource_name'], ['keyword_text', 'match_type', 'status', 'updated_at']);
            } catch (\Throwable $e) {
                report($e);
            }
            $this->bumpRawGridRowsCache();
        }

        $ok = ((int) $res['added']) > 0;
        $message = $ok
            ? $res['added'].' negative keyword(s) added to Google Ads'.($res['failed'] ? ', '.$res['failed'].' failed (already exist or rejected)' : '').'.'
            : ($res['errors'][0] ?? 'No negative keyword was added (they may already exist).');

        return response()->json([
            'ok' => $ok,
            'message' => $message,
            'added' => (int) $res['added'],
            'failed' => (int) $res['failed'],
            'errors' => $res['errors'] ?? [],
        ], $ok ? 200 : 422);
    }

    /**
     * Audit-history channel key for the "Revised" negative-keywords review page. Kept
     * separate from {@see channelKey()} so its red/green status + history don't mix with
     * the Shopping campaign audit.
     */
    protected function revisedChannelKey(): string
    {
        return 'negatives';
    }

    /**
     * "Revised" page — one row per campaign with its stored negative-keyword count and an
     * amazon-ads/audit-style red/green status (green = audited within 30 days) plus history.
     */
    public function revisedPage()
    {
        return view('campaign.google-negatives-revised');
    }

    /**
     * Rows for the Revised page: campaign name, negative-keyword count, red/green audit dot,
     * and audit history. Ordered never-audited / oldest-audited first (same shape as
     * {@see auditPageData()}).
     */
    public function revisedPageData(Request $request): JsonResponse
    {
        // Negative-keyword counts per campaign (campaign-level + ad group-level).
        $negCounts = DB::table('google_ads_negative_keywords')
            ->whereIn('level', [
                GoogleAdsNegativeKeyword::LEVEL_CAMPAIGN,
                GoogleAdsNegativeKeyword::LEVEL_AD_GROUP,
            ])
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->selectRaw('campaign_id, COUNT(*) as c')
            ->groupBy('campaign_id')
            ->pluck('c', 'campaign_id');

        // All campaigns — latest name/status per campaign_id from google_ads_campaigns.
        $latest = DB::table('google_ads_campaigns')
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->selectRaw('campaign_id, MAX(`date`) as md')
            ->groupBy('campaign_id');

        $campaigns = DB::table('google_ads_campaigns as g')
            ->joinSub($latest, 'l', function ($join) {
                $join->on('g.campaign_id', '=', 'l.campaign_id')
                    ->on('g.date', '=', 'l.md');
            })
            ->selectRaw('g.campaign_id, g.campaign_name, g.campaign_status')
            ->get();

        $histByCid = \App\Models\GoogleAdsAuditHistory::where('channel', $this->revisedChannelKey())
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn ($h) => (string) $h->campaign_id);

        $now = Carbon::now();
        $greenDays = 30;
        $data = [];
        $seen = [];

        foreach ($campaigns as $c) {
            $cid = trim((string) ($c->campaign_id ?? ''));
            if ($cid === '' || isset($seen[$cid])) {
                continue;
            }
            $seen[$cid] = true;

            $hist = $histByCid->get($cid, collect());
            $historyArr = [];
            foreach ($hist as $h) {
                $historyArr[] = [
                    'fixed' => (bool) $h->fixed,
                    'details' => (string) $h->details,
                    'created_at' => optional($h->created_at)->format('Y-m-d H:i'),
                ];
            }
            $latestAudit = $hist->last();
            $latestAt = $latestAudit && $latestAudit->created_at ? $latestAudit->created_at : null;
            $isGreen = $latestAt !== null && $latestAt->gt($now->copy()->subDays($greenDays));

            $data[] = [
                'campaign_id' => $cid,
                'campaign_name' => (string) ($c->campaign_name ?? ''),
                'campaign_status' => (string) ($c->campaign_status ?? ''),
                'neg_count' => (int) ($negCounts[$cid] ?? 0),
                'dot' => $isGreen ? 'green' : 'red',
                'latest_audit_at' => $latestAt ? $latestAt->format('Y-m-d H:i') : null,
                'latest_audit_ts' => $latestAt ? $latestAt->getTimestamp() : 0,
                'history' => $historyArr,
            ];
        }

        usort($data, fn ($a, $b) => $a['latest_audit_ts'] <=> $b['latest_audit_ts']);

        return response()->json(['data' => $data]);
    }

    /**
     * Record a Revised-page audit submission for a campaign (both fields mandatory),
     * stored under the dedicated negatives channel.
     */
    public function revisedPageSave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => ['required', 'string', 'max:255'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'fixed' => ['required', 'boolean'],
            'details' => ['required', 'string'],
        ]);

        \App\Models\GoogleAdsAuditHistory::create([
            'channel' => $this->revisedChannelKey(),
            'campaign_id' => $validated['campaign_id'],
            'campaign_name' => $validated['campaign_name'] ?? null,
            'fixed' => (bool) $validated['fixed'],
            'details' => $validated['details'],
            'user_id' => auth()->id(),
            'created_at' => Carbon::now(),
        ]);

        $hist = \App\Models\GoogleAdsAuditHistory::where('channel', $this->revisedChannelKey())
            ->where('campaign_id', $validated['campaign_id'])
            ->orderBy('created_at')->get();
        $historyArr = $hist->map(fn ($h) => [
            'fixed' => (bool) $h->fixed,
            'details' => (string) $h->details,
            'created_at' => optional($h->created_at)->format('Y-m-d H:i'),
        ])->all();
        $latest = $hist->last();
        $latestAt = $latest && $latest->created_at ? $latest->created_at : null;
        $isGreen = $latestAt !== null && $latestAt->gt(Carbon::now()->subDays(30));

        return response()->json([
            'ok' => true,
            'dot' => $isGreen ? 'green' : 'red',
            'latest_audit_at' => $latestAt ? $latestAt->format('Y-m-d H:i') : null,
            'history' => $historyArr,
        ]);
    }

    /**
     * Row counts by U7% band for the current filters (same as the grid except the U7% filter is ignored).
     */
    public function u7Distribution(Request $request): JsonResponse
    {
        $empty = [
            'ok' => false,
            'buckets' => ['lt66' => 0, '66_99' => 0, 'gt99' => 0, 'na' => 0],
            'total' => 0,
        ];

        try {
            $query = $this->buildRawGridBaseQuery();
            $this->applyRawGridDataFilters($query, $request, false);
            $out = $this->aggregateUb7BucketsFromFilteredQuery($query);
        } catch (\Throwable $e) {
            report($e);

            return response()->json($empty + ['reason' => 'query_error'], 500);
        }

        return response()->json([
            'ok' => true,
            'buckets' => $out['buckets'],
            'total' => $out['total'],
        ]);
    }

    /**
     * Per-calendar-day U7% bucket row counts for the last N days (default 30). Each day re-anchors the 30d / L7 / L2 / L1
     * windows to end on that calendar date (parity with Amazon’s per-day re-query, adapted to this page’s window model).
     * Respects U2/U1/Status filters; ignores the U7 filter.
     */
    public function u7DistributionHistory(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);
        if ($days < 1) {
            $days = 1;
        }
        if ($days > 90) {
            $days = 90;
        }

        $tz = config('app.timezone');
        $bucketKey = $this->normalizeU7HistoryBucketKey($request->input('bucket'));
        $daysOut = [];

        try {
            for ($i = $days - 1; $i >= 0; $i--) {
                $d = Carbon::now($tz)->subDays($i)->format('Y-m-d');
                $q = $this->buildRawGridBaseQuery($d);
                $this->applyRawGridDataFilters($q, $request, false);
                $agg = $this->aggregateUb7BucketsFromFilteredQuery($q);
                $row = [
                    'date' => $d,
                    'lt66' => $agg['buckets']['lt66'],
                    '66_99' => $agg['buckets']['66_99'],
                    'gt99' => $agg['buckets']['gt99'],
                    'na' => $agg['buckets']['na'],
                    'total' => $agg['total'],
                ];
                if ($bucketKey !== null) {
                    $row['selected'] = $agg['buckets'][$bucketKey] ?? 0;
                }
                $daysOut[] = $row;
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'days' => [],
                'reason' => 'query_error',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'days' => $daysOut,
            'days_count' => $days,
            'bucket' => $bucketKey,
        ]);
    }

    /**
     * Toolbar badge trend chart — each point is the rolling L30 average as-of that
     * calendar day (saved in google_ads_sbgt_snapshots), not single-day metrics.
     * Optional query: campaign_ids=123,456 limits the chart to visible grid rows.
     */
    public function badgeHistory(Request $request): JsonResponse
    {
        $metric = strtolower((string) $request->input('metric', ''));
        $days = max(1, min(180, (int) $request->input('days', 30)));
        $allowed = array_merge(
            ['spend', 'clicks', 'sold', 'sales', 'acos', 'cvr', 'bgt', 'green_util_l7'],
            self::KPI_BADGE_METRICS,
            self::UTIL_BADGE_METRICS
        );
        if (! in_array($metric, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown metric.',
                'data' => [],
            ], 422);
        }

        // Utilized Y / L7 badges: daily campaign counts per band (+ average utilisation).
        if (in_array($metric, self::UTIL_BADGE_METRICS, true)) {
            return $this->badgeHistoryUtil($metric, $days);
        }

        // Green util (L7) = campaigns with U7% in the green band (66–99%). Daily
        // re-anchored counts — same model as u7DistributionHistory (U7 filter ignored).
        if ($metric === 'green_util_l7') {
            return $this->badgeHistoryGreenUtilL7($request, $days);
        }

        $campaignIds = $this->parseBadgeCampaignIds($request);

        // Daily BGT / Utilization / Avg Bid / Avg CVR% / ACOS% — yesterday (y_*) and rolling L30 (l30_*).
        if (in_array($metric, self::KPI_BADGE_METRICS, true)) {
            return $this->badgeHistoryKpi($metric, $days, array_keys($campaignIds));
        }

        // Persist the preceding completed US day's L30 snapshot when the lock is free.
        $this->ensureTodayBadgeL30Snapshot();

        // Chart window ends on the last completed USA day (never the incomplete current day).
        $end = $this->badgeChartCompletedEndDate();
        $start = $end->copy()->subDays($days - 1);
        $channel = $this->channelKey();
        $cidList = array_keys($campaignIds);

        $rows = collect();
        if (Schema::hasTable('google_ads_sbgt_snapshots')
            && Schema::hasColumn('google_ads_sbgt_snapshots', 'spend_l30')) {
            $query = DB::table('google_ads_sbgt_snapshots')
                ->where('channel', $channel)
                ->whereBetween('snapshot_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->whereNotNull('spend_l30');
            if ($campaignIds !== []) {
                $query->whereIn('campaign_id', $cidList);
            }

            $rows = $query
                ->groupBy('snapshot_date')
                ->orderBy('snapshot_date')
                ->selectRaw('
                    snapshot_date,
                    SUM(COALESCE(spend_l30, 0)) AS spend,
                    SUM(COALESCE(sales_l30, 0)) AS sales,
                    SUM(COALESCE(clicks_l30, 0)) AS clicks,
                    SUM(COALESCE(sold_l30, 0)) AS sold,
                    SUM(COALESCE(bgt, 0)) AS bgt
                ')
                ->get()
                ->keyBy(function ($r) {
                    return Carbon::parse((string) $r->snapshot_date)->format('Y-m-d');
                });
        }

        $data = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $row = $rows->get($key);
            if ($row) {
                $spend = (float) ($row->spend ?? 0);
                $clicks = (float) ($row->clicks ?? 0);
                $sales = (float) ($row->sales ?? 0);
                $sold = (float) ($row->sold ?? 0);
                $bgt = (float) ($row->bgt ?? 0);
            } else {
                // Gap before snapshots existed — compute the same L30 window on the fly.
                $tot = $this->computeL30BadgeTotalsForDate($key, $cidList);
                $spend = $tot['spend'];
                $clicks = $tot['clicks'];
                $sales = $tot['sales'];
                $sold = $tot['sold'];
                $bgt = $tot['bgt'];
            }

            // Same edge cases as the toolbar / grid ACOS L30.
            switch ($metric) {
                case 'spend':
                    $value = round($spend, 2);
                    break;
                case 'clicks':
                    $value = $clicks;
                    break;
                case 'sold':
                    $value = $sold;
                    break;
                case 'sales':
                    $value = round($sales, 2);
                    break;
                case 'acos':
                    if ($spend <= 0.0) {
                        $value = 0.0;
                    } elseif ($sales < 1.0) {
                        $value = 100.0;
                    } else {
                        $value = round(($spend / $sales) * 100, 1);
                    }
                    break;
                case 'cvr':
                    $value = $clicks > 0 ? round(($sold / $clicks) * 100, 1) : 0;
                    break;
                case 'bgt':
                    $value = round($bgt, 2);
                    break;
                default:
                    $value = 0;
            }

            $data[] = [
                'date' => $d->format('M d'),
                'value' => $value,
            ];
        }

        return response()->json([
            'success' => true,
            'metric' => $metric,
            'days' => $days,
            'mode' => 'l30_avg',
            'end_date' => $end->toDateString(),
            'timezone' => 'America/Los_Angeles',
            'data' => $data,
        ]);
    }

    /**
     * Utilized Y / Utilized L7 badges. *_green / *_yellow / *_red / *_pink = campaigns per utilisation band,
     * *_avg = average utilisation % (Σ spend ÷ Σ budget). y = that day's spend vs its budget, l7 = trailing 7 days.
     */
    public const UTIL_BADGE_METRICS = [
        'util_y_green', 'util_y_yellow', 'util_y_red', 'util_y_pink', 'util_y_avg',
        'util_l7_green', 'util_l7_yellow', 'util_l7_red', 'util_l7_pink', 'util_l7_avg',
    ];

    /**
     * Per-day Utilized badge values for every day in [$start, $end] (active (ENABLED) campaigns — same scope
     * as the badges). Same campaign set as the grid: a campaign counts on a day when it has a row in the 30 days
     * up to it, and PARENT campaigns count from their first row on. Its budget is its latest row's budget.
     *
     * @return array<string, array<string, int|float>>  Y-m-d => metric => value
     */
    protected function utilizationDailySeries(Carbon $start, Carbon $end): array
    {
        $from = $start->copy()->subDays(29);
        $query = DB::table('google_ads_campaigns')
            ->whereNotNull('date')
            ->whereNotNull('campaign_id')
            ->where('date', '<=', $end->format('Y-m-d'))
            ->where(function ($q) use ($from) {
                $q->where('date', '>=', $from->format('Y-m-d'))
                    ->orWhereRaw('UPPER(campaign_name) LIKE ?', ['PARENT %']);
            });
        $this->applyCampaignNameScope($query);
        $rows = $query
            ->groupBy('campaign_id', 'date')
            ->selectRaw('campaign_id, `date` AS d, MAX(campaign_name) AS n, MAX(campaign_status) AS st, COALESCE(SUM(metrics_cost_micros), 0) / 1000000.0 AS spend, COALESCE(MAX(budget_amount_micros), 0) / 1000000.0 AS budget')
            ->get();

        $dayNo = static fn (string $ymd): int => intdiv((int) strtotime($ymd.' 00:00:00 UTC'), 86400);
        $byCampaign = [];
        $isParent = [];
        foreach ($rows as $r) {
            $cid = (string) $r->campaign_id;
            $byCampaign[$cid][$dayNo(Carbon::parse((string) $r->d)->format('Y-m-d'))] = [(float) $r->spend, (float) $r->budget, strtoupper(trim((string) $r->st))];
            $isParent[$cid] = str_starts_with(strtoupper((string) $r->n), 'PARENT ');
        }
        $sortedDays = [];
        foreach ($byCampaign as $cid => $days) {
            $keys = array_keys($days);
            sort($keys);
            $sortedDays[$cid] = $keys;
        }

        $band = static function (float $ub): string {
            if ($ub > 99) {
                return 'pink';
            }
            if ($ub >= 66) {
                return 'green';
            }

            return $ub >= 0.1 ? 'yellow' : 'red';
        };

        $out = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dn = $dayNo($d->format('Y-m-d'));
            $y = ['green' => 0, 'yellow' => 0, 'red' => 0, 'pink' => 0];
            $l7 = ['green' => 0, 'yellow' => 0, 'red' => 0, 'pink' => 0];
            $budgetSum = 0.0;
            $spendY = 0.0;
            $spend7 = 0.0;
            foreach ($byCampaign as $cid => $days) {
                // Latest row on or before this day (binary search over the campaign's sorted days).
                $keys = $sortedDays[$cid];
                $lo = 0;
                $hi = count($keys) - 1;
                $latest = null;
                while ($lo <= $hi) {
                    $mid = intdiv($lo + $hi, 2);
                    if ($keys[$mid] <= $dn) {
                        $latest = $keys[$mid];
                        $lo = $mid + 1;
                    } else {
                        $hi = $mid - 1;
                    }
                }
                if ($latest === null || ($dn - $latest >= 30 && ! $isParent[$cid])) {
                    continue; // not in the grid that day
                }
                if ($days[$latest][2] !== 'ENABLED') {
                    continue; // Utilized badges cover active campaigns only
                }
                $budget = $days[$latest][1];
                $sy = $days[$dn][0] ?? 0.0;
                $s7 = 0.0;
                for ($i = 0; $i < 7; $i++) {
                    $s7 += $days[$dn - $i][0] ?? 0.0;
                }
                if ($budget > 0) {
                    $budgetSum += $budget;
                    $spendY += $sy;
                    $spend7 += $s7;
                }
                $y[$band($budget > 0 ? $sy / $budget * 100.0 : 0.0)]++;
                $l7[$band($budget > 0 ? $s7 / ($budget * 7.0) * 100.0 : 0.0)]++;
            }

            $row = [];
            foreach (['green', 'yellow', 'red', 'pink'] as $b) {
                $row['util_y_'.$b] = $y[$b];
                $row['util_l7_'.$b] = $l7[$b];
            }
            $row['util_y_avg'] = $budgetSum > 0 ? round($spendY / $budgetSum * 100.0, 1) : 0.0;
            $row['util_l7_avg'] = $budgetSum > 0 ? round($spend7 / ($budgetSum * 7.0) * 100.0, 1) : 0.0;
            $out[$d->format('Y-m-d')] = $row;
        }

        return $out;
    }

    /**
     * History chart for one Utilized badge (daily value per day).
     */
    protected function badgeHistoryUtil(string $metric, int $days): JsonResponse
    {
        $end = $this->badgeChartCompletedEndDate();
        $start = $end->copy()->subDays($days - 1);
        $series = Cache::remember(
            'gads_util_series_v2:'.$this->channelKey().':'.$end->toDateString().':'.$days,
            now()->addMinutes(10),
            fn () => $this->utilizationDailySeries($start, $end)
        );

        $data = [];
        foreach ($series as $ymd => $row) {
            $data[] = [
                'date' => Carbon::parse($ymd)->format('M d'),
                'value' => $row[$metric] ?? 0,
            ];
        }

        return response()->json([
            'success' => true,
            'metric' => $metric,
            'days' => $days,
            'mode' => 'daily_count',
            'end_date' => $end->toDateString(),
            'timezone' => 'America/Los_Angeles',
            'data' => $data,
        ]);
    }

    /**
     * Dots for the Utilized badges: the value on the last completed US day and the day before.
     */
    public function badgeUtilDots(Request $request): JsonResponse
    {
        $end = $this->badgeChartCompletedEndDate();
        $prevEnd = $end->copy()->subDay();
        $series = Cache::remember(
            'gads_util_dots_v2:'.$this->channelKey().':'.$end->toDateString(),
            now()->addMinutes(10),
            fn () => $this->utilizationDailySeries($prevEnd, $end)
        );

        return response()->json([
            'success' => true,
            'end_date' => $end->toDateString(),
            'prev_date' => $prevEnd->toDateString(),
            'values' => $series[$end->toDateString()] ?? [],
            'prev' => $series[$prevEnd->toDateString()] ?? [],
        ]);
    }

    /**
     * Header KPI badges. `y_*` = the last completed US day, `l30_*` = rolling 30 days ending that day.
     * bgt = daily budget, clicks / sold / sales / spend = totals, util = spend ÷ budget, bid = avg CPC (spend ÷ clicks), cvr = sold ÷ clicks, acos = spend ÷ sales.
     */
    public const KPI_BADGE_METRICS = [
        'y_bgt', 'y_util', 'y_bid', 'y_cvr', 'y_acos', 'y_clicks', 'y_sold', 'y_sales', 'y_spend',
        'l30_bgt', 'l30_util', 'l30_bid', 'l30_cvr', 'l30_acos',
        'l30_bgt_total',
        'p_bgt', 'p_util', 'p_bid', 'p_cvr', 'p_acos', 'p_clicks', 'p_sold', 'p_sales', 'p_spend', 'p_bgt_total',
    ];

    /**
     * KPI badges that always cover EVERY campaign (Active, Paused, Removed …), whatever rows the grid is showing.
     * l30_bgt_total = the daily budgets summed over the last 30 days.
     */
    public const KPI_ALL_CAMPAIGN_METRICS = [
        'l30_bgt_total',
        // The whole Projected block uses every campaign, so its ratios (util / bid / cvr / ACOS) are
        // computed from the same totals as the Spend / Sales / Clicks / Sold tiles beside them.
        'p_bgt', 'p_util', 'p_bid', 'p_cvr', 'p_acos', 'p_clicks', 'p_sold', 'p_sales', 'p_spend', 'p_bgt_total',
    ];

    /**
     * Projected badges (p_*): the last 7 completed US days, divided by 7 and multiplied by 30.
     * Totals (clicks / sold / sales / spend / total bgt) are scaled to 30 days; ratios (util / bid / cvr / acos)
     * and the average daily budget are unchanged by the scaling.
     */
    public const KPI_PROJECTION_DAYS = 30;

    /**
     * @return array<string, true>  campaign_id => true (digits only, max 1000)
     */
    protected function parseBadgeCampaignIds(Request $request): array
    {
        $campaignIds = [];
        foreach (explode(',', (string) $request->input('campaign_ids', '')) as $id) {
            $d = preg_replace('/\D/', '', trim($id));
            if ($d !== '' && strlen($d) <= 32) {
                $campaignIds[$d] = true;
            }
            if (count($campaignIds) >= 1000) {
                break;
            }
        }

        return $campaignIds;
    }

    /**
     * Per-day account totals from google_ads_campaigns for [$from, $to] (inclusive), keyed Y-m-d.
     * bgt is the sum of the campaigns' daily budget on that day.
     *
     * @param  list<string>  $campaignIds
     * @return array<string, array{spend: float, sales: float, clicks: float, sold: float, bgt: float}>
     */
    protected function dailyKpiTotals(Carbon $from, Carbon $to, array $campaignIds = []): array
    {
        $query = DB::table('google_ads_campaigns')
            ->whereNotNull('date')
            ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')]);
        $this->applyCampaignNameScope($query);
        if ($campaignIds !== []) {
            $query->whereIn('campaign_id', $campaignIds);
        }

        $rows = $query
            ->groupBy('date')
            ->selectRaw('
                `date` AS d,
                COALESCE(SUM(metrics_cost_micros), 0) / 1000000.0 AS spend,
                COALESCE(SUM(ga4_actual_revenue), 0) AS actual_sales,
                COALESCE(SUM(ga4_ad_sales), 0) AS ad_sales,
                COALESCE(SUM(metrics_clicks), 0) AS clicks,
                COALESCE(SUM(ga4_actual_sold_units), 0) AS actual_sold,
                COALESCE(SUM(ga4_sold_units), 0) AS ads_sold,
                COALESCE(SUM(budget_amount_micros), 0) / 1000000.0 AS bgt
            ')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $key = Carbon::parse((string) $r->d)->format('Y-m-d');
            $out[$key] = [
                'spend' => (float) $r->spend,
                'sales' => static::resolveSalesL30Value((float) $r->actual_sales, (float) $r->ad_sales),
                'clicks' => (float) $r->clicks,
                'sold' => static::resolveSoldL30Value((float) $r->actual_sold, (float) $r->ads_sold),
                'bgt' => (float) $r->bgt,
            ];
        }

        return $out;
    }

    /**
     * Sum the daily totals for the $len days ending on $end (days without data add nothing).
     *
     * @param  array<string, array{spend: float, sales: float, clicks: float, sold: float, bgt: float}>  $daily
     * @return array{spend: float, sales: float, clicks: float, sold: float, bgt: float, days: int}
     */
    protected function sumKpiWindow(array $daily, Carbon $end, int $len): array
    {
        $sum = ['spend' => 0.0, 'sales' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'bgt' => 0.0, 'days' => 0];
        for ($i = 0; $i < $len; $i++) {
            $row = $daily[$end->copy()->subDays($i)->format('Y-m-d')] ?? null;
            if ($row === null) {
                continue;
            }
            $sum['spend'] += $row['spend'];
            $sum['sales'] += $row['sales'];
            $sum['clicks'] += $row['clicks'];
            $sum['sold'] += $row['sold'];
            $sum['bgt'] += $row['bgt'];
            $sum['days']++;
        }

        return $sum;
    }

    /**
     * One KPI value from a summed window. $metric is y_* or l30_* (the prefix only picks the window).
     *
     * @param  array{spend: float, sales: float, clicks: float, sold: float, bgt: float, days: int}  $w
     */
    protected function kpiValueFromWindow(string $metric, array $w, bool $round = true): float
    {
        $kind = substr($metric, (int) strpos($metric, '_') + 1);

        if (str_starts_with($metric, 'p_')) {
            // L7 ÷ 7 × 30. Days is scaled too so the average daily budget stays the L7 daily average.
            $f = self::KPI_PROJECTION_DAYS / 7;
            foreach (['spend', 'sales', 'clicks', 'sold', 'bgt', 'days'] as $k) {
                $w[$k] = $w[$k] * $f;
            }
        }

        switch ($kind) {
            case 'bgt':
                // Average daily budget across the window (one day for y_*).
                return $w['days'] > 0 ? ($round ? round($w['bgt'] / $w['days'], 2) : ($w['bgt'] / $w['days'])) : 0.0;
            case 'bgt_total':
                // Sum of the daily budgets across the whole window (30 days for l30_*).
                return ($round ? round($w['bgt'], 2) : ($w['bgt']));
            case 'util':
                return $w['bgt'] > 0 ? ($round ? round(($w['spend'] / $w['bgt']) * 100, 1) : (($w['spend'] / $w['bgt']) * 100)) : 0.0;
            case 'clicks':
                return $round ? round($w['clicks']) : $w['clicks'];
            case 'sold':
                return $round ? round($w['sold']) : $w['sold'];
            case 'sales':
                return ($round ? round($w['sales'], 2) : ($w['sales']));
            case 'spend':
                return ($round ? round($w['spend'], 2) : ($w['spend']));
            case 'bid':
                return $w['clicks'] > 0 ? ($round ? round($w['spend'] / $w['clicks'], 2) : ($w['spend'] / $w['clicks'])) : 0.0;
            case 'cvr':
                return $w['clicks'] > 0 ? ($round ? round(($w['sold'] / $w['clicks']) * 100, 1) : (($w['sold'] / $w['clicks']) * 100)) : 0.0;
            case 'acos':
                // ACOS = Spend ÷ Sales of the SAME block, using the whole-dollar Spend / Sales the tiles display
                // (identical to the grid's ACOS column), so anyone can re-check it from the two tiles.
                $spendR = round($w['spend']);
                $salesR = round($w['sales']);
                if ($salesR < 1.0) {
                    return $spendR > 0.0 ? 100.0 : 0.0;
                }
                $acos = ($spendR / $salesR) * 100;

                return $round ? round($acos, 1) : $acos;
        }

        return 0.0;
    }

    protected function kpiWindowLength(string $metric): int
    {
        if (str_starts_with($metric, 'p_')) {
            return 7;
        }

        return str_starts_with($metric, 'l30_') ? 30 : 1;
    }

    /**
     * Toolbar KPI badges: the value as of the last completed US day and as of the day before
     * (the dot compares them). Optional ?campaign_ids=1,2 limits it to the visible grid rows.
     */
    public function badgeKpis(Request $request): JsonResponse
    {
        $cids = array_keys($this->parseBadgeCampaignIds($request));
        $end = $this->badgeChartCompletedEndDate();
        $prevEnd = $end->copy()->subDay();

        // 30 days ending the day before yesterday ⇒ 31 days of data covers both windows.
        $daily = $this->dailyKpiTotals($end->copy()->subDays(30), $end, $cids);
        // All-campaign totals (no campaign filter) for the badges that ignore the grid's rows / Sts filter.
        $dailyAll = $cids === [] ? $daily : $this->dailyKpiTotals($end->copy()->subDays(30), $end);

        $values = [];
        $prev = [];
        foreach (self::KPI_BADGE_METRICS as $metric) {
            $len = $this->kpiWindowLength($metric);
            $src = in_array($metric, self::KPI_ALL_CAMPAIGN_METRICS, true) ? $dailyAll : $daily;
            $values[$metric] = $this->kpiValueFromWindow($metric, $this->sumKpiWindow($src, $end, $len));
            $prev[$metric] = $this->kpiValueFromWindow($metric, $this->sumKpiWindow($src, $prevEnd, $len));
        }

        // Yesterday (y_*) and Projected (p_*) tiles vs the L30 average: better = green, otherwise red.
        $cmp = [];
        $base = [];
        foreach (self::KPI_BADGE_METRICS as $metric) {
            if (! str_starts_with($metric, 'y_') && ! str_starts_with($metric, 'p_')) {
                continue;
            }
            $kind = substr($metric, (int) strpos($metric, '_') + 1);
            $src = in_array($metric, self::KPI_ALL_CAMPAIGN_METRICS, true) ? $dailyAll : $daily;
            $l30 = $this->sumKpiWindow($src, $end, 30);
            $cur = $this->kpiValueFromWindow($metric, $this->sumKpiWindow($src, $end, $this->kpiWindowLength($metric)), false);
            if (str_starts_with($metric, 'y_') && in_array($kind, ['clicks', 'sold', 'sales', 'spend'], true)) {
                // One day vs the L30 per-day average.
                $avg = $l30['days'] > 0 ? $l30[$kind] / $l30['days'] : 0.0;
                $ref = $avg;
            } else {
                $ref = $this->kpiValueFromWindow('l30_'.$kind, $l30, false);
            }
            $lowerIsBetter = in_array($kind, ['bid', 'acos'], true);
            $better = $lowerIsBetter ? $cur < $ref - 1e-9 : $cur > $ref + 1e-9;
            $cmp[$metric] = $better ? 'better' : 'worse';
            $base[$metric] = round($ref, 4);
        }

        return response()->json([
            'success' => true,
            'end_date' => $end->toDateString(),
            'prev_date' => $prevEnd->toDateString(),
            'timezone' => 'America/Los_Angeles',
            'values' => $values,
            'prev' => $prev,
            'cmp' => $cmp,
            'base' => $base,
        ]);
    }

    /**
     * History chart for one KPI badge: y_* = that day's value, l30_* = rolling 30 days ending that day.
     *
     * @param  list<string>  $campaignIds
     */
    protected function badgeHistoryKpi(string $metric, int $days, array $campaignIds): JsonResponse
    {
        $end = $this->badgeChartCompletedEndDate();
        $start = $end->copy()->subDays($days - 1);
        $len = $this->kpiWindowLength($metric);
        if (in_array($metric, self::KPI_ALL_CAMPAIGN_METRICS, true)) {
            $campaignIds = []; // every campaign, whatever the grid shows
        }

        $daily = $this->dailyKpiTotals($start->copy()->subDays($len - 1), $end, $campaignIds);

        $data = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $data[] = [
                'date' => $d->format('M d'),
                'value' => $this->kpiValueFromWindow($metric, $this->sumKpiWindow($daily, $d, $len)),
            ];
        }

        return response()->json([
            'success' => true,
            'metric' => $metric,
            'days' => $days,
            'mode' => $len === 1 ? 'daily' : ($len === 7 ? 'projected_l7' : 'l30_avg'),
            'end_date' => $end->toDateString(),
            'timezone' => 'America/Los_Angeles',
            'data' => $data,
        ]);
    }

    /**
     * Daily count of campaigns in Green utilisation (L7) — U7% band 66–99%.
     * Reads persisted channel-level snapshots (recorded on grid load / first chart open);
     * backfills any missing days with a lightweight L7-only query and stores them.
     */
    private function badgeHistoryGreenUtilL7(Request $request, int $days): JsonResponse
    {
        $end = $this->badgeChartCompletedEndDate();
        $start = $end->copy()->subDays($days - 1);
        $channel = $this->channelKey();

        try {
            $byDate = $this->ensureGreenUtilL7Snapshots($start, $end);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'query_error',
                'data' => [],
            ], 500);
        }

        $data = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $data[] = [
                'date' => $d->format('M d'),
                'value' => (int) ($byDate[$key] ?? 0),
            ];
        }

        return response()->json([
            'success' => true,
            'metric' => 'green_util_l7',
            'days' => $days,
            'mode' => 'daily_count',
            'end_date' => $end->toDateString(),
            'timezone' => 'America/Los_Angeles',
            'channel' => $channel,
            'data' => $data,
        ]);
    }

    /**
     * Best-effort: record today's (completed US day) Green util (L7) count once per channel.
     */
    protected function snapshotGreenUtilL7ForToday(): void
    {
        if (! Schema::hasTable('google_ads_green_util_daily_counts')) {
            return;
        }

        $anchor = $this->badgeChartCompletedEndDate()->toDateString();
        $channel = $this->channelKey();
        $lockKey = "gads_green_util_snap_v1:{$channel}:{$anchor}";

        if (! Cache::add($lockKey, 1, now()->addDay())) {
            return;
        }

        $this->persistGreenUtilL7ForDate($anchor);
    }

    /**
     * Ensure every calendar day in [$start, $end] has a stored green util count.
     * Missing days are computed with {@see countGreenUtilL7ForDate()} and persisted.
     *
     * @return array<string, int> snapshot_date (Y-m-d) => green_count
     */
    protected function ensureGreenUtilL7Snapshots(Carbon $start, Carbon $end): array
    {
        $channel = $this->channelKey();
        $byDate = [];

        if (Schema::hasTable('google_ads_green_util_daily_counts')) {
            $rows = DB::table('google_ads_green_util_daily_counts')
                ->where('channel', $channel)
                ->whereBetween('snapshot_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->get(['snapshot_date', 'green_count']);

            foreach ($rows as $row) {
                $key = Carbon::parse((string) $row->snapshot_date)->format('Y-m-d');
                $byDate[$key] = (int) $row->green_count;
            }
        }

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->format('Y-m-d');
            if (array_key_exists($key, $byDate)) {
                continue;
            }
            $count = $this->countGreenUtilL7ForDate($key);
            $byDate[$key] = $count;
            $this->persistGreenUtilL7ForDate($key, $count);
        }

        return $byDate;
    }

    /**
     * Upsert one day's channel-level Green util (L7) count.
     */
    protected function persistGreenUtilL7ForDate(string $endYmd, ?int $count = null): int
    {
        if (! Schema::hasTable('google_ads_green_util_daily_counts')) {
            return $count ?? $this->countGreenUtilL7ForDate($endYmd);
        }

        $endYmd = Carbon::parse($endYmd)->toDateString();
        $channel = $this->channelKey();
        $green = $count ?? $this->countGreenUtilL7ForDate($endYmd);
        $now = now();

        DB::table('google_ads_green_util_daily_counts')->updateOrInsert(
            ['channel' => $channel, 'snapshot_date' => $endYmd],
            [
                'green_count' => $green,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        return $green;
    }

    /**
     * Lightweight count of campaigns with U7% in the green band (66–99%) as of $forcedEndYmd.
     * Only joins L7 spend + latest budget — far cheaper than the full raw grid query.
     */
    protected function countGreenUtilL7ForDate(?string $forcedEndYmd = null): int
    {
        $bounds = $this->rawGridDateBoundaries($forcedEndYmd);
        $l7Bounds = $this->rawTrailingInclusiveDayBounds($bounds, 7);
        if ($bounds === null || $l7Bounds === null) {
            return 0;
        }

        $latestSub = DB::table('google_ads_campaigns')
            ->whereNotNull('date')
            ->whereBetween('date', [$bounds['start'], $bounds['end']]);
        $this->applyCampaignNameScope($latestSub);
        $latestSub->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, MAX(`date`) as max_d')
            ->groupBy('campaign_id');

        $sumL7Sub = DB::table('google_ads_campaigns')
            ->whereNotNull('date')
            ->whereBetween('date', [$l7Bounds['start'], $l7Bounds['end']]);
        $this->applyCampaignNameScope($sumL7Sub);
        $sumL7Sub->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, SUM(metrics_cost_micros) as sum_micros_l7')
            ->groupBy('campaign_id');

        $ub7 = '(CASE WHEN COALESCE(g.budget_amount_micros, 0) > 0 THEN (COALESCE(cSpendL7.sum_micros_l7, 0) / 1000000.0) / ((g.budget_amount_micros / 1000000.0) * 7.0) * 100.0 ELSE 0 END)';

        $query = DB::table('google_ads_campaigns as g')
            ->joinSub($latestSub, 'latest', function ($join) {
                $join->on('g.campaign_id', '=', 'latest.campaign_id')
                    ->on('g.date', '=', 'latest.max_d');
            })
            ->leftJoinSub($sumL7Sub, 'cSpendL7', function ($join) {
                $join->on('g.campaign_id', '=', 'cSpendL7.campaign_id');
            });
        $this->applyCampaignNameScope($query, 'g.campaign_name');

        $row = $query->selectRaw(
            'SUM(CASE WHEN COALESCE(g.budget_amount_micros, 0) > 0 AND ('.$ub7.') >= 66 AND ('.$ub7.') <= 99 THEN 1 ELSE 0 END) AS green_count'
        )->first();

        return (int) ($row->green_count ?? 0);
    }

    /**
     * One row per campaign for the raw grid (before U7/U2/U1/Sts filters). Optional $forcedEndYmd (Y-m-d) anchors windows for history.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function buildRawGridBaseQuery(?string $forcedEndYmd = null)
    {
        $bounds = $this->rawGridDateBoundaries($forcedEndYmd);
        $l7Bounds = $this->rawTrailingInclusiveDayBounds($bounds, 7);
        $l2Bounds = $this->rawTrailingInclusiveDayBounds($bounds, 2);
        $l1Bounds = $this->rawTrailingInclusiveDayBounds($bounds, 1);

        $metricsSub = $this->buildRawGridMetricsSubquery($bounds, $l7Bounds, $l2Bounds, $l1Bounds);

        // Latest row per campaign in the 30d window, PLUS every PARENT campaign's
        // latest row ever — so parent rows always appear even with no recent metrics.
        $latestSub = DB::table('google_ads_campaigns');
        $this->applyCampaignNameScope($latestSub);
        $latestSub->whereNotNull('campaign_id')
            ->whereNotNull('date')
            ->where(function ($q) use ($bounds) {
                if ($bounds !== null) {
                    $q->whereBetween('date', [$bounds['start'], $bounds['end']])
                        ->orWhereRaw('UPPER(campaign_name) LIKE ?', ['PARENT %']);
                }
            })
            ->selectRaw('campaign_id, MAX(`date`) as max_d')
            ->groupBy('campaign_id');

        $query = DB::table('google_ads_campaigns as g')
            ->leftJoinSub($metricsSub, 'agg', function ($join) {
                $join->on('g.campaign_id', '=', 'agg.campaign_id');
            })
            ->joinSub($latestSub, 'cLatest', function ($join) {
                $join->on('g.campaign_id', '=', 'cLatest.campaign_id')
                    ->on('g.date', '=', 'cLatest.max_d');
            })
            ->whereNotNull('g.campaign_id');
        // Non-parent rows stay in the 30d window; PARENT campaigns may use their latest row ever.
        if ($bounds !== null) {
            $query->where(function ($q) use ($bounds) {
                $q->where(function ($q2) use ($bounds) {
                    $q2->whereNotNull('g.date')
                        ->whereBetween('g.date', [$bounds['start'], $bounds['end']]);
                })->orWhereRaw('UPPER(g.campaign_name) LIKE ?', ['PARENT %']);
            });
        }
        $this->applyCampaignNameScope($query, 'g.campaign_name');

        $query->select([
            'g.id',
            'g.date',
            'g.campaign_id',
            'g.campaign_name',
            'g.campaign_status',
            'g.budget_id',
            'g.budget_amount_micros',
            'g.bidding_strategy_type',
        ])
            ->addSelect(DB::raw('COALESCE(agg.sum_micros, 0) as spend_window_micros'))
            ->addSelect(DB::raw('COALESCE(agg.sum_micros, 0) / 1000000 as spend'))
            ->addSelect(DB::raw('COALESCE(agg.sum_micros_l7, 0) / 1000000 as l7_spend'))
            ->addSelect(DB::raw('COALESCE(agg.sum_micros_l2, 0) / 1000000 as l2_spend'))
            ->addSelect(DB::raw('COALESCE(agg.sum_micros_l1, 0) / 1000000 as l1_spend'))
            ->addSelect(DB::raw('COALESCE(agg.sum_clicks_30, 0) as clicks_sum_30'))
            ->addSelect(DB::raw('COALESCE(agg.sum_impr_30, 0) as impr_sum_30'))
            ->addSelect(DB::raw('COALESCE(agg.sum_clicks_l7, 0) as clicks_sum_l7'))
            ->addSelect(DB::raw('COALESCE(agg.sum_clicks_l2, 0) as clicks_sum_l2'))
            ->addSelect(DB::raw('COALESCE(agg.sum_clicks_l1, 0) as clicks_sum_l1'))
            ->addSelect(DB::raw('COALESCE(agg.sum_views_30, 0) as views_sum_30'))
            ->addSelect(DB::raw('COALESCE(agg.sum_views_l7, 0) as views_sum_l7'))
            ->addSelect(DB::raw('COALESCE(agg.sum_views_l2, 0) as views_sum_l2'))
            ->addSelect(DB::raw('COALESCE(agg.sum_views_l1, 0) as views_sum_l1'))
            ->addSelect(DB::raw('COALESCE(agg.sum_ga4_actual, 0) as sum_ga4_actual'))
            ->addSelect(DB::raw('COALESCE(agg.sum_ga4_ads, 0) as sum_ga4_ads'))
            ->addSelect(DB::raw('COALESCE(agg.sum_ga4_actual_sold, 0) as sum_ga4_actual_sold'))
            ->addSelect(DB::raw('COALESCE(agg.sum_ga4_ads_sold, 0) as sum_ga4_ads_sold'))
            ->addSelect(DB::raw(static::salesL30SqlExpression().' as sales_l30_agg'))
            ->addSelect(DB::raw(static::soldL30SqlExpression().' as sold_l30_agg'));

        return $query;
    }

    /**
     * Single 30-day GROUP BY for spend / clicks / views / GA4 (L7/L2/L1 via CASE).
     * Replaces the previous 9 separate scans of google_ads_campaigns.
     *
     * @param  array{start: string, end: string}|null  $bounds
     * @param  array{start: string, end: string}|null  $l7Bounds
     * @param  array{start: string, end: string}|null  $l2Bounds
     * @param  array{start: string, end: string}|null  $l1Bounds
     * @return \Illuminate\Database\Query\Builder
     */
    private function buildRawGridMetricsSubquery(?array $bounds, ?array $l7Bounds, ?array $l2Bounds, ?array $l1Bounds)
    {
        $metricsSub = DB::table('google_ads_campaigns');

        if ($bounds !== null && $l7Bounds !== null && $l2Bounds !== null && $l1Bounds !== null) {
            $metricsSub->selectRaw(
                'campaign_id,
                SUM(metrics_cost_micros) as sum_micros,
                SUM(CASE WHEN `date` >= ? THEN metrics_cost_micros ELSE 0 END) as sum_micros_l7,
                SUM(CASE WHEN `date` >= ? THEN metrics_cost_micros ELSE 0 END) as sum_micros_l2,
                SUM(CASE WHEN `date` >= ? THEN metrics_cost_micros ELSE 0 END) as sum_micros_l1,
                SUM(metrics_clicks) as sum_clicks_30,
                SUM(metrics_impressions) as sum_impr_30,
                SUM(metrics_video_views) as sum_views_30,
                SUM(CASE WHEN `date` >= ? THEN metrics_clicks ELSE 0 END) as sum_clicks_l7,
                SUM(CASE WHEN `date` >= ? THEN metrics_clicks ELSE 0 END) as sum_clicks_l2,
                SUM(CASE WHEN `date` >= ? THEN metrics_clicks ELSE 0 END) as sum_clicks_l1,
                SUM(CASE WHEN `date` >= ? THEN metrics_video_views ELSE 0 END) as sum_views_l7,
                SUM(CASE WHEN `date` >= ? THEN metrics_video_views ELSE 0 END) as sum_views_l2,
                SUM(CASE WHEN `date` >= ? THEN metrics_video_views ELSE 0 END) as sum_views_l1,
                SUM(ga4_actual_revenue) as sum_ga4_actual,
                SUM(ga4_ad_sales) as sum_ga4_ads,
                SUM(ga4_actual_sold_units) as sum_ga4_actual_sold,
                SUM(ga4_sold_units) as sum_ga4_ads_sold',
                [
                    $l7Bounds['start'], $l2Bounds['start'], $l1Bounds['start'],
                    $l7Bounds['start'], $l2Bounds['start'], $l1Bounds['start'],
                    $l7Bounds['start'], $l2Bounds['start'], $l1Bounds['start'],
                ]
            );
            $metricsSub->whereNotNull('date')
                ->whereBetween('date', [$bounds['start'], $bounds['end']]);
        } else {
            $metricsSub->selectRaw(
                'campaign_id,
                SUM(metrics_cost_micros) as sum_micros,
                SUM(metrics_cost_micros) as sum_micros_l7,
                SUM(metrics_cost_micros) as sum_micros_l2,
                SUM(metrics_cost_micros) as sum_micros_l1,
                SUM(metrics_clicks) as sum_clicks_30,
                SUM(metrics_impressions) as sum_impr_30,
                SUM(metrics_video_views) as sum_views_30,
                SUM(metrics_clicks) as sum_clicks_l7,
                SUM(metrics_clicks) as sum_clicks_l2,
                SUM(metrics_clicks) as sum_clicks_l1,
                SUM(metrics_video_views) as sum_views_l7,
                SUM(metrics_video_views) as sum_views_l2,
                SUM(metrics_video_views) as sum_views_l1,
                SUM(ga4_actual_revenue) as sum_ga4_actual,
                SUM(ga4_ad_sales) as sum_ga4_ads,
                SUM(ga4_actual_sold_units) as sum_ga4_actual_sold,
                SUM(ga4_sold_units) as sum_ga4_ads_sold'
            );
        }

        $this->applyCampaignNameScope($metricsSub);
        $metricsSub->whereNotNull('campaign_id')->groupBy('campaign_id');

        return $metricsSub;
    }

    /**
     * UB% color bands match the raw grid formatters: green 66–99%, pink &gt;99%, red &lt;66%; zero = 0% (under 0.1%).
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    private function applyRawGridDataFilters($query, Request $request, bool $includeUb7 = true): void
    {
        $ub7 = $this->normalizeUbColorFilter($request->input('filter_ub7'));
        $ub2 = $this->normalizeUbColorFilter($request->input('filter_ub2'));
        $ub1 = $this->normalizeUbColorFilter($request->input('filter_ub1'));
        $acos = $this->normalizeAcosColorFilter($request->input('filter_acos'));
        $stat = $this->normalizeStatFilter($request->input('filter_stat'));
        $searchQ = $this->normalizeCampaignSearchQuery($request->input('q'));

        $ctrMin = $this->normalizeRangeFilterValue($request->input('filter_ctr_min'));
        $ctrMax = $this->normalizeRangeFilterValue($request->input('filter_ctr_max'));
        $cvrMin = $this->normalizeRangeFilterValue($request->input('filter_cvr_min'));
        $cvrMax = $this->normalizeRangeFilterValue($request->input('filter_cvr_max'));

        if ($searchQ !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], strtoupper($searchQ)).'%';
            $query->whereRaw('UPPER(g.campaign_name) LIKE ? ESCAPE \'\\\\\'', [$like]);
        }

        $ub7Expr = '(CASE WHEN COALESCE(g.budget_amount_micros, 0) > 0 THEN (COALESCE(agg.sum_micros_l7, 0) / 1000000.0) / ((g.budget_amount_micros / 1000000.0) * 7.0) * 100.0 ELSE 0 END)';
        $ub2Expr = '(CASE WHEN COALESCE(g.budget_amount_micros, 0) > 0 THEN (COALESCE(agg.sum_micros_l2, 0) / 1000000.0) / ((g.budget_amount_micros / 1000000.0) * 2.0) * 100.0 ELSE 0 END)';
        $ub1Expr = '(CASE WHEN COALESCE(g.budget_amount_micros, 0) > 0 THEN (COALESCE(agg.sum_micros_l1, 0) / 1000000.0) / (g.budget_amount_micros / 1000000.0) * 100.0 ELSE 0 END)';

        if ($includeUb7) {
            $this->whereUbColorBand($query, $ub7Expr, $ub7);
        }
        $this->whereUbColorBand($query, $ub2Expr, $ub2);
        $this->whereUbColorBand($query, $ub1Expr, $ub1);
        $this->whereAcosColorBand($query, $acos);

        // CTR / CVR min-max range filters. Expressions mirror ctr_l30 / cvr_l30 so the
        // filtered rows match the displayed (and colour-flagged) values to the percent.
        $ctrExpr = '(CASE WHEN COALESCE(agg.sum_impr_30, 0) > 0 THEN (COALESCE(agg.sum_clicks_30, 0) / COALESCE(agg.sum_impr_30, 0)) * 100.0 ELSE 0 END)';
        $cvrExpr = '(CASE WHEN COALESCE(agg.sum_clicks_30, 0) > 0 THEN ('.static::soldL30SqlExpression().' / COALESCE(agg.sum_clicks_30, 0)) * 100.0 ELSE 0 END)';
        $this->whereRangeBand($query, $ctrExpr, $ctrMin, $ctrMax);
        $this->whereRangeBand($query, $cvrExpr, $cvrMin, $cvrMax);

        if ($stat === 'ENABLED') {
            $query->whereRaw('UPPER(TRIM(COALESCE(g.campaign_status, ""))) = ?', ['ENABLED']);
        } elseif ($stat === 'NOT_ENABLED') {
            // Every status except ENABLED (includes PAUSED, REMOVED, UNKNOWN, etc.)
            $query->whereRaw('UPPER(TRIM(COALESCE(g.campaign_status, ""))) <> ?', ['ENABLED']);
        } elseif ($stat !== 'all' && $stat !== '') {
            $query->whereRaw('UPPER(TRIM(COALESCE(g.campaign_status, ""))) = ?', [strtoupper($stat)]);
        }

        // Verify ID: L30 spend = 0 (INV > 0 applied after inventory attach in data()).
        if ($request->boolean('filter_verify_id')) {
            $query->whereRaw('COALESCE(agg.sum_micros, 0) = 0');
        }
    }

    private function normalizeUbColorFilter(mixed $value): string
    {
        $v = is_string($value) ? strtolower(trim($value)) : 'all';

        return in_array($v, ['green', 'pink', 'red', 'zero'], true) ? $v : 'all';
    }

    /**
     * Parse a numeric range-filter box (CTR/CVR min or max). Returns a non-negative float,
     * or null when the input is blank / non-numeric so the bound is simply not applied.
     */
    private function normalizeRangeFilterValue(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }
        $f = (float) $s;
        if (! is_finite($f) || $f < 0) {
            return null;
        }

        return $f;
    }

    /**
     * Apply `>= $min` and/or `<= $max` bounds on a computed SQL expression. Either bound
     * may be null (not applied). Used by the CTR / CVR min-max grid filters.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    private function whereRangeBand($query, string $sqlExpr, ?float $min, ?float $max): void
    {
        if ($min !== null) {
            $query->whereRaw("({$sqlExpr}) >= ?", [$min]);
        }
        if ($max !== null) {
            $query->whereRaw("({$sqlExpr}) <= ?", [$max]);
        }
    }

    /**
     * Trim, cap length, and reject control characters so the search input can't
     * blow up the LIKE clause on a hostile request.
     */
    private function normalizeCampaignSearchQuery(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $v = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '');
        if ($v === '') {
            return '';
        }

        return mb_substr($v, 0, 100);
    }

    /**
     * Apply server-side ORDER BY based on Tabulator's `sort` request param
     * (e.g. `sort[0][field]=spend&sort[0][dir]=desc`). Whitelist of fields ⇄
     * SQL expressions is kept in sync with the `sortableFields` map in the
     * raw grid view. Computed expressions mirror {@see whereAcosColorBand}
     * and {@see whereUbColorBand} so sort and filter agree to the percent.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    private function applyRawGridSort($query, Request $request): void
    {
        $spend = '(COALESCE(agg.sum_micros, 0) / 1000000.0)';
        $spendL7 = '(COALESCE(agg.sum_micros_l7, 0) / 1000000.0)';
        $spendL2 = '(COALESCE(agg.sum_micros_l2, 0) / 1000000.0)';
        $spendL1 = '(COALESCE(agg.sum_micros_l1, 0) / 1000000.0)';
        $sales = static::salesL30SqlExpression();
        $sold = static::soldL30SqlExpression();
        $clicks = 'COALESCE(agg.sum_clicks_30, 0)';
        $clicksL7 = 'COALESCE(agg.sum_clicks_l7, 0)';
        $clicksL2 = 'COALESCE(agg.sum_clicks_l2, 0)';
        $clicksL1 = 'COALESCE(agg.sum_clicks_l1, 0)';
        $impr = 'COALESCE(agg.sum_impr_30, 0)';
        $acosExpr = "(CASE "
            ."WHEN ROUND({$sales}) >= 1 THEN (ROUND({$spend}) / ROUND({$sales})) * 100.0 "
            ."WHEN ROUND({$spend}) > 0 THEN 100.0 "
            ."ELSE 0 END)";
        // CVR sort SQL — mirrors the per-row PHP formula in enrichRawRowGoogleShoppingStyle()
        // so that ORDER BY cvr_l30 matches the displayed grid value to the percent.
        $cvrExpr = "(CASE WHEN {$clicks} > 0 THEN ({$sold} / {$clicks}) * 100.0 ELSE 0 END)";
        // CTR sort SQL — mirrors ctr_l30 = (clicks / impressions) * 100.
        $ctrExpr = "(CASE WHEN {$impr} > 0 THEN ({$clicks} / {$impr}) * 100.0 ELSE 0 END)";
        $cpcL30 = "(CASE WHEN {$clicks} > 0 THEN {$spend} / {$clicks} ELSE 0 END)";
        $cpcL7 = "(CASE WHEN {$clicksL7} > 0 THEN {$spendL7} / {$clicksL7} ELSE 0 END)";
        $cpcL2 = "(CASE WHEN {$clicksL2} > 0 THEN {$spendL2} / {$clicksL2} ELSE 0 END)";
        $cpcL1 = "(CASE WHEN {$clicksL1} > 0 THEN {$spendL1} / {$clicksL1} ELSE 0 END)";
        $views30 = 'COALESCE(agg.sum_views_30, 0)';
        $cpsL30 = "(CASE WHEN {$sold} > 0 THEN {$spend} / {$sold} ELSE 0 END)";
        $cpvL30 = "(CASE WHEN {$views30} > 0 THEN {$spend} / {$views30} ELSE 0 END)";
        $ub7 = '(CASE WHEN COALESCE(g.budget_amount_micros, 0) > 0 THEN (COALESCE(agg.sum_micros_l7, 0) / 1000000.0) / ((g.budget_amount_micros / 1000000.0) * 7.0) * 100.0 ELSE 0 END)';
        $ub2 = '(CASE WHEN COALESCE(g.budget_amount_micros, 0) > 0 THEN (COALESCE(agg.sum_micros_l2, 0) / 1000000.0) / ((g.budget_amount_micros / 1000000.0) * 2.0) * 100.0 ELSE 0 END)';
        $ub1 = '(CASE WHEN COALESCE(g.budget_amount_micros, 0) > 0 THEN (COALESCE(agg.sum_micros_l1, 0) / 1000000.0) / (g.budget_amount_micros / 1000000.0) * 100.0 ELSE 0 END)';
        $sbgtExpr = $this->sbgtSortSqlExpression($acosExpr);
        $sbidExpr = $this->sbidSortSqlExpression($ub7, $ub1, $cpcL1, $cpcL7);

        $sortMap = [
            'campaign_status' => 'g.campaign_status',
            'campaign_name' => 'g.campaign_name',
            'spend' => 'COALESCE(agg.sum_micros, 0)',
            'l7_spend' => 'COALESCE(agg.sum_micros_l7, 0)',
            'l2_spend' => 'COALESCE(agg.sum_micros_l2, 0)',
            'l1_spend' => 'COALESCE(agg.sum_micros_l1, 0)',
            'metrics_clicks' => 'COALESCE(agg.sum_clicks_30, 0)',
            'ctr_l30' => $ctrExpr,
            'cpc_L30' => $cpcL30,
            'cps_L30' => $cpsL30,
            'cpv_L30' => $cpvL30,
            'video_views_L30' => $views30,
            'cpc_L7' => $cpcL7,
            'cpc_L2' => $cpcL2,
            'cpc_L1' => $cpcL1,
            'ad_sold_L30' => $sold,
            'ad_sales_L30' => $sales,
            'acos_l30' => $acosExpr,
            'cvr_l30' => $cvrExpr,
            'bgt' => 'COALESCE(g.budget_amount_micros, 0)',
            'ub7' => $ub7,
            'ub2' => $ub2,
            'ub1' => $ub1,
            'bgt_acos' => $sbgtExpr,
            'bgt_cvr' => $this->bgtCvrSortSqlExpression($cvrExpr),
            'sbgt' => $sbgtExpr,
            // COALESCE so NULL (no SBID suggestion) sorts as lowest, matching the "—" cells.
            'sbid' => "COALESCE({$sbidExpr}, -1)",
        ];

        $applied = false;
        [$field, $dir] = $this->rawGridSortFromRequest($request);
        if ($field !== null && isset($sortMap[$field]) && ! $this->isPhpSortField($field)) {
            $query->orderByRaw($sortMap[$field].' '.$dir);
            $applied = true;
        }

        // Deterministic tiebreaker (and default ordering when no sort is sent)
        if (! $applied) {
            $query->orderByDesc('g.id');
        } else {
            $query->orderBy('g.id', 'desc');
        }
    }

    /**
     * SQL CASE matching {@see GoogleShoppingCampaignsRawRule::sbgtFromAcos()} for ORDER BY.
     */
    private function sbgtSortSqlExpression(string $acosExpr): string
    {
        $rule = GoogleShoppingCampaignsRawRule::resolvedRule();
        $bands = GoogleShoppingCampaignsRawRule::normalizeSbgtBands($rule['sbgt']['bands'] ?? []);
        $fallback = (int) GoogleShoppingCampaignsRawRule::sbgtFromAcos(-1.0, $rule);

        $whens = [];
        foreach ($bands as $band) {
            $from = (float) ($band['acos_from'] ?? 0);
            $to = (float) ($band['acos_to'] ?? 9999);
            $sbgt = (int) ($band['sbgt'] ?? 0);
            $whens[] = sprintf(
                'WHEN %s >= %s AND %s <= %s THEN %d',
                $acosExpr,
                $this->sqlFloatLiteral($from),
                $acosExpr,
                $this->sqlFloatLiteral($to),
                $sbgt
            );
        }

        if ($whens === []) {
            return (string) $fallback;
        }

        return '(CASE WHEN '.$acosExpr.' < 0 THEN '.$fallback.' '
            .implode(' ', $whens)
            .' ELSE '.$fallback.' END)';
    }

    /**
     * SQL CASE matching {@see GoogleShoppingBgtCvrRule::apply()} for ORDER BY.
     */
    private function bgtCvrSortSqlExpression(string $cvrExpr): string
    {
        $bands = GoogleShoppingBgtCvrRule::resolvedRule()['bands'] ?? [];
        $whens = [];
        foreach ($bands as $band) {
            if (! is_array($band)) {
                continue;
            }
            $from = (float) ($band['cvr_from'] ?? 0);
            $to = (float) ($band['cvr_to'] ?? 9999);
            $bgt = (int) ($band['bgt'] ?? 0);
            $whens[] = sprintf(
                'WHEN %s >= %s AND %s <= %s THEN %d',
                $cvrExpr,
                $this->sqlFloatLiteral($from),
                $cvrExpr,
                $this->sqlFloatLiteral($to),
                $bgt
            );
        }

        if ($whens === []) {
            return '0';
        }

        return '(CASE '.implode(' ', $whens).' ELSE 0 END)';
    }

    protected function usesShoppingBgtParts(): bool
    {
        return $this->channelKey() === 'shopping';
    }

    /**
     * @return \Closure(string): array{views_l7: float, views_l30: float, price: float|null, inv: float, ovl30: float, dil: float|null}|null
     */
    protected function shoppingBgtMetricsResolver(): ?\Closure
    {
        if (! $this->usesShoppingBgtParts()) {
            return null;
        }

        return GoogleShoppingBgtSkuMetrics::resolver();
    }

    /**
     * @param  array<string, mixed>  $arr
     * @param  \Closure(string): array{views_l7: float, views_l30: float, price: float|null, inv: float, ovl30: float, dil: float|null}|null  $resolver
     * @param  array{sbgt: array<string, mixed>, sbid: array<string, float>}  $rawRule
     */
    protected function attachShoppingBgtParts(array &$arr, ?\Closure $resolver, array $rawRule): void
    {
        if ($resolver === null) {
            return;
        }
        $metrics = $resolver((string) ($arr['campaign_name'] ?? ''));
        GoogleShoppingBgtParts::applyToRow($arr, $metrics, $rawRule);
    }

    /**
     * SQL CASE matching {@see GoogleShoppingCampaignsRawRule::sbidFromUb7Ub1Cpc()} for ORDER BY.
     */
    private function sbidSortSqlExpression(string $ub7Expr, string $ub1Expr, string $cpcL1Expr, string $cpcL7Expr): string
    {
        $s = GoogleShoppingCampaignsRawRule::resolvedRule()['sbid'];
        $low = $this->sqlFloatLiteral((float) $s['util_low']);
        $high = $this->sqlFloatLiteral((float) $s['util_high']);
        $overM = $this->sqlFloatLiteral((float) $s['over_mult_l1']);
        $underM1 = $this->sqlFloatLiteral((float) $s['under_mult_l1']);
        $underM7 = $this->sqlFloatLiteral((float) $s['under_mult_l7']);
        $fb = $this->sqlFloatLiteral((float) $s['under_fallback']);
        $flatMax = $this->sqlFloatLiteral((float) ($s['under_flat_max'] ?? 0.25));
        $flatIncr = $this->sqlFloatLiteral((float) ($s['under_flat_incr'] ?? 0.05));

        $overBid = "FLOOR(({$cpcL1Expr}) * {$overM} * 100.0) / 100.0";
        $underL1 = '(CASE '
            ."WHEN ({$cpcL1Expr}) < {$flatMax} THEN FLOOR((({$cpcL1Expr}) + {$flatIncr}) * 100.0) / 100.0 "
            ."ELSE FLOOR(({$cpcL1Expr}) * {$underM1} * 100.0) / 100.0 "
            .'END)';
        $underL7 = '(CASE '
            ."WHEN ({$cpcL7Expr}) < {$flatMax} THEN FLOOR((({$cpcL7Expr}) + {$flatIncr}) * 100.0) / 100.0 "
            ."ELSE FLOOR(({$cpcL7Expr}) * {$underM7} * 100.0) / 100.0 "
            .'END)';
        $underBid = '(CASE '
            ."WHEN ({$cpcL1Expr}) <= 0 AND ({$cpcL7Expr}) <= 0 THEN {$fb} "
            ."WHEN ({$cpcL1Expr}) > 0 THEN {$underL1} "
            ."ELSE {$underL7} "
            .'END)';

        return '(CASE '
            ."WHEN ({$ub7Expr}) > {$high} AND ({$ub1Expr}) > {$high} THEN {$overBid} "
            ."WHEN ({$ub7Expr}) < {$low} AND ({$ub1Expr}) < {$low} THEN {$underBid} "
            .'ELSE NULL END)';
    }

    private function sqlFloatLiteral(float $value): string
    {
        if (! is_finite($value)) {
            return '0';
        }

        return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.') ?: '0';
    }

    /**
     * ACOS L30 color bands match {@see acosFormatter} in the raw grid view:
     * pink <10, green 10–20, blue 20–30, yellow 30–40, orange 40–50, red >50.
     */
    private function normalizeAcosColorFilter(mixed $value): string
    {
        $v = is_string($value) ? strtolower(trim($value)) : 'all';

        return in_array($v, ['pink', 'green', 'blue', 'yellow', 'orange', 'red'], true) ? $v : 'all';
    }

    private function normalizeStatFilter(mixed $value): string
    {
        if (! is_string($value)) {
            return 'all';
        }
        $v = strtoupper(trim($value));
        if ($v === '' || $v === 'ALL') {
            return 'all';
        }
        if ($v === 'NOT_ENABLED') {
            return 'NOT_ENABLED';
        }
        if (in_array($v, ['ENABLED', 'PAUSED', 'REMOVED'], true)) {
            return $v;
        }

        return 'all';
    }

    /** ID check filter: matched = green, missing = no ID found (yellow), mismatch = wrong ID (red). */
    private function normalizeIdStatusFilter(mixed $value): string
    {
        $v = is_string($value) ? strtolower(trim($value)) : 'all';

        return in_array($v, ['matched', 'missing', 'mismatch'], true) ? $v : 'all';
    }

    /** Verification filter: match = green (attached Items = SKU count), mismatch = red. */
    private function normalizeVerificationFilter(mixed $value): string
    {
        $v = is_string($value) ? strtolower(trim($value)) : 'all';

        return in_array($v, ['match', 'mismatch'], true) ? $v : 'all';
    }

    /**
     * N Target filter: "m" = campaigns with no negative keywords (the red M), "has" = at least one.
     * Counts live in google_ads_negative_keywords, so like INV this runs in PHP. Shopping grid only.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $collection
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    private function filterRawGridRowsByNegativeTargets($collection, mixed $value)
    {
        $v = is_string($value) ? strtolower(trim($value)) : 'all';
        if (! in_array($v, ['m', 'has'], true) || $this->channelKey() !== 'shopping') {
            return $collection;
        }
        $ids = [];
        foreach ($collection as $row) {
            $ids[] = (string) (is_object($row) ? ($row->campaign_id ?? '') : ($row['campaign_id'] ?? ''));
        }
        $counts = $this->negativeTargetCountsForPage($ids);

        return $collection->filter(static function ($row) use ($counts, $v) {
            $cid = trim((string) (is_object($row) ? ($row->campaign_id ?? '') : ($row['campaign_id'] ?? '')));
            $n = (int) ($counts[$cid] ?? 0);

            return $v === 'm' ? $n === 0 : $n > 0;
        })->values();
    }

    private function normalizeInvFilter(mixed $value): string
    {
        $v = is_string($value) ? strtolower(trim($value)) : 'all';
        if (in_array($v, ['gt0', '>0', 'pos'], true)) {
            return 'gt0';
        }
        if (in_array($v, ['eq0', '=0', 'zero', '0'], true)) {
            return 'eq0';
        }

        return 'all';
    }

    /**
     * Inventory is Shopify (not in google_ads_campaigns), so INV filters run in PHP.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $collection
     * @param  \Closure(string): ?int  $invResolver
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    private function filterRawGridRowsByInventory($collection, string $invFilter, \Closure $invResolver)
    {
        if ($invFilter === 'all') {
            return $collection;
        }

        return $collection->filter(static function ($row) use ($invResolver, $invFilter) {
            $name = is_object($row)
                ? (string) ($row->campaign_name ?? '')
                : (string) ($row['campaign_name'] ?? '');
            $inv = $invResolver($name);
            $n = $inv !== null ? (int) $inv : 0;

            return $invFilter === 'gt0' ? $n > 0 : $n === 0;
        })->values();
    }

    /**
     * Price (Shopify, same value as the Price column) min / max filter. Price is not in google_ads_campaigns,
     * so like INV it runs in PHP. With a bound set, campaigns without a price are left out.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $collection
     * @param  \Closure|null  $bgtResolver  {@see shoppingBgtMetricsResolver()}
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    private function filterRawGridRowsByPrice($collection, Request $request, ?\Closure $bgtResolver)
    {
        $min = $this->normalizeRangeFilterValue($request->input('filter_price_min'));
        $max = $this->normalizeRangeFilterValue($request->input('filter_price_max'));
        if (($min === null && $max === null) || $bgtResolver === null) {
            return $collection;
        }

        return $collection->filter(static function ($row) use ($bgtResolver, $min, $max) {
            $name = is_object($row)
                ? (string) ($row->campaign_name ?? '')
                : (string) ($row['campaign_name'] ?? '');
            $price = ($bgtResolver($name))['price'] ?? null;
            if ($price === null) {
                return false;
            }
            $price = (float) $price;

            return ($min === null || $price >= $min) && ($max === null || $price <= $max);
        })->values();
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function rawGridSortFromRequest(Request $request): array
    {
        $raw = $request->input('sort');
        if (! is_array($raw) || $raw === []) {
            $raw = $request->input('sorters');
        }
        if (! is_array($raw)) {
            return [null, 'asc'];
        }
        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $field = is_string($entry['field'] ?? null) ? $entry['field'] : '';
            if ($field === '') {
                continue;
            }
            $dir = strtolower((string) ($entry['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

            return [$field, $dir];
        }

        return [null, 'asc'];
    }

    private function isPhpSortField(string $field): bool
    {
        $fields = [
            'inventory',
            'dil',
            'views_l30',
            'views_l7',
            'price',
            'bgt_views',
            'bgt_prc',
            'bgt_dil',
            'sbgt',
        ];

        // YouTube Sales / ACOS are finalized in PHP (transaction $ or Shopify
        // price fallback). SQL ORDER BY still uses the pre-fallback $1 Ads value,
        // so those columns must sort after hydrate.
        if ($this->channelKey() === 'youtube') {
            $fields[] = 'acos_l30';
            $fields[] = 'ad_sales_L30';
            $fields[] = 'spend_lt';
            $fields[] = 'sold_lt';
            $fields[] = 'sales_lt';
            $fields[] = 'acos_lt';
            $fields[] = 'cpc_lt';
            $fields[] = 'ctr_lt';
            $fields[] = 'views_lt';
            $fields[] = 'clicks_lt';
            $fields[] = 'cps_lt';
            $fields[] = 'cpv_lt';
            $fields[] = 'cvr_lt';
        }

        return in_array($field, $fields, true);
    }

    /**
     * @param  \Closure(string): ?int  $invResolver
     * @param  \Closure(string): array<string, mixed>|null  $bgtResolver
     * @return array<string, mixed>
     */
    private function hydrateRawGridRow($row, array $rawRule, \Closure $invResolver, ?\Closure $bgtResolver): array
    {
        $arr = self::rawGridRowToArray($row);
        if (isset($arr['spend_window_micros'])) {
            $arr['metrics_cost_micros'] = (int) $arr['spend_window_micros'];
            unset($arr['spend_window_micros']);
        }
        static::enrichRawRowGoogleShoppingStyle($arr, $rawRule);
        $this->attachInventoryFields($arr, $invResolver);
        $this->attachShoppingBgtParts($arr, $bgtResolver, $rawRule);
        $this->applyRowChannelOverrides($arr);

        return $arr;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function sortHydratedRawGridRows($rows, string $field, string $dir)
    {
        $isString = in_array($field, ['campaign_status', 'campaign_name'], true);

        return $rows->sort(static function ($a, $b) use ($field, $dir, $isString) {
            $av = is_array($a) ? ($a[$field] ?? null) : ($a->{$field} ?? null);
            $bv = is_array($b) ? ($b[$field] ?? null) : ($b->{$field} ?? null);
            if ($isString) {
                $cmp = strcasecmp((string) $av, (string) $bv);
            } else {
                $an = is_numeric($av) ? (float) $av : null;
                $bn = is_numeric($bv) ? (float) $bv : null;
                if ($an === null && $bn === null) {
                    $cmp = 0;
                } elseif ($an === null) {
                    $cmp = -1;
                } elseif ($bn === null) {
                    $cmp = 1;
                } else {
                    $cmp = $an <=> $bn;
                }
            }

            return $dir === 'desc' ? -$cmp : $cmp;
        })->values();
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    private function whereUbColorBand($query, string $ubSqlExpr, string $band): void
    {
        if ($band === 'all') {
            return;
        }
        if ($band === 'green') {
            $query->whereRaw("({$ubSqlExpr}) >= 66 AND ({$ubSqlExpr}) <= 99");

            return;
        }
        if ($band === 'pink') {
            $query->whereRaw("({$ubSqlExpr}) > 99");

            return;
        }
        if ($band === 'red') {
            $query->whereRaw("({$ubSqlExpr}) < 66");

            return;
        }
        if ($band === 'zero') {
            // 0 utilisation: nothing spent / no budget — anything under 0.1% shows as 0% in the grid.
            $query->whereRaw("({$ubSqlExpr}) < 0.1");
        }
    }

    /**
     * Filter rows by computed ACOS L30 band. SQL mirrors {@see enrichRawRowGoogleShoppingStyle}:
     * ROUND(spend)/ROUND(sales)*100 when ROUND(sales) >= 1, else 100 if ROUND(spend) > 0, else 0.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    private function whereAcosColorBand($query, string $band): void
    {
        if ($band === 'all') {
            return;
        }

        $spend = '(COALESCE(agg.sum_micros, 0) / 1000000.0)';
        $sales = static::salesL30SqlExpression();
        $acosExpr = "(CASE "
            ."WHEN ROUND({$sales}) >= 1 THEN (ROUND({$spend}) / ROUND({$sales})) * 100.0 "
            ."WHEN ROUND({$spend}) > 0 THEN 100.0 "
            ."ELSE 0 END)";

        if ($band === 'pink') {
            $query->whereRaw("({$acosExpr}) >= 0 AND ({$acosExpr}) < 10");

            return;
        }
        if ($band === 'green') {
            $query->whereRaw("({$acosExpr}) >= 10 AND ({$acosExpr}) < 20");

            return;
        }
        if ($band === 'blue') {
            $query->whereRaw("({$acosExpr}) >= 20 AND ({$acosExpr}) < 30");

            return;
        }
        if ($band === 'yellow') {
            $query->whereRaw("({$acosExpr}) >= 30 AND ({$acosExpr}) < 40");

            return;
        }
        if ($band === 'orange') {
            $query->whereRaw("({$acosExpr}) >= 40 AND ({$acosExpr}) <= 50");

            return;
        }
        if ($band === 'red') {
            $query->whereRaw("({$acosExpr}) > 50");
        }
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @return array{buckets: array{lt66: int, 66_99: int, gt99: int, na: int}, total: int}
     */
    private function aggregateUb7BucketsFromFilteredQuery($query): array
    {
        $sub = clone $query;
        $sub->reorder();

        $u7 = '(CASE WHEN COALESCE(sq.budget_amount_micros, 0) > 0 THEN sq.l7_spend / ((sq.budget_amount_micros / 1000000.0) * 7.0) * 100.0 ELSE 0 END)';
        $bucket = "(CASE WHEN COALESCE(sq.budget_amount_micros, 0) <= 0 THEN 'na' WHEN ({$u7}) < 66 THEN 'lt66' WHEN ({$u7}) <= 99 THEN '66_99' ELSE 'gt99' END)";

        $outer = DB::query()->fromSub($sub, 'sq');
        $row = $outer->selectRaw(
            'SUM(CASE WHEN ('.$bucket.') = \'lt66\' THEN 1 ELSE 0 END) as c_lt66,'.
            'SUM(CASE WHEN ('.$bucket.') = \'66_99\' THEN 1 ELSE 0 END) as c_mid,'.
            'SUM(CASE WHEN ('.$bucket.') = \'gt99\' THEN 1 ELSE 0 END) as c_gt,'.
            'SUM(CASE WHEN ('.$bucket.') = \'na\' THEN 1 ELSE 0 END) as c_na,'.
            'COUNT(*) as c_tot'
        )->first();

        $lt66 = (int) ($row->c_lt66 ?? 0);
        $mid = (int) ($row->c_mid ?? 0);
        $gt = (int) ($row->c_gt ?? 0);
        $na = (int) ($row->c_na ?? 0);
        $total = (int) ($row->c_tot ?? 0);

        return [
            'buckets' => [
                'lt66' => $lt66,
                '66_99' => $mid,
                'gt99' => $gt,
                'na' => $na,
            ],
            'total' => $total,
        ];
    }

    private function normalizeU7HistoryBucketKey(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $k = trim((string) $raw);

        return in_array($k, ['lt66', '66_99', 'gt99', 'na'], true) ? $k : null;
    }

    /**
     * Weighted totals over the full filtered set (not just the current page).
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $summaryQuery
     * @return array{spi30: float|null, acos_pct: int|null, filtered_row_count: int, active_count: int|null, green_util_l7_count: int|null, avg_ctr: float|null, avg_cvr: float|null}
     */
    private function computeRawGridSummary($summaryQuery): array
    {
        // Green util (L7) = U7% in 66–99% with a positive budget — same band as the grid / U7% mix.
        $ub7Pct = '(CASE WHEN COALESCE(subq.budget_amount_micros, 0) > 0 THEN subq.l7_spend / ((subq.budget_amount_micros / 1000000.0) * 7.0) * 100.0 ELSE 0 END)';
        $greenUtilCase = 'SUM(CASE WHEN COALESCE(subq.budget_amount_micros, 0) > 0 AND ('.$ub7Pct.') >= 66 AND ('.$ub7Pct.') <= 99 THEN 1 ELSE 0 END) AS green_util_l7_count';

        try {
            $sql = $summaryQuery->toSql();
            $bindings = $summaryQuery->getBindings();
            $row = DB::selectOne(
                'SELECT COUNT(*) AS row_count, COALESCE(SUM(subq.spend), 0) AS sum_spend, COALESCE(SUM(subq.sales_l30_agg), 0) AS sum_sales, '.
                'COALESCE(SUM(subq.clicks_sum_30), 0) AS sum_clicks, COALESCE(SUM(subq.impr_sum_30), 0) AS sum_impr, '.
                'COALESCE(SUM(subq.sold_l30_agg), 0) AS sum_sold, '.
                'SUM(CASE WHEN UPPER(TRIM(COALESCE(subq.campaign_status, ""))) = "ENABLED" THEN 1 ELSE 0 END) AS active_count, '.
                $greenUtilCase.' '.
                'FROM ('.$sql.') AS subq',
                $bindings
            );
        } catch (\Throwable) {
            return [
                'spi30' => null,
                'acos_pct' => null,
                'filtered_row_count' => 0,
                'active_count' => null,
                'green_util_l7_count' => null,
                'avg_ctr' => null,
                'avg_cvr' => null,
            ];
        }

        $sumSales = (float) ($row->sum_sales ?? 0);
        $sumSpend = (float) ($row->sum_spend ?? 0);
        $acos = 0.0;
        if ($sumSales >= 1.0) {
            $acos = ($sumSpend / $sumSales) * 100.0;
        } elseif ($sumSpend > 0) {
            $acos = 100.0;
        }

        // Weighted averages over the full filtered set — mirror the toolbar CVR badge
        // (soldSum / clicksSum) and add the same style for CTR (clicksSum / imprSum). These
        // drive the CTR/CVR flag colours: red < avg*0.80, magenta > avg*1.20, green in between.
        $sumClicks = (float) ($row->sum_clicks ?? 0);
        $sumImpr = (float) ($row->sum_impr ?? 0);
        $sumSold = (float) ($row->sum_sold ?? 0);
        $avgCtr = $sumImpr > 0 ? ($sumClicks / $sumImpr) * 100.0 : 0.0;
        $avgCvr = $sumClicks > 0 ? ($sumSold / $sumClicks) * 100.0 : 0.0;

        return [
            'spi30' => round($sumSales, 2),
            'acos_pct' => (int) round($acos),
            'filtered_row_count' => (int) ($row->row_count ?? 0),
            'active_count' => (int) ($row->active_count ?? 0),
            'green_util_l7_count' => (int) ($row->green_util_l7_count ?? 0),
            'avg_ctr' => round($avgCtr, 2),
            'avg_cvr' => round($avgCvr, 2),
        ];
    }

    /**
     * L30 Spend / Sales / Sold for the toolbar badges, counting every campaign status
     * (Enabled, Paused and Removed). All other grid filters (search, UB%, ACOS, CTR/CVR, INV)
     * still apply; only the status filter is ignored.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $collection  rows for the current filters
     * @param  \Closure(string): ?int  $invResolver
     * @return array{spend_l30_all: float, sales_l30_all: float, sold_l30_all: float}
     */
    private function rawGridL30TotalsAllStatuses(Request $request, $collection, string $invFilter, \Closure $invResolver): array
    {
        $rows = $collection;
        if ($this->normalizeStatFilter($request->input('filter_stat')) !== 'all') {
            $allStatus = $request->duplicate();
            $allStatus->merge(['filter_stat' => 'all']);
            $query = $this->buildRawGridBaseQuery();
            $this->applyRawGridDataFilters($query, $allStatus);
            $this->applyRawGridSort($query, $allStatus);
            $rows = $this->rememberRawGridRows($allStatus, $query);
            $rows = $this->filterRawGridRowsByInventory($rows, $invFilter, $invResolver);
            $rows = $this->filterRawGridRowsByPrice($rows, $request, $this->shoppingBgtMetricsResolver());
        }

        $spend = 0.0;
        $sales = 0.0;
        $sold = 0.0;
        foreach ($rows as $row) {
            $arr = self::rawGridRowToArray($row);
            $spend += (float) ($arr['spend'] ?? 0);
            $sales += $this->adjustChannelRowSales($arr, (float) ($arr['sales_l30_agg'] ?? 0));
            $sold += (float) ($arr['sold_l30_agg'] ?? $arr['sum_ga4_actual_sold'] ?? 0);
        }

        return [
            'spend_l30_all' => round($spend, 2),
            'sales_l30_all' => round($sales, 2),
            'sold_l30_all' => round($sold, 2),
        ];
    }

    /**
     * Utilized Y / Utilized L7 badges: bands and averages over the ENABLED (active) campaigns only. The set is
     * built from every status (so the ACTIVE badge can show active / total) and ignores the Sts and UB%
     * filters (those would empty the other bands). Search, ACOS, CTR/CVR and INV filters still apply.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $collection  rows for the current filters
     * @param  \Closure(string): ?int  $invResolver
     * @return array<string, int|float>
     */
    /**
     * Campaigns per SBGT (suggested daily budget) over every campaign in the grid — all statuses, no filters.
     * Feeds the BGT Rules "Sum" card so it is not limited to the rows on the current page.
     *
     * @return list<array{value: float|int, count: int}>
     */
    private function sbgtDistributionAllCampaigns($rawRule, \Closure $invResolver, \Closure $bgtResolver): array
    {
        return $this->sbgtDistributionData($rawRule, $invResolver, $bgtResolver)['dist'];
    }

    /**
     * Campaigns per ACOS value (all statuses, no filters). The BGT Rules "Sum" card re-buckets these with the
     * ACOS bands being edited, so it updates live without a round trip.
     *
     * @return list<array{acos: float, count: int}>
     */
    private function sbgtAcosDistributionAllCampaigns($rawRule, \Closure $invResolver, \Closure $bgtResolver): array
    {
        return $this->sbgtDistributionData($rawRule, $invResolver, $bgtResolver)['acos'];
    }

    /**
     * @return array{dist: list<array{value: float|int, count: int}>, acos: list<array{acos: float, count: int}>}
     */
    private function sbgtDistributionData($rawRule, \Closure $invResolver, \Closure $bgtResolver): array
    {
        $key = 'gads_sbgt_dist_v2:'.$this->channelKey().':'.$this->rawGridRowsCacheVersion().':'.sha1((string) json_encode($this->rawGridDateBoundaries()));

        return Cache::remember($key, now()->addSeconds(60), function () use ($rawRule, $invResolver, $bgtResolver) {
            $empty = Request::create('/', 'GET');
            $query = $this->buildRawGridBaseQuery();
            $this->applyRawGridDataFilters($query, $empty);
            $this->applyRawGridSort($query, $empty);
            $counts = [];
            $acosCounts = [];
            foreach ($this->rememberRawGridRows($empty, $query) as $row) {
                $arr = $this->hydrateRawGridRow($row, $rawRule, $invResolver, $bgtResolver);
                $a = $arr['acos_l30'] ?? null;
                if ($a !== null && is_numeric($a) && is_finite((float) $a)) {
                    $ak = (string) round((float) $a, 6);
                    $acosCounts[$ak] = ($acosCounts[$ak] ?? 0) + 1;
                }
                $v = $arr['sbgt'] ?? null;
                if ($v === null || $v === '' || ! is_numeric($v)) {
                    continue;
                }
                $k = (string) (0 + $v);
                $counts[$k] = ($counts[$k] ?? 0) + 1;
            }
            ksort($counts, SORT_NUMERIC);
            $out = [];
            foreach ($counts as $value => $count) {
                $out[] = ['value' => 0 + $value, 'count' => $count];
            }
            $acosOut = [];
            foreach ($acosCounts as $acos => $count) {
                $acosOut[] = ['acos' => (float) $acos, 'count' => $count];
            }

            return ['dist' => $out, 'acos' => $acosOut];
        });
    }

    private function rawGridUtilizationAllCampaigns(Request $request, $collection, string $invFilter, \Closure $invResolver): array
    {
        $rows = $collection;
        $narrowed = $this->normalizeStatFilter($request->input('filter_stat')) !== 'all'
            || $this->normalizeUbColorFilter($request->input('filter_ub7')) !== 'all'
            || $this->normalizeUbColorFilter($request->input('filter_ub1')) !== 'all'
            || $this->normalizeUbColorFilter($request->input('filter_ub2')) !== 'all';
        if ($narrowed) {
            $allCampaigns = $request->duplicate();
            $allCampaigns->merge(['filter_stat' => 'all', 'filter_ub7' => 'all', 'filter_ub1' => 'all', 'filter_ub2' => 'all']);
            $query = $this->buildRawGridBaseQuery();
            $this->applyRawGridDataFilters($query, $allCampaigns);
            $this->applyRawGridSort($query, $allCampaigns);
            $rows = $this->rememberRawGridRows($allCampaigns, $query);
            $rows = $this->filterRawGridRowsByInventory($rows, $invFilter, $invResolver);
            $rows = $this->filterRawGridRowsByPrice($rows, $request, $this->shoppingBgtMetricsResolver());
        }

        return $this->summarizeRawGridUtilization($rows);
    }

    /**
     * Utilisation bands (same for Y = the grid's "1 UB%" and L7 = "U7%"): red = 0% (nothing spent / no budget),
     * yellow = 0.1–66%, green = 66–99%, pink = over 99%. Under 0.1% shows as 0%, so it counts as red.
     * Average = Σ spend ÷ Σ budget over the campaigns that have a budget.
     * Bands / average / util_enabled_count cover ENABLED campaigns only; util_total_count / util_active_count
     * still cover every status (they feed the ACTIVE badge).
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $rows
     * @return array<string, int|float>
     */
    private function summarizeRawGridUtilization($rows): array
    {
        $y = ['green' => 0, 'yellow' => 0, 'red' => 0, 'pink' => 0];
        $l7 = ['green' => 0, 'yellow' => 0, 'red' => 0, 'pink' => 0];
        $budgetDaily = 0.0;
        $spendL1 = 0.0;
        $spendL7 = 0.0;
        $total = 0;
        $active = 0;
        $band = static function (float $ub): string {
            if ($ub > 99) {
                return 'pink';
            }
            if ($ub >= 66) {
                return 'green';
            }

            return $ub >= 0.1 ? 'yellow' : 'red';
        };

        foreach ($rows as $row) {
            $arr = self::rawGridRowToArray($row);
            $total++;
            if (strtoupper(trim((string) ($arr['campaign_status'] ?? ''))) !== 'ENABLED') {
                continue; // Utilized blocks show active campaigns only
            }
            $active++;
            $bgtMicros = (float) ($arr['budget_amount_micros'] ?? 0);
            $spend1 = (float) ($arr['l1_spend'] ?? 0);
            $spend7 = (float) ($arr['l7_spend'] ?? 0);
            $budget = $bgtMicros / 1000000.0;
            if ($bgtMicros > 0) {
                $budgetDaily += $budget;
                $spendL1 += $spend1;
                $spendL7 += $spend7;
            }
            $y[$band($bgtMicros > 0 ? $spend1 / $budget * 100.0 : 0.0)]++;
            $l7[$band($bgtMicros > 0 ? $spend7 / ($budget * 7.0) * 100.0 : 0.0)]++;
        }

        return [
            'util_total_count' => $total,
            'util_active_count' => $active,
            'util_enabled_count' => $active,
            'avg_util_y_pct' => $budgetDaily > 0 ? round($spendL1 / $budgetDaily * 100.0, 1) : 0.0,
            'avg_util_l7_pct' => $budgetDaily > 0 ? round($spendL7 / ($budgetDaily * 7.0) * 100.0, 1) : 0.0,
            'green_util_y_count' => $y['green'],
            'yellow_util_y_count' => $y['yellow'],
            'red_util_y_count' => $y['red'],
            'pink_util_y_count' => $y['pink'],
            'green_util_l7_count' => $l7['green'],
            'yellow_util_l7_count' => $l7['yellow'],
            'red_util_l7_count' => $l7['red'],
            'pink_util_l7_count' => $l7['pink'],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $rows
     * @return array{spi30: float, acos_pct: int, filtered_row_count: int, active_count: int, avg_ctr: float, avg_cvr: float}
     */
    private function summarizeRawGridRows($rows): array
    {
        $sumSpend = 0.0;
        $sumSales = 0.0;
        $sumClicks = 0.0;
        $sumImpr = 0.0;
        $sumSold = 0.0;
        $active = 0;
        $count = 0;

        foreach ($rows as $row) {
            $arr = self::rawGridRowToArray($row);
            $count++;
            $spend = (float) ($arr['spend'] ?? 0);
            $sales = $this->adjustChannelRowSales($arr, (float) ($arr['sales_l30_agg'] ?? 0));
            $sumSpend += $spend;
            $sumSales += $sales;
            $sumClicks += (float) ($arr['clicks_sum_30'] ?? 0);
            $sumImpr += (float) ($arr['impr_sum_30'] ?? 0);
            $sumSold += (float) ($arr['sold_l30_agg'] ?? $arr['sum_ga4_actual_sold'] ?? 0);
            if (strtoupper(trim((string) ($arr['campaign_status'] ?? ''))) === 'ENABLED') {
                $active++;
            }
        }

        $acos = 0.0;
        if ($sumSales >= 1.0) {
            $acos = ($sumSpend / $sumSales) * 100.0;
        } elseif ($sumSpend > 0) {
            $acos = 100.0;
        }

        return [
            'spi30' => round($sumSales, 2),
            'acos_pct' => (int) round($acos),
            'filtered_row_count' => $count,
            'active_count' => $active,
            'avg_ctr' => $sumImpr > 0 ? round(($sumClicks / $sumImpr) * 100.0, 2) : 0.0,
            'avg_cvr' => $sumClicks > 0 ? round(($sumSold / $sumClicks) * 100.0, 2) : 0.0,
        ];
    }

    /**
     * Optional channel-specific Sales adjustment after SQL aggregation.
     * YouTube uses Shopify price × sold only when transaction value is missing.
     *
     * @param  array<string, mixed>  $arr
     */
    protected function adjustChannelRowSales(array $arr, float $sales): float
    {
        return $sales;
    }

    /**
     * @param  object|array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function rawGridRowToArray($row): array
    {
        if (is_array($row)) {
            return $row;
        }

        return get_object_vars($row);
    }

    /**
     * Cache the filtered campaign set for 60s so paging / refresh does not
     * re-run the 30-day aggregation. Bumped after Pull Data.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    private function rememberRawGridRows(Request $request, $query)
    {
        $key = 'gads_raw_grid_rows_v2:'
            .$this->channelKey().':'
            .$this->rawGridRowsCacheVersion().':'
            .sha1((string) json_encode([
                $this->rawGridDateBoundaries(),
                $request->only([
                    'filter_ub7', 'filter_ub1', 'filter_ub2', 'filter_acos', 'filter_stat',
                    'filter_ctr_min', 'filter_ctr_max', 'filter_cvr_min', 'filter_cvr_max',
                    'q', 'sort', 'sorters',
                ]),
                $request->boolean('filter_verify_id'),
            ]));

        return Cache::remember($key, now()->addSeconds(60), static fn () => $query->get());
    }

    private function rawGridRowsCacheVersion(): int
    {
        return (int) Cache::get('gads_raw_grid_ver:'.$this->channelKey(), 1);
    }

    /**
     * Invalidate cached Shopping / SERP / YouTube grids (shared google_ads_campaigns).
     */
    protected function bumpRawGridRowsCache(): void
    {
        foreach (['shopping', 'serp', 'youtube'] as $channel) {
            $k = 'gads_raw_grid_ver:'.$channel;
            if (! Cache::add($k, 2, now()->addYear())) {
                Cache::increment($k);
            }
        }
    }

    /**
     * 30 inclusive calendar days: default end = latest non-null `date` in the table; optional $forcedEndYmd (Y-m-d) for history.
     * If nothing has a date and no forced end, returns null (no filter — whole table).
     *
     * @return array{start: string, end: string}|null
     */
    private function rawGridDateBoundaries(?string $forcedEndYmd = null): ?array
    {
        if ($forcedEndYmd !== null && trim($forcedEndYmd) !== '') {
            $end = Carbon::parse($forcedEndYmd)->startOfDay();
            $start = $end->copy()->subDays(29);

            return [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ];
        }

        $maxDateStr = DB::table('google_ads_campaigns')->whereNotNull('date')->max('date');
        if ($maxDateStr === null || $maxDateStr === '') {
            return null;
        }

        $end = Carbon::parse($maxDateStr)->startOfDay();
        $start = $end->copy()->subDays(29);

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ];
    }

    /**
     * CPC / ACOS / UB% / SBGT / SBID (SBGT/SBID bands from {@see GoogleShoppingCampaignsRawRule}).
     * Uses raw grid date anchor (max(date) in table) and trailing L7/L2/L1 windows as spend columns.
     *
     * @param  array{sbgt: array<string, float|int>, sbid: array<string, float>}  $rawRule
     */
    /**
     * Per-channel display adjustments applied to an enriched grid row before it is
     * trimmed for Tabulator. Default (Shopping/SERP): no change — the grid keeps the
     * click-based CPC columns. Subclasses (e.g. YouTube) override this to surface
     * view-based CPV instead.
     *
     * @param  array<string, mixed>  $arr
     */
    protected function applyRowChannelOverrides(array &$arr): void
    {
        // CPV / CPS are YouTube-only. Enrich still computes CPV for VIDEO math,
        // but Shopping/SERP must not emit those keys (shared column priority).
        unset(
            $arr['cps_L30'],
            $arr['cpv_L30'],
            $arr['cpv_L7'],
            $arr['cpv_L2'],
            $arr['cpv_L1'],
            $arr['spend_lt'],
            $arr['sold_lt'],
            $arr['sales_lt'],
            $arr['acos_lt'],
            $arr['cpc_lt'],
            $arr['ctr_lt'],
            $arr['views_lt'],
            $arr['video_views_L30'],
            $arr['clicks_lt'],
            $arr['cps_lt'],
            $arr['cpv_lt'],
            $arr['cvr_lt'],
            $arr['video_audit_filled'],
            $arr['video_audit_ai_filled'],
            $arr['video_audit_pct'],
            $arr['yt_category'],
            $arr['yt_audience'],
            $arr['yt_landing']
        );
    }

    private static function enrichRawRowGoogleShoppingStyle(array &$arr, array $rawRule): void
    {
        $spend = (float) ($arr['spend'] ?? 0);
        $l7Spend = (float) ($arr['l7_spend'] ?? 0);
        $l2Spend = (float) ($arr['l2_spend'] ?? 0);
        $l1Spend = (float) ($arr['l1_spend'] ?? 0);

        $clicks30 = (int) ($arr['clicks_sum_30'] ?? 0);
        $clicksL7 = (int) ($arr['clicks_sum_l7'] ?? 0);
        $clicksL2 = (int) ($arr['clicks_sum_l2'] ?? 0);
        $clicksL1 = (int) ($arr['clicks_sum_l1'] ?? 0);
        unset($arr['clicks_sum_30'], $arr['clicks_sum_l7'], $arr['clicks_sum_l2'], $arr['clicks_sum_l1']);

        // Impressions (30d) drive CTR L30 = (clicks / impressions) * 100. Kept in the same
        // 30-day window as Clicks so the column, the flag colour, and the SQL sort/filter agree.
        $impr30 = (int) ($arr['impr_sum_30'] ?? 0);
        unset($arr['impr_sum_30']);

        // TrueView views per window (video campaigns are billed per view, not per click).
        $views30 = (int) ($arr['views_sum_30'] ?? 0);
        $viewsL7 = (int) ($arr['views_sum_l7'] ?? 0);
        $viewsL2 = (int) ($arr['views_sum_l2'] ?? 0);
        $viewsL1 = (int) ($arr['views_sum_l1'] ?? 0);
        unset($arr['views_sum_30'], $arr['views_sum_l7'], $arr['views_sum_l2'], $arr['views_sum_l1']);

        // Show/sum L30 clicks in the grid; g.metrics_clicks is only the latest date row.
        $arr['metrics_clicks'] = $clicks30;
        // CTR L30 — (clicks / impressions) * 100, 2 dp. 0 when there are no impressions.
        $arr['ctr_l30'] = $impr30 > 0 ? round(($clicks30 / $impr30) * 100.0, 2) : 0.0;
        $arr['cpc_L30'] = $clicks30 > 0 ? round($spend / $clicks30, 6) : 0.0;
        $arr['cpc_L7'] = $clicksL7 > 0 ? round($l7Spend / $clicksL7, 6) : 0.0;
        $arr['cpc_L2'] = $clicksL2 > 0 ? round($l2Spend / $clicksL2, 6) : 0.0;
        $arr['cpc_L1'] = $clicksL1 > 0 ? round($l1Spend / $clicksL1, 6) : 0.0;

        // CPV (cost per view) per window — used by the YouTube/TrueView grid, which is
        // billed per view. Shopping/SERP ignore these keys (not in their column priority).
        $arr['cpv_L30'] = $views30 > 0 ? round($spend / $views30, 6) : 0.0;
        $arr['cpv_L7'] = $viewsL7 > 0 ? round($l7Spend / $viewsL7, 6) : 0.0;
        $arr['cpv_L2'] = $viewsL2 > 0 ? round($l2Spend / $viewsL2, 6) : 0.0;
        $arr['cpv_L1'] = $viewsL1 > 0 ? round($l1Spend / $viewsL1, 6) : 0.0;
        $arr['video_views_L30'] = $views30;

        $sumActual = (float) ($arr['sum_ga4_actual'] ?? 0);
        $sumAds = (float) ($arr['sum_ga4_ads'] ?? 0);
        $sumActualSold = (float) ($arr['sum_ga4_actual_sold'] ?? 0);
        $sumAdsSold = (float) ($arr['sum_ga4_ads_sold'] ?? 0);
        unset($arr['sum_ga4_actual'], $arr['sum_ga4_ads'], $arr['sum_ga4_actual_sold'], $arr['sum_ga4_ads_sold']);

        $salesL30 = static::resolveSalesL30Value($sumActual, $sumAds);
        $soldL30 = static::resolveSoldL30Value($sumActualSold, $sumAdsSold);
        $arr['ad_sold_L30'] = $soldL30;
        $arr['ad_sales_L30'] = $salesL30;

        // CVR L30 — mirrors the toolbar CVR badge: (sold / clicks) * 100, 1 dp.
        // Both inputs use the same 30-day window as Sold and Clicks above so the
        // column, the badge, and the SQL sort agree to the percent.
        $arr['cvr_l30'] = $clicks30 > 0 ? round(($soldL30 / $clicks30) * 100.0, 1) : 0.0;

        $spendR = (int) round($spend);
        $salesR = (int) round($salesL30);
        $acos = 0.0;
        if ($salesR >= 1) {
            $acos = ($spendR / $salesR) * 100.0;
        } elseif ($spendR > 0) {
            $acos = 100.0;
        }
        $arr['acos_l30'] = $acos;

        $arr['sbgt'] = GoogleShoppingCampaignsRawRule::sbgtFromAcos($acos, $rawRule);

        $bgt = 0.0;
        if (! empty($arr['budget_amount_micros'])) {
            $bgt = (float) $arr['budget_amount_micros'] / 1000000.0;
        }
        $arr['bgt'] = $bgt;

        $arr['ub7'] = $bgt > 0 ? ($l7Spend / ($bgt * 7.0)) * 100.0 : 0.0;
        $arr['ub2'] = $bgt > 0 ? ($l2Spend / ($bgt * 2.0)) * 100.0 : 0.0;
        $arr['ub1'] = $bgt > 0 ? ($l1Spend / $bgt) * 100.0 : 0.0;

        $cpcL1 = (float) $arr['cpc_L1'];
        $cpcL7 = (float) $arr['cpc_L7'];
        $ub7 = (float) $arr['ub7'];
        $ub1 = (float) $arr['ub1'];

        $arr['sbid'] = GoogleShoppingCampaignsRawRule::sbidFromUb7Ub1Cpc($ub7, $ub1, $cpcL1, $cpcL7, $rawRule);
    }

    /**
     * Last N inclusive calendar days ending at the same `end` as the 30d grid (max date in table).
     * When $bounds30 is null (no dates in table), returns null so the sum uses the same unbounded semantics as Spend.
     *
     * @param  array{start: string, end: string}|null  $bounds30
     * @return array{start: string, end: string}|null
     */
    private function rawTrailingInclusiveDayBounds(?array $bounds30, int $inclusiveDays): ?array
    {
        if ($bounds30 === null || $inclusiveDays < 1) {
            return null;
        }

        $end = Carbon::parse($bounds30['end'])->startOfDay();
        $start = $end->copy()->subDays($inclusiveDays - 1);

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ];
    }

    /**
     * Key order for Tabulator autoColumns. Only keys in rawTabulatorColumnPriority() are emitted
     * (everything after SBID is omitted from the grid payload).
     */
    private static function prepareRawRowForTabulator(array $row): array
    {
        $ordered = [];
        foreach (self::rawTabulatorColumnPriority() as $key) {
            if (array_key_exists($key, $row)) {
                $ordered[$key] = $row[$key];
            }
        }

        return $ordered;
    }

    /**
     * @return list<string>
     */
    private static function rawTabulatorColumnPriority(): array
    {
        return [
            'id',
            'date',
            'campaign_id',
            'campaign_status',
            'yt_category',
            'yt_audience',
            'yt_landing',
            'campaign_name',
            'is_parent',
            'inventory',
            'dil',
            'spend',
            'spend_lt',
            'video_views_L30',
            'views_lt',
            'l7_spend',
            'l2_spend',
            'l1_spend',
            'metrics_clicks',
            'clicks_lt',
            'ctr_l30',
            'ctr_lt',
            'cpc_L30',
            'cpc_lt',
            'live_cpc',
            'live_cpc_tip',
            'cps_L30',
            'cps_lt',
            'cpv_L30',
            'cpv_lt',
            'cpc_L7',
            'cpc_L2',
            'cpc_L1',
            'ad_sold_L30',
            'sold_lt',
            'ad_sales_L30',
            'sales_lt',
            'acos_l30',
            'acos_lt',
            'price',
            'cvr_l30',
            'cvr_lt',
            'ub7',
            'ub2',
            'ub1',
            'bgt',
            'bgt_acos',
            'views_l30',
            'views_l7',
            'bgt_views',
            'bgt_cvr',
            'bgt_prc',
            'bgt_dil',
            'sbgt',
            'sbgt_cap',
            'sbgt_prev',
            'sbgt_prev_date',
            'sbgt_trend',
            'bgt_views_color',
            'bgt_views_label',
            'bgt_cvr_color',
            'bgt_cvr_label',
            'bgt_cvr_page_cvr',
            'bgt_prc_color',
            'bgt_prc_label',
            'bgt_prc_price',
            'bgt_dil_color',
            'bgt_dil_label',
            'bgt_dil_value',
            'ovl30',
            'sbid',
            'lbid',
            'lbgt',
            'lbid_green',
            'lbgt_green',
            'lbid_tip',
            'lbgt_tip',
            'n_targets',
            'video_audit_filled',
            'video_audit_ai_filled',
            'video_audit_pct',
            'id_mismatch',
            'id_status',
            'id_alert_title',
            'ver_attached',
            'ver_total',
            'ver_zero',
            'ver_status',
            'ver_reason',
            'ads_needed',
            'ads_needed_title',
        ];
    }

    /**
     * Negative-keyword counts for the current page. Shopping only — the count is the
     * synced Google Ads list (`app:fetch-google-ads-negative-keywords`), campaign plus ad group.
     *
     * @param  list<string>  $campaignIds
     * @return array<string, int>
     */
    private function negativeTargetCountsForPage(array $campaignIds): array
    {
        if ($this->channelKey() !== 'shopping') {
            return [];
        }

        $ids = [];
        foreach ($campaignIds as $id) {
            $s = trim((string) $id);
            if ($s !== '') {
                $ids[$s] = true;
            }
        }
        $ids = array_keys($ids);
        if ($ids === [] || ! Schema::hasTable('google_ads_negative_keywords')) {
            return array_fill_keys($ids, 0);
        }

        $map = array_fill_keys($ids, 0);
        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = DB::table('google_ads_negative_keywords')
                ->select('campaign_id', DB::raw('COUNT(*) AS c'))
                ->whereIn('campaign_id', $chunk)
                ->whereIn('level', [
                    GoogleAdsNegativeKeyword::LEVEL_CAMPAIGN,
                    GoogleAdsNegativeKeyword::LEVEL_AD_GROUP,
                ])
                ->where(function ($w) {
                    $w->whereNull('status')
                        ->orWhereRaw("UPPER(status) NOT IN ('REMOVED', 'ARCHIVED')");
                })
                ->groupBy('campaign_id')
                ->get();
            foreach ($rows as $row) {
                $map[trim((string) $row->campaign_id)] = (int) $row->c;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $arr
     * @param  array<string, int>  $counts
     */
    private function attachNegativeTargetCount(array &$arr, array $counts): void
    {
        if ($this->channelKey() !== 'shopping') {
            return;
        }
        $cid = trim((string) ($arr['campaign_id'] ?? ''));
        $arr['n_targets'] = $cid !== '' ? (int) ($counts[$cid] ?? 0) : 0;
    }

    /**
     * Attach is_parent + inventory (parent total for PARENT campaigns; child SKU inv otherwise).
     *
     * @param  array<string, mixed>  $arr
     * @param  \Closure(string): ?int  $invResolver
     */
    private function attachInventoryFields(array &$arr, \Closure $invResolver): void
    {
        $name = (string) ($arr['campaign_name'] ?? '');
        $norm = preg_replace('/\s+/', ' ', strtoupper(rtrim(trim($name), '.')));
        $isParent = $norm !== '' && str_starts_with($norm, 'PARENT ');
        $arr['is_parent'] = $isParent;
        $inv = $invResolver($name);
        $arr['inventory'] = $inv !== null ? (int) $inv : 0;
    }

    /**
     * Memoized campaign_name → Shopify INV resolver (Amazon SB / missing-ads style).
     * PARENT … campaigns get SUM(child inv) by product_master.parent; other names match SKU inv.
     *
     * @return \Closure(string): ?int
     */
    private function buildInventoryResolver(): \Closure
    {
        $channel = $this->channelKey();
        $payload = Cache::remember("gads_{$channel}_inv_resolver_v1", 900, function () {
            $allPm = ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get(['sku', 'parent']);

            $childSkus = [];
            foreach ($allPm as $pm) {
                $s = trim((string) ($pm->sku ?? ''));
                if ($s === '' || str_starts_with(strtoupper($s), 'PARENT')) {
                    continue;
                }
                $childSkus[] = $s;
            }
            $shopifyByPmSku = ShopifySku::mapByProductSkus(array_values(array_unique($childSkus)));

            $inventoryByParent = [];
            $skuToParentKey = [];
            foreach ($allPm as $pm) {
                $s = trim((string) ($pm->sku ?? ''));
                if ($s === '' || str_starts_with(strtoupper($s), 'PARENT')) {
                    continue;
                }
                $pKey = preg_replace('/\s+/', ' ', strtoupper(trim((string) ($pm->parent ?? ''))));
                if ($pKey === '') {
                    continue;
                }
                $normSku = preg_replace('/\s+/', ' ', strtoupper(rtrim($s, '.')));
                $skuToParentKey[$normSku] = $pKey;
                $rec = $shopifyByPmSku->get($s);
                $inventoryByParent[$pKey] = ($inventoryByParent[$pKey] ?? 0) + (float) ($rec?->inv ?? 0);
            }

            $parentSkuToFamilyKey = [];
            foreach ($allPm as $pm) {
                $s = trim((string) ($pm->sku ?? ''));
                if ($s === '' || ! str_starts_with(strtoupper($s), 'PARENT')) {
                    continue;
                }
                $normSku = preg_replace('/\s+/', ' ', strtoupper(rtrim($s, '.')));
                $parentCol = trim((string) ($pm->parent ?? ''));
                if ($parentCol !== '') {
                    $parentSkuToFamilyKey[$normSku] = preg_replace('/\s+/', ' ', strtoupper($parentCol));
                } else {
                    $rest = trim(preg_replace('/^PARENT\s+/i', '', $s) ?? '');
                    $parentSkuToFamilyKey[$normSku] = $rest === ''
                        ? $normSku
                        : preg_replace('/\s+/', ' ', strtoupper(rtrim($rest, '.')));
                }
            }

            $childInvBySku = [];
            foreach ($shopifyByPmSku as $sku => $rec) {
                $normSku = preg_replace('/\s+/', ' ', strtoupper(rtrim((string) $sku, '.')));
                if ($normSku !== '') {
                    $childInvBySku[$normSku] = (int) round((float) ($rec->inv ?? 0));
                }
            }

            return [
                'inventoryByParent' => $inventoryByParent,
                'parentSkuToFamilyKey' => $parentSkuToFamilyKey,
                'skuToParentKey' => $skuToParentKey,
                'childInvBySku' => $childInvBySku,
            ];
        });

        $inventoryByParent = $payload['inventoryByParent'];
        $parentSkuToFamilyKey = $payload['parentSkuToFamilyKey'];
        $skuToParentKey = $payload['skuToParentKey'];
        $childInvBySku = $payload['childInvBySku'];
        $memo = [];

        return static function (string $campaignName) use (
            $inventoryByParent,
            $parentSkuToFamilyKey,
            $skuToParentKey,
            $childInvBySku,
            &$memo
        ): ?int {
            $norm = preg_replace('/\s+/', ' ', strtoupper(rtrim(trim($campaignName), '.')));
            if ($norm === '') {
                return null;
            }
            if (array_key_exists($norm, $memo)) {
                return $memo[$norm];
            }

            // PARENT {family} → sum of children's Shopify inv (parent total).
            if (str_starts_with($norm, 'PARENT ')) {
                $fam = $parentSkuToFamilyKey[$norm]
                    ?? preg_replace('/\s+/', ' ', trim(substr($norm, strlen('PARENT '))));
                $out = isset($inventoryByParent[$fam]) ? (int) round($inventoryByParent[$fam]) : 0;
                $memo[$norm] = $out;

                return $out;
            }

            // Child / other campaign name → that SKU's inv when matched; else parent total if linked.
            if (isset($childInvBySku[$norm])) {
                $memo[$norm] = $childInvBySku[$norm];

                return $memo[$norm];
            }
            if (isset($skuToParentKey[$norm])) {
                $fam = $skuToParentKey[$norm];
                $out = isset($inventoryByParent[$fam]) ? (int) round($inventoryByParent[$fam]) : 0;
                $memo[$norm] = $out;

                return $out;
            }

            $memo[$norm] = null;

            return null;
        };
    }

    /**
     * Compare Google Ads listing-group Item IDs vs expected Merchant Center IDs
     * (shopify_us_{productId}_{variantId} from shopify_catalog_variants).
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     */
    private function attachMerchantIdVerification($rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $campaignIds = $rows
            ->pluck('campaign_id')
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values()
            ->all();

        $liveByCampaign = [];
        $apiError = null;
        $customerId = config('services.google_ads.login_customer_id');
        if ($customerId && $campaignIds !== []) {
            try {
                /** @var GoogleAdsSbidService $sbidService */
                $sbidService = app(GoogleAdsSbidService::class);
                sort($campaignIds);
                // Live Ads Item IDs barely change within minutes — cache so re-filtering doesn't re-hit Google Ads.
                $liveByCampaign = Cache::remember(
                    'gads_shopping_live_item_ids_v1:'.((int) Cache::get('gads_shopping_item_ids_gen', 0)).':'.sha1($customerId.'|'.implode(',', $campaignIds)),
                    300,
                    static fn () => $sbidService->fetchShoppingProductItemIdsByCampaignIds($customerId, $campaignIds)
                );
            } catch (\Throwable $e) {
                report($e);
                $apiError = $e->getMessage();
            }
        } elseif (! $customerId) {
            $apiError = 'Google Ads customer ID is not configured.';
        }

        $skusByCampaignName = [];
        $allSkus = [];
        foreach ($rows as $arr) {
            $name = (string) ($arr['campaign_name'] ?? '');
            $skus = $this->childSkusForCampaignName($name);
            $skusByCampaignName[$name] = $skus;
            foreach ($skus as $sku) {
                $allSkus[$sku] = true;
            }
        }
        $expectedBySku = $this->resolveMerchantCenterItemIds(array_keys($allSkus));
        $invResolver = $this->buildInventoryResolver();

        foreach ($rows as $i => $arr) {
            $cid = (string) ($arr['campaign_id'] ?? '');
            $name = (string) ($arr['campaign_name'] ?? '');
            $allChildSkus = $skusByCampaignName[$name] ?? [];
            // SKUs with no stock (INV <= 0) are meant to be out of the ad, so they are not expected / not counted.
            $skus = array_values(array_filter($allChildSkus, static function ($sku) use ($invResolver) {
                $inv = $invResolver($sku);

                return $inv === null || (float) $inv > 0;
            }));
            $zeroInv = count($allChildSkus) - count($skus);
            $noStockOnly = $allChildSkus !== [] && $skus === [];
            $expected = [];
            foreach ($skus as $sku) {
                if (! empty($expectedBySku[$sku])) {
                    $expected[] = $expectedBySku[$sku];
                }
            }
            $expected = array_values(array_unique($expected));
            sort($expected);

            $live = $liveByCampaign[$cid] ?? [];
            sort($live);

            // status: matched (green) · missing = no ID found (yellow) · mismatch / error (red)
            $status = 'matched';
            $tip = 'Item ID matches Merchant Center ('.implode(', ', array_slice($expected, 0, 5)).(count($expected) > 5 ? ', …' : '').')';

            if ($apiError !== null) {
                $status = 'error';
                $tip = 'Could not fetch live Ads Item IDs: '.$apiError;
            } elseif ($noStockOnly) {
                $tip = 'No stock (INV ≤ 0) on every SKU — nothing needs to be attached.';
            } elseif ($expected === []) {
                $status = 'missing';
                $tip = 'No Merchant Center Item ID found in catalog for this campaign SKU(s).';
            } elseif ($live === []) {
                $status = 'missing';
                $tip = 'No product Item ID on Google Ads listing groups. Expected: '.implode(', ', $expected);
            } elseif ($expected !== $live) {
                $status = 'mismatch';
                $missing = array_values(array_diff($expected, $live));
                $extra = array_values(array_diff($live, $expected));
                $parts = [];
                if ($missing !== []) {
                    $parts[] = 'Missing on Ads: '.implode(', ', $missing);
                }
                if ($extra !== []) {
                    $parts[] = 'Unexpected on Ads: '.implode(', ', $extra);
                }
                $tip = $parts !== []
                    ? implode(' | ', $parts)
                    : 'Item ID mismatch vs Merchant Center.';
            }

            // Verification: Items attached on Google Ads (distinct Item IDs) vs SKUs under this campaign/parent.
            $skuTotal = count($skus);
            $skusNoId = array_values(array_filter($skus, static fn ($sku) => empty($expectedBySku[$sku])));
            $attached = $apiError !== null ? null : count($live);
            $arr['ver_attached'] = $attached;
            $arr['ver_total'] = $skuTotal;
            $arr['ver_zero'] = $zeroInv;
            // Green when every SKU with stock is attached; a campaign with no stock at all (INV <= 0) is green too.
            $arr['ver_status'] = $noStockOnly || ($attached !== null && $skuTotal > 0 && $attached === $skuTotal) ? 'match' : 'mismatch';
            // Exact reason shown in the Reason column (+ the last Fix attempt's error, if any).
            if ($noStockOnly) {
                $reason = 'OK — no stock (INV ≤ 0) on any SKU, nothing to attach.';
            } elseif ($status === 'matched' && $arr['ver_status'] === 'match') {
                $reason = "OK — all {$skuTotal} SKU(s) with stock attached with matching Item IDs."
                    .($zeroInv > 0 ? " ({$zeroInv} SKU(s) with INV ≤ 0 not counted.)" : '');
            } elseif ($status === 'matched') {
                $reason = "Item IDs match, but Items attached ({$attached}) ≠ SKUs ({$skuTotal})"
                    .($skusNoId !== [] ? ': no Merchant Center Item ID in catalog for SKU '.implode(', ', array_slice($skusNoId, 0, 5)).(count($skusNoId) > 5 ? ', …' : '') : '.');
            } else {
                $reason = $tip;
                if ($status === 'mismatch' || $status === 'missing') {
                    $reason .= ($skusNoId !== [] && $expected !== [] ? ' | No catalog Item ID for SKU '.implode(', ', array_slice($skusNoId, 0, 5)) : '');
                }
            }
            $fixNote = Cache::get('gads_shopping_fix_note:'.$cid);
            if (is_string($fixNote) && $fixNote !== '') {
                $reason .= ' | Last fix: '.$fixNote;
            }
            $arr['ver_reason'] = $reason;
            $arr['id_status'] = $status;
            $arr['id_mismatch'] = in_array($status, ['mismatch', 'error'], true);
            $arr['id_alert_title'] = $tip;
            $rows[$i] = $arr;
        }
    }

    /**
     * Child SKUs linked to a campaign name (PARENT → all children; else the name as SKU).
     *
     * @return list<string>
     */
    private function childSkusForCampaignName(string $campaignName): array
    {
        static $cache = null;
        if ($cache === null) {
            $cache = Cache::remember('gads_'.$this->channelKey().'_child_skus_by_campaign_v1', 300, function () {
                $allPm = ProductMaster::query()
                    ->whereNotNull('sku')
                    ->where('sku', '!=', '')
                    ->get(['sku', 'parent']);

                $childrenByFamily = [];
                $parentSkuToFamily = [];
                $normToSku = [];
                foreach ($allPm as $pm) {
                    $s = trim((string) ($pm->sku ?? ''));
                    if ($s === '') {
                        continue;
                    }
                    $normSku = preg_replace('/\s+/', ' ', strtoupper(rtrim($s, '.')));
                    if (str_starts_with(strtoupper($s), 'PARENT')) {
                        $parentCol = trim((string) ($pm->parent ?? ''));
                        if ($parentCol !== '') {
                            $parentSkuToFamily[$normSku] = preg_replace('/\s+/', ' ', strtoupper($parentCol));
                        } else {
                            $rest = trim(preg_replace('/^PARENT\s+/i', '', $s) ?? '');
                            $parentSkuToFamily[$normSku] = $rest === ''
                                ? $normSku
                                : preg_replace('/\s+/', ' ', strtoupper(rtrim($rest, '.')));
                        }

                        continue;
                    }
                    if ($normSku !== '' && ! isset($normToSku[$normSku])) {
                        $normToSku[$normSku] = $s;
                    }
                    $fam = preg_replace('/\s+/', ' ', strtoupper(trim((string) ($pm->parent ?? ''))));
                    if ($fam === '') {
                        continue;
                    }
                    $childrenByFamily[$fam][] = $s;
                }

                return [
                    'childrenByFamily' => $childrenByFamily,
                    'parentSkuToFamily' => $parentSkuToFamily,
                    'normToSku' => $normToSku,
                ];
            });
        }

        $norm = preg_replace('/\s+/', ' ', strtoupper(rtrim(trim($campaignName), '.')));
        if ($norm === '') {
            return [];
        }

        if (str_starts_with($norm, 'PARENT ')) {
            $fam = $cache['parentSkuToFamily'][$norm]
                ?? preg_replace('/\s+/', ' ', trim(substr($norm, strlen('PARENT '))));
            $kids = $cache['childrenByFamily'][$fam] ?? [];

            return array_values(array_unique($kids));
        }

        // Campaign name is typically the child SKU (normalize against product_master).
        if (isset($cache['normToSku'][$norm])) {
            return [$cache['normToSku'][$norm]];
        }

        return [trim($campaignName)];
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, string> sku => shopify_us_{productId}_{variantId}
     */
    private function resolveMerchantCenterItemIds(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ))));
        if ($skus === [] || ! Schema::hasTable('shopify_catalog_variants')) {
            return [];
        }

        $out = [];

        $rows = DB::table('shopify_catalog_variants')
            ->whereIn('sku', $skus)
            ->whereNotNull('shopify_product_id')
            ->whereNotNull('shopify_variant_id')
            ->where('shopify_product_id', '!=', '')
            ->where('shopify_variant_id', '!=', '')
            ->orderByRaw("CASE WHEN store = 'main' THEN 0 ELSE 1 END")
            ->orderByDesc('synced_at')
            ->get(['sku', 'shopify_product_id', 'shopify_variant_id']);

        foreach ($rows as $row) {
            $sku = (string) $row->sku;
            if ($sku === '' || isset($out[$sku])) {
                continue;
            }
            $out[$sku] = 'shopify_us_'.$row->shopify_product_id.'_'.$row->shopify_variant_id;
        }

        $missing = array_values(array_filter($skus, static fn ($s) => ! isset($out[$s])));
        if ($missing === []) {
            return $out;
        }

        $shopifyBySku = ShopifySku::mapByProductSkus($missing);
        $variantToSku = [];
        foreach ($missing as $sku) {
            $variantId = trim((string) ($shopifyBySku->get($sku)?->variant_id ?? ''));
            if ($variantId !== '') {
                $variantToSku[$variantId] = $sku;
            }
        }

        if ($variantToSku === []) {
            return $out;
        }

        $byVariant = DB::table('shopify_catalog_variants')
            ->whereIn('shopify_variant_id', array_keys($variantToSku))
            ->whereNotNull('shopify_product_id')
            ->where('shopify_product_id', '!=', '')
            ->orderByRaw("CASE WHEN store = 'main' THEN 0 ELSE 1 END")
            ->orderByDesc('synced_at')
            ->get(['shopify_variant_id', 'shopify_product_id']);

        foreach ($byVariant as $row) {
            $sku = $variantToSku[(string) $row->shopify_variant_id] ?? null;
            if ($sku === null || isset($out[$sku])) {
                continue;
            }
            $out[$sku] = 'shopify_us_'.$row->shopify_product_id.'_'.$row->shopify_variant_id;
        }

        return $out;
    }
}
