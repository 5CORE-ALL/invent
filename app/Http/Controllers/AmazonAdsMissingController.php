<?php

namespace App\Http\Controllers;

use App\Models\AmazonAdsMissingLink;
use App\Models\ShopifySku;
use App\Services\AmazonAdsService;
use App\Support\Marketplace\AmazonAdsMissingLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AmazonAdsMissingController extends Controller
{
    private const SP_TABLE = 'amazon_sp_campaign_reports';

    private const TYPES = ['PT', 'KW'];

    private const SIDEBAR_COUNT_CACHE_KEY = 'amazon_ads_missing_sidebar_count';

    public function index()
    {
        return view('amazon_ads.amz_ads_missing');
    }

    /**
     * Missing PT + Missing KW for in-stock parents (same rules as the page badges).
     * Cached briefly for the left-sidebar badge.
     */
    public static function missingTotalCount(): int
    {
        try {
            $cached = Cache::get(self::SIDEBAR_COUNT_CACHE_KEY);
            if ($cached !== null) {
                return (int) $cached;
            }
        } catch (\Throwable $e) {
            // File cache dirs may be missing mid-request after optimize:clear.
        }

        return 0;
    }

    public static function forgetMissingTotalCache(): void
    {
        Cache::forget(self::SIDEBAR_COUNT_CACHE_KEY);
    }

    /**
     * One synthetic parent row per distinct parent — same method as /amazon-tabulator-view:
     * raw "PARENT …" SKU rows are ignored, children are grouped by their (normalized) parent,
     * and each group yields a single parent row whose SKU is "PARENT {parent}". Inventory is the
     * SUM of the children's Shopify inv. This avoids the duplicate-parent rows that come from
     * multiple / whitespace-variant "PARENT …" SKUs in product_master.
     */
    public function data(): JsonResponse
    {
        if (! Schema::hasTable('product_master')) {
            return response()->json(['data' => []]);
        }

        // Match Product Master page: Eloquent SoftDeletes — skip deleted_at rows.
        $rows = DB::table('product_master')
            ->select('parent', 'sku', 'Values')
            ->whereNull('deleted_at')
            ->orderBy('parent')
            ->orderBy('sku')
            ->get();

        // Inventory per normalized parent = SUM(shopify_skus.inv) over child (non-PARENT) SKUs.
        $inventoryByParent = $this->buildInventorySumByParent($rows);

        // Distinct parents, derived from child rows only (mirrors the tabulator view's grouping).
        // Skip DC products (Values.status = DC).
        $parents = [];
        foreach ($rows as $r) {
            $sku = trim((string) ($r->sku ?? ''));
            if ($sku === '' || Str::startsWith(strtoupper($sku), 'PARENT') || $this->isDcProduct($r)) {
                continue;
            }
            $parent = preg_replace('/\s+/', ' ', trim((string) ($r->parent ?? '')));
            if ($parent === '') {
                continue;
            }
            $parents[strtoupper($parent)] = $parent; // display value keyed by uppercase for dedupe
        }
        ksort($parents);

        // All links grouped by sku ("PARENT {parent}").
        $linksBySku = AmazonAdsMissingLink::orderBy('id')
            ->get()
            ->groupBy(fn ($l) => (string) $l->sku);

        $statusMap = $this->campaignStatusMap();
        $childrenByParent = $this->childrenMetaByParent($rows, $parents);

        $data = collect($parents)->map(function ($parent, $parentKey) use (
            $linksBySku,
            $inventoryByParent,
            $statusMap,
            $childrenByParent
        ) {
            $sku = 'PARENT '.$parent;
            $links = $linksBySku->get($sku, collect());
            $meta = $childrenByParent[$parentKey] ?? ['children' => [], 'target_sku' => ''];

            return [
                'parent' => $parent,
                'sku' => $sku,
                'is_parent' => true,
                'inventory' => (int) round($inventoryByParent[$parentKey] ?? 0),
                'campaign_pick' => '',
                'target_sku' => (string) ($meta['target_sku'] ?? ''),
                'children' => is_array($meta['children'] ?? null) ? $meta['children'] : [],
                'pt' => $this->linkListForType($links, 'PT', $statusMap),
                'kw' => $this->linkListForType($links, 'KW', $statusMap),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    /**
     * Distinct SP campaigns (same source as /amazon-ads/all SP reports) for the Campaign picker.
     */
    public function campaigns(): JsonResponse
    {
        if (! Schema::hasTable(self::SP_TABLE) || ! Schema::hasColumn(self::SP_TABLE, 'campaignName')) {
            return response()->json(['data' => []]);
        }

        $rows = DB::table(self::SP_TABLE)
            ->selectRaw('campaignName AS campaign_name, MAX(campaign_id) AS campaign_id')
            ->whereNotNull('campaignName')
            ->where('campaignName', '!=', '')
            ->groupBy('campaignName')
            ->orderBy('campaignName')
            ->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * Link a campaign to a SKU as PT or KW. Campaign id is resolved from the SP reports table by name.
     */
    public function link(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:PT,KW'],
            'campaign_name' => ['required', 'string', 'max:255'],
        ]);

        $campaignId = null;
        if (Schema::hasTable(self::SP_TABLE) && Schema::hasColumn(self::SP_TABLE, 'campaignName')) {
            $campaignId = DB::table(self::SP_TABLE)
                ->where('campaignName', $validated['campaign_name'])
                ->max('campaign_id');
        }

        AmazonAdsMissingLink::firstOrCreate(
            [
                'sku' => $validated['sku'],
                'type' => $validated['type'],
                'campaign_name' => $validated['campaign_name'],
            ],
            [
                'campaign_id' => $campaignId,
                'user_id' => Auth::id(),
                'created_at' => Carbon::now(),
            ]
        );

        self::forgetMissingTotalCache();

        return response()->json($this->linksResponseForSku($validated['sku']));
    }

    /**
     * Remove a linked campaign by its id.
     */
    public function unlink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $link = AmazonAdsMissingLink::find($validated['id']);
        $sku = $link?->sku;
        if ($link) {
            $link->delete();
            self::forgetMissingTotalCache();
        }

        return response()->json($this->linksResponseForSku((string) $sku));
    }

    /**
     * Create one PAUSED KW/MANUAL Sponsored Products campaign for a parent with product ads
     * for selected child seller SKUs (Amazon seller rule: product ads use sku, not ASIN).
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent' => ['required', 'string', 'max:255'],
            'campaign_name' => ['nullable', 'string', 'max:128'],
            'budget_amount' => ['nullable', 'numeric', 'min:1'],
            'default_bid' => ['nullable', 'numeric', 'min:0.02'],
            'type' => ['nullable', 'string', 'in:PT,KW'],
            'children' => ['required', 'array', 'min:1'],
            'children.*.target_sku' => ['required', 'string', 'max:255'],
            'children.*.asin' => ['nullable', 'string', 'max:32'],
        ]);

        $parent = preg_replace('/\s+/', ' ', trim($validated['parent']));
        $sku = 'PARENT '.$parent;
        $type = 'KW';

        $campaignName = trim((string) ($validated['campaign_name'] ?? ''));
        if ($campaignName === '') {
            $campaignName = $sku;
        }
        $campaignName = mb_substr($campaignName, 0, 128);

        $sellerSkus = [];
        $resolvedChildren = [];
        foreach ($validated['children'] as $child) {
            $targetSku = trim((string) ($child['target_sku'] ?? ''));
            if ($targetSku === '') {
                continue;
            }
            $asin = strtoupper(trim((string) ($child['asin'] ?? '')));
            if ($asin === '') {
                $asin = (string) ($this->resolveAsinForSku($targetSku) ?? '');
            }
            if ($asin === '') {
                return response()->json([
                    'ok' => false,
                    'message' => 'No Amazon ASIN found for SKU: '.$targetSku.'. Add it in Amazon datasheets first.',
                ], 422);
            }
            $sellerSkus[] = $targetSku;
            $resolvedChildren[] = [
                'target_sku' => $targetSku,
                'asin' => $asin,
            ];
        }
        $sellerSkus = array_values(array_unique($sellerSkus));
        if ($sellerSkus === []) {
            return response()->json([
                'ok' => false,
                'message' => 'Select at least one child SKU with a valid Amazon ASIN.',
            ], 422);
        }

        $campaignName = $this->ensureUniqueCampaignName($campaignName);
        $budget = (float) ($validated['budget_amount'] ?? 3);
        $defaultBid = (float) ($validated['default_bid'] ?? 0.60);
        // Amazon rule: positive keywords require MANUAL; AUTO is for PT-style product ads.
        $targetingType = $type === 'KW' ? 'MANUAL' : 'AUTO';

        $created = null;
        $lastError = '';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $tryName = $attempt === 0
                ? $campaignName
                : mb_substr($campaignName.' '.($attempt + 1), 0, 128);

            $created = app(AmazonAdsService::class)->createPausedAutoCampaignWithProductAds(
                $tryName,
                $sellerSkus,
                $budget,
                $defaultBid,
                $targetingType
            );

            if (! empty($created['success'])) {
                $campaignName = (string) ($created['campaign_name'] ?? $tryName);
                break;
            }

            $lastError = (string) ($created['message'] ?? 'Amazon create failed.');
            if (stripos($lastError, 'duplicate') === false && stripos($lastError, 'unique') === false) {
                break;
            }
            $campaignName = $tryName;
        }

        if (empty($created['success'])) {
            Log::error('Amazon Ads missing create failed', [
                'parent' => $parent,
                'campaign_name' => $campaignName,
                'skus' => $sellerSkus,
                'error' => $lastError,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Amazon Ads create failed: '.($lastError !== '' ? $lastError : 'Unknown error.'),
            ], 500);
        }

        $campaignId = (string) ($created['campaign_id'] ?? '');
        AmazonAdsMissingLink::firstOrCreate(
            [
                'sku' => $sku,
                'type' => $type,
                'campaign_name' => $campaignName,
            ],
            [
                'campaign_id' => $campaignId !== '' ? $campaignId : null,
                'user_id' => Auth::id(),
                'created_at' => Carbon::now(),
            ]
        );

        $this->upsertLocalSpCampaignRow($campaignId, $campaignName, 'PAUSED', $budget);
        self::forgetMissingTotalCache();

        $adsCreated = (int) ($created['product_ads_created'] ?? 0);
        $adGroupId = (string) ($created['ad_group_id'] ?? '');
        $extra = '';
        if (! empty($created['errors']) && is_array($created['errors'])) {
            $extra = ' Warnings: '.implode(' | ', array_slice($created['errors'], 0, 2));
        }

        return response()->json([
            'ok' => true,
            'message' => $targetingType.' SP campaign created (PAUSED): '.$campaignName
                .' with '.$adsCreated.' product ad(s).'.$extra,
            'parent' => $parent,
            'sku' => $sku,
            'type' => $type,
            'targeting_type' => $targetingType,
            'campaign_name' => $campaignName,
            'campaign_id' => $campaignId,
            'ad_group_id' => $adGroupId,
            'children' => $resolvedChildren,
            'campaign' => $created,
            'pt' => $this->linksResponseForSku($sku)['pt'],
            'kw' => $this->linksResponseForSku($sku)['kw'],
        ]);
    }

    /**
     * AI-suggested negative keywords for an Amazon AUTO/SP campaign.
     */
    public function aiNegativeKeywords(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent' => ['required', 'string', 'max:255'],
            'target_sku' => ['nullable', 'string', 'max:255'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'ideas' => ['nullable', 'string', 'max:2000'],
            'mode' => ['nullable', 'string', 'in:generate,add_more'],
            'already_suggested' => ['nullable', 'array'],
            'already_suggested.*' => ['string', 'max:255'],
        ]);

        $parent = preg_replace('/\s+/', ' ', trim($validated['parent']));
        $targetSku = trim((string) ($validated['target_sku'] ?? ''));
        $campaignName = trim((string) ($validated['campaign_name'] ?? ('PARENT '.$parent)));
        $ideas = trim((string) ($validated['ideas'] ?? ''));
        $mode = ($validated['mode'] ?? 'generate') === 'add_more' ? 'add_more' : 'generate';
        $alreadySuggested = collect($validated['already_suggested'] ?? [])
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique(fn ($t) => strtolower($t))
            ->values()
            ->all();

        if ($mode === 'add_more' && $ideas === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Enter ideas to add more negative keywords.',
            ], 422);
        }

        $productTitle = $this->resolveProductTitleForAi($parent, $targetSku);
        $existing = $this->existingAmazonNegativesForParent($parent);

        $apiKey = (string) (config('services.anthropic.key') ?: config('services.claude.key') ?: '');
        if ($apiKey === '') {
            return response()->json([
                'ok' => false,
                'message' => 'CLAUDE_API_KEY / ANTHROPIC_API_KEY is not configured.',
            ], 503);
        }

        $existingList = $existing === [] ? '(none)' : implode(', ', array_slice($existing, 0, 80));
        $alreadyList = $alreadySuggested === [] ? '(none)' : implode(', ', array_slice($alreadySuggested, 0, 120));
        $ideasBlock = $ideas !== ''
            ? "Media buyer ideas / themes to expand into negatives:\n{$ideas}"
            : 'Media buyer ideas: (none provided)';

        if ($mode === 'add_more') {
            $task = <<<TASK
Task: Expand the media buyer ideas into ADDITIONAL negative keywords for this Amazon Sponsored Products campaign.
Return 15–30 NEW negatives inspired by those ideas (and closely related variants).
Do NOT repeat Existing Amazon KW negatives or Already suggested negatives.
TASK;
        } else {
            $task = <<<TASK
Task: Suggest negative keywords so this Amazon AUTO Sponsored Products campaign does NOT attract irrelevant search traffic (wrong product types, free/cheap/diy, incompatible uses, competitor brands, unrelated accessories).
If media buyer ideas are provided, prioritize and expand those themes, then fill with other strong negatives.
Return 25–40 short, high-value negative keyword phrases.
TASK;
        }

        $prompt = <<<PROMPT
You are an Amazon Advertising Sponsored Products negative-keyword strategist for an e-commerce brand (5 Core / musical gear & accessories).

Product parent: {$parent}
Target SKU: {$targetSku}
Campaign name: {$campaignName}
Product title/context: {$productTitle}
Existing Amazon KW negatives already linked for this parent: {$existingList}
Already suggested negatives (do not repeat): {$alreadyList}
{$ideasBlock}

{$task}

Amazon rules to respect in suggestions:
- Prefer 1–4 word phrase/exact style negatives (Amazon campaign negatives support NEGATIVE_PHRASE and NEGATIVE_EXACT only — not broad).
- Do NOT repeat items already listed above (case-insensitive).
- Do NOT include the product's own brand/core model terms that should remain eligible.
- Output ONLY valid JSON (no markdown fences): {"negatives":["keyword one","keyword two"]}
PROMPT;

        try {
            $model = (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001');
            $version = (string) config('services.anthropic.version', '2023-06-01');
            $response = Http::timeout(90)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => $version,
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 2000,
                    'temperature' => 0.4,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                $errorType = (string) data_get($response->json(), 'error.type', '');
                $errorMessage = (string) data_get($response->json(), 'error.message', '');
                Log::warning('Amazon AI negatives Claude failed', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 500),
                    'model' => $model,
                ]);

                $message = $errorType === 'not_found_error'
                    ? "Claude model '{$model}' is not available. Update ANTHROPIC_MODEL in .env."
                    : ($errorMessage !== '' ? $errorMessage : 'Claude request failed (HTTP '.$response->status().').');

                return response()->json([
                    'ok' => false,
                    'message' => $message,
                ], 502);
            }

            $content = (string) data_get($response->json(), 'content.0.text', '');
            $content = trim($content);
            if (preg_match('/\{.*\}/s', $content, $m)) {
                $content = $m[0];
            }
            $parsed = json_decode($content, true);
            $suggested = [];
            if (is_array($parsed) && isset($parsed['negatives']) && is_array($parsed['negatives'])) {
                $skipLookup = [];
                foreach (array_merge($existing, $alreadySuggested) as $ex) {
                    $skipLookup[strtolower(trim((string) $ex))] = true;
                }
                foreach ($parsed['negatives'] as $kw) {
                    $kw = trim((string) $kw);
                    if ($kw === '') {
                        continue;
                    }
                    $key = strtolower($kw);
                    if (isset($skipLookup[$key])) {
                        continue;
                    }
                    $skipLookup[$key] = true;
                    $suggested[] = $kw;
                }
            }

            return response()->json([
                'ok' => true,
                'parent' => $parent,
                'target_sku' => $targetSku,
                'product_title' => $productTitle,
                'existing' => $existing,
                'existing_count' => count($existing),
                'suggested' => $suggested,
                'suggested_count' => count($suggested),
                'mode' => $mode,
            ]);
        } catch (\Throwable $e) {
            Log::error('Amazon AI negatives generation error', ['error' => $e->getMessage()]);

            return response()->json([
                'ok' => false,
                'message' => 'AI generation failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Push negative keywords to the parent's Amazon SP campaign (campaign-level).
     * Amazon campaign negatives: NEGATIVE_PHRASE / NEGATIVE_EXACT only.
     */
    public function pushNegativeKeywords(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent' => ['required', 'string', 'max:255'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'campaign_id' => ['nullable', 'string', 'max:64'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:255'],
            'include_existing' => ['nullable', 'boolean'],
            'match_type' => ['nullable', 'string', 'in:PHRASE,EXACT,NEGATIVE_PHRASE,NEGATIVE_EXACT'],
        ]);

        $parent = preg_replace('/\s+/', ' ', trim($validated['parent']));
        $sku = 'PARENT '.$parent;
        $campaignName = trim((string) ($validated['campaign_name'] ?? ''));
        if ($campaignName === '') {
            $campaignName = $sku;
        }

        $matchType = strtoupper(trim((string) ($validated['match_type'] ?? 'PHRASE')));
        $amazonMatch = match ($matchType) {
            'EXACT', 'NEGATIVE_EXACT' => 'NEGATIVE_EXACT',
            default => 'NEGATIVE_PHRASE',
        };

        $keywords = collect($validated['keywords'] ?? [])
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique(fn ($t) => strtolower($t))
            ->values()
            ->all();

        if (! empty($validated['include_existing'])) {
            $existing = $this->existingAmazonNegativesForParent($parent);
            $existingLookup = [];
            foreach ($existing as $ex) {
                $existingLookup[strtolower(trim((string) $ex))] = true;
            }
            // Drop request keywords that already appear in Amazon KW(-) — they are merged once below.
            $keywords = array_values(array_filter(
                $keywords,
                static fn ($t) => ! isset($existingLookup[strtolower($t)])
            ));
            $keywords = collect(array_merge($keywords, $existing))
                ->map(fn ($t) => trim((string) $t))
                ->filter()
                ->unique(fn ($t) => strtolower($t))
                ->values()
                ->all();
        }

        if ($keywords === []) {
            return response()->json([
                'ok' => false,
                'message' => 'No negative keywords to push. Generate AI suggestions first (or include existing KW negatives).',
            ], 422);
        }

        $campaignId = preg_replace('/\D+/', '', trim((string) ($validated['campaign_id'] ?? ''))) ?: '';
        if ($campaignId === '') {
            // Prefer exact name, then PT/KW suffix variants, then any linked campaign for parent.
            $resolved = $this->resolveCampaignIdForParent($sku, $campaignName, null);
            $campaignId = $resolved['campaign_id'] ?? '';
            if (($resolved['campaign_name'] ?? '') !== '') {
                $campaignName = $resolved['campaign_name'];
            }
        }

        if ($campaignId === '') {
            return response()->json([
                'ok' => false,
                'message' => 'No Amazon SP campaign found for this parent. Click "Create campaign" first (or link an existing PT/KW campaign), then push negatives.',
            ], 422);
        }

        try {
            $result = app(AmazonAdsService::class)->pushCampaignNegativeKeywords(
                $campaignId,
                $keywords,
                $amazonMatch
            );
        } catch (\Throwable $e) {
            Log::error('Amazon Ads push negatives failed', [
                'parent' => $parent,
                'campaign_id' => $campaignId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Amazon Ads push failed: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => (bool) ($result['success'] ?? false),
            'message' => (string) ($result['message'] ?? 'Done.'),
            'parent' => $parent,
            'sku' => $sku,
            'campaign_id' => $campaignId,
            'campaign_name' => $campaignName,
            'match_type' => $amazonMatch,
            'added' => (int) ($result['added'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'duplicates' => (int) ($result['duplicates'] ?? 0),
        ], ! empty($result['success']) ? 200 : 422);
    }

    /**
     * AI-suggested positive keywords for an Amazon MANUAL/KW campaign.
     */
    public function aiPositiveKeywords(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent' => ['required', 'string', 'max:255'],
            'target_sku' => ['nullable', 'string', 'max:255'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'ideas' => ['nullable', 'string', 'max:2000'],
            'mode' => ['nullable', 'string', 'in:generate,add_more'],
            'already_suggested' => ['nullable', 'array'],
            'already_suggested.*' => ['string', 'max:255'],
        ]);

        $parent = preg_replace('/\s+/', ' ', trim($validated['parent']));
        $targetSku = trim((string) ($validated['target_sku'] ?? ''));
        $campaignName = trim((string) ($validated['campaign_name'] ?? ('PARENT '.$parent)));
        $ideas = trim((string) ($validated['ideas'] ?? ''));
        $mode = ($validated['mode'] ?? 'generate') === 'add_more' ? 'add_more' : 'generate';
        $alreadySuggested = collect($validated['already_suggested'] ?? [])
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique(fn ($t) => strtolower($t))
            ->values()
            ->all();

        if ($mode === 'add_more' && $ideas === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Enter ideas to add more positive keywords.',
            ], 422);
        }

        $productTitle = $this->resolveProductTitleForAi($parent, $targetSku);
        $existing = $this->existingAmazonPositivesForParent($parent);

        $apiKey = (string) (config('services.anthropic.key') ?: config('services.claude.key') ?: '');
        if ($apiKey === '') {
            return response()->json([
                'ok' => false,
                'message' => 'CLAUDE_API_KEY / ANTHROPIC_API_KEY is not configured.',
            ], 503);
        }

        $existingList = $existing === [] ? '(none)' : implode(', ', array_slice($existing, 0, 80));
        $alreadyList = $alreadySuggested === [] ? '(none)' : implode(', ', array_slice($alreadySuggested, 0, 120));
        $ideasBlock = $ideas !== ''
            ? "Media buyer ideas / themes to expand into positive keywords:\n{$ideas}"
            : 'Media buyer ideas: (none provided)';

        if ($mode === 'add_more') {
            $task = <<<TASK
Task: Expand the media buyer ideas into ADDITIONAL positive (bid-on) keywords for this Amazon Sponsored Products MANUAL campaign.
Return 15–30 NEW high-intent search keywords inspired by those ideas.
Do NOT repeat Existing Amazon KW positives or Already suggested keywords.
TASK;
        } else {
            $task = <<<TASK
Task: Suggest positive keywords shoppers would use to find this product on Amazon (core product terms, use-cases, relevant accessories that still convert for this SKU).
If media buyer ideas are provided, prioritize and expand those themes, then fill with other strong positives.
Return 25–40 short, high-intent keyword phrases (1–4 words). Avoid irrelevant/competitor-only terms.
TASK;
        }

        $prompt = <<<PROMPT
You are an Amazon Advertising Sponsored Products keyword strategist for an e-commerce brand (5 Core / musical gear & accessories).

Product parent: {$parent}
Target SKU: {$targetSku}
Campaign name: {$campaignName}
Product title/context: {$productTitle}
Existing Amazon KW positive keywords already linked for this parent: {$existingList}
Already suggested positives (do not repeat): {$alreadyList}
{$ideasBlock}

{$task}

Amazon rules to respect in suggestions:
- Prefer 1–4 word phrases suitable for BROAD / PHRASE / EXACT match types.
- Do NOT repeat items already listed above (case-insensitive).
- Do NOT include obvious negative/irrelevant terms (free, diy, cheap knockoffs, wrong product types).
- Output ONLY valid JSON (no markdown fences): {"positives":["keyword one","keyword two"]}
PROMPT;

        try {
            $model = (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001');
            $version = (string) config('services.anthropic.version', '2023-06-01');
            $response = Http::timeout(90)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => $version,
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 2000,
                    'temperature' => 0.4,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                $errorType = (string) data_get($response->json(), 'error.type', '');
                $errorMessage = (string) data_get($response->json(), 'error.message', '');
                Log::warning('Amazon AI positives Claude failed', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 500),
                    'model' => $model,
                ]);

                $message = $errorType === 'not_found_error'
                    ? "Claude model '{$model}' is not available. Update ANTHROPIC_MODEL in .env."
                    : ($errorMessage !== '' ? $errorMessage : 'Claude request failed (HTTP '.$response->status().').');

                return response()->json([
                    'ok' => false,
                    'message' => $message,
                ], 502);
            }

            $content = (string) data_get($response->json(), 'content.0.text', '');
            $content = trim($content);
            if (preg_match('/\{.*\}/s', $content, $m)) {
                $content = $m[0];
            }
            $parsed = json_decode($content, true);
            $suggested = [];
            $rawList = [];
            if (is_array($parsed)) {
                if (isset($parsed['positives']) && is_array($parsed['positives'])) {
                    $rawList = $parsed['positives'];
                } elseif (isset($parsed['keywords']) && is_array($parsed['keywords'])) {
                    $rawList = $parsed['keywords'];
                } elseif (isset($parsed['negatives']) && is_array($parsed['negatives'])) {
                    // tolerate model mix-up
                    $rawList = $parsed['negatives'];
                }
            }
            $skipLookup = [];
            foreach (array_merge($existing, $alreadySuggested) as $ex) {
                $skipLookup[strtolower(trim((string) $ex))] = true;
            }
            foreach ($rawList as $kw) {
                $kw = trim((string) $kw);
                if ($kw === '') {
                    continue;
                }
                $key = strtolower($kw);
                if (isset($skipLookup[$key])) {
                    continue;
                }
                $skipLookup[$key] = true;
                $suggested[] = $kw;
            }

            return response()->json([
                'ok' => true,
                'parent' => $parent,
                'target_sku' => $targetSku,
                'product_title' => $productTitle,
                'existing' => $existing,
                'existing_count' => count($existing),
                'suggested' => $suggested,
                'suggested_count' => count($suggested),
                'mode' => $mode,
            ]);
        } catch (\Throwable $e) {
            Log::error('Amazon AI positives generation error', ['error' => $e->getMessage()]);

            return response()->json([
                'ok' => false,
                'message' => 'AI generation failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Push positive keywords to the parent's Amazon MANUAL/KW campaign ad group.
     * Match types: BROAD / PHRASE / EXACT.
     */
    public function pushPositiveKeywords(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent' => ['required', 'string', 'max:255'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'campaign_id' => ['nullable', 'string', 'max:64'],
            'ad_group_id' => ['nullable', 'string', 'max:64'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:255'],
            'include_existing' => ['nullable', 'boolean'],
            'match_type' => ['nullable', 'string', 'in:BROAD,PHRASE,EXACT'],
            'bid' => ['nullable', 'numeric', 'min:0.02'],
        ]);

        $parent = preg_replace('/\s+/', ' ', trim($validated['parent']));
        $sku = 'PARENT '.$parent;
        $campaignName = trim((string) ($validated['campaign_name'] ?? ''));
        if ($campaignName === '') {
            $campaignName = $sku;
        }

        $matchType = strtoupper(trim((string) ($validated['match_type'] ?? 'PHRASE')));
        if (! in_array($matchType, ['BROAD', 'PHRASE', 'EXACT'], true)) {
            $matchType = 'PHRASE';
        }
        $bid = (float) ($validated['bid'] ?? 0.50);

        $keywords = collect($validated['keywords'] ?? [])
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique(fn ($t) => strtolower($t))
            ->values()
            ->all();

        if (! empty($validated['include_existing'])) {
            $existing = $this->existingAmazonPositivesForParent($parent);
            $existingLookup = [];
            foreach ($existing as $ex) {
                $existingLookup[strtolower(trim((string) $ex))] = true;
            }
            $keywords = array_values(array_filter(
                $keywords,
                static fn ($t) => ! isset($existingLookup[strtolower($t)])
            ));
            $keywords = collect(array_merge($keywords, $existing))
                ->map(fn ($t) => trim((string) $t))
                ->filter()
                ->unique(fn ($t) => strtolower($t))
                ->values()
                ->all();
        }

        if ($keywords === []) {
            return response()->json([
                'ok' => false,
                'message' => 'No positive keywords to push. Generate AI suggestions first (or include existing KW positives).',
            ], 422);
        }

        $campaignId = preg_replace('/\D+/', '', trim((string) ($validated['campaign_id'] ?? ''))) ?: '';
        if ($campaignId === '') {
            $resolved = $this->resolveCampaignIdForParent($sku, $campaignName, 'KW');
            if (($resolved['campaign_id'] ?? '') === '') {
                $resolved = $this->resolveCampaignIdForParent($sku, $campaignName, null);
            }
            $campaignId = $resolved['campaign_id'] ?? '';
            if (($resolved['campaign_name'] ?? '') !== '') {
                $campaignName = $resolved['campaign_name'];
            }
        }

        if ($campaignId === '') {
            return response()->json([
                'ok' => false,
                'message' => 'No Amazon SP campaign found for this parent. Create a KW (MANUAL) campaign first, then push positive keywords.',
            ], 422);
        }

        $adGroupId = preg_replace('/\D+/', '', trim((string) ($validated['ad_group_id'] ?? ''))) ?: '';
        $service = app(AmazonAdsService::class);
        if ($adGroupId === '') {
            $adGroupId = $service->resolvePrimaryAdGroupId($campaignId);
        }
        if ($adGroupId === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Could not resolve an ad group for this campaign. Create the campaign again, then push positives.',
            ], 422);
        }

        try {
            $result = $service->pushAdGroupPositiveKeywords(
                $campaignId,
                $adGroupId,
                $keywords,
                $matchType,
                $bid
            );
        } catch (\Throwable $e) {
            Log::error('Amazon Ads push positives failed', [
                'parent' => $parent,
                'campaign_id' => $campaignId,
                'ad_group_id' => $adGroupId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Amazon Ads push failed: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => (bool) ($result['success'] ?? false),
            'message' => (string) ($result['message'] ?? 'Done.'),
            'parent' => $parent,
            'sku' => $sku,
            'campaign_id' => $campaignId,
            'ad_group_id' => $adGroupId,
            'campaign_name' => $campaignName,
            'match_type' => $matchType,
            'added' => (int) ($result['added'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'duplicates' => (int) ($result['duplicates'] ?? 0),
        ], ! empty($result['success']) ? 200 : 422);
    }

    /**
     * Archive campaign in Amazon Ads (no hard delete) and unlink locally.
     */
    public function delete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:255'],
            'campaign_id' => ['nullable', 'string', 'max:64'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'link_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'in:PT,KW'],
        ]);

        $sku = trim($validated['sku']);
        $campaignName = trim((string) ($validated['campaign_name'] ?? ''));
        $campaignId = preg_replace('/\D+/', '', trim((string) ($validated['campaign_id'] ?? ''))) ?: '';
        $type = strtoupper(trim((string) ($validated['type'] ?? '')));

        if ($campaignId === '' && $campaignName !== '') {
            $resolved = $this->resolveCampaignIdForParent($sku, $campaignName, $type !== '' ? $type : null);
            $campaignId = $resolved['campaign_id'] ?? '';
            if (($resolved['campaign_name'] ?? '') !== '') {
                $campaignName = $resolved['campaign_name'];
            }
        }

        if ($campaignId === '' && ! empty($validated['link_id'])) {
            $link = AmazonAdsMissingLink::query()->where('id', (int) $validated['link_id'])->first();
            if ($link) {
                $campaignId = preg_replace('/\D+/', '', trim((string) ($link->campaign_id ?? ''))) ?: '';
                if ($campaignName === '') {
                    $campaignName = trim((string) ($link->campaign_name ?? ''));
                }
                if ($type === '') {
                    $type = (string) ($link->type ?? '');
                }
                if ($campaignId === '' && $campaignName !== '') {
                    $resolved = $this->resolveCampaignIdForParent($sku, $campaignName, $type !== '' ? $type : null);
                    $campaignId = $resolved['campaign_id'] ?? '';
                }
            }
        }

        if ($campaignId === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Could not resolve campaign ID to archive. Provide campaign_id or a known campaign_name.',
            ], 422);
        }

        try {
            $archived = app(AmazonAdsService::class)->archiveCampaign($campaignId);
        } catch (\Throwable $e) {
            Log::error('Amazon Ads missing archive failed', [
                'sku' => $sku,
                'campaign_id' => $campaignId,
                'campaign_name' => $campaignName,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Amazon Ads archive failed: '.$e->getMessage(),
            ], 500);
        }

        if (empty($archived['success'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Amazon Ads archive failed: '.((string) ($archived['message'] ?? 'Unknown error.')),
            ], 500);
        }

        $linkQuery = AmazonAdsMissingLink::query()->where('sku', $sku);
        $linkQuery->where(function ($q) use ($campaignId, $campaignName, $validated) {
            $q->where('campaign_id', $campaignId);
            if ($campaignName !== '') {
                $q->orWhere('campaign_name', $campaignName);
            }
            if (! empty($validated['link_id'])) {
                $q->orWhere('id', (int) $validated['link_id']);
            }
        })->delete();

        if (Schema::hasTable(self::SP_TABLE) && Schema::hasColumn(self::SP_TABLE, 'campaignStatus')) {
            DB::table(self::SP_TABLE)
                ->where('campaign_id', $campaignId)
                ->update(['campaignStatus' => 'ARCHIVED']);
        }

        self::forgetMissingTotalCache();
        $links = $this->linksResponseForSku($sku);

        return response()->json([
            'ok' => true,
            'message' => 'Campaign archived in Amazon Ads'
                .($campaignName !== '' ? ': '.$campaignName : ' (ID '.$campaignId.')').'.',
            'sku' => $sku,
            'campaign_id' => $campaignId,
            'campaign_name' => $campaignName,
            'pt' => $links['pt'],
            'kw' => $links['kw'],
        ]);
    }

    /**
     * Count Missing PT + Missing KW across in-stock parent rows (inventory > 0).
     */
    private function computeMissingTotal(): int
    {
        if (! Schema::hasTable('product_master')) {
            return 0;
        }

        $rows = DB::table('product_master')
            ->select('parent', 'sku', 'Values')
            ->whereNull('deleted_at')
            ->orderBy('parent')
            ->orderBy('sku')
            ->get();

        $inventoryByParent = $this->buildInventorySumByParent($rows);

        $parents = [];
        foreach ($rows as $r) {
            $sku = trim((string) ($r->sku ?? ''));
            if ($sku === '' || Str::startsWith(strtoupper($sku), 'PARENT') || $this->isDcProduct($r)) {
                continue;
            }
            $parent = preg_replace('/\s+/', ' ', trim((string) ($r->parent ?? '')));
            if ($parent === '') {
                continue;
            }
            $parents[strtoupper($parent)] = $parent;
        }

        $linkedTypesBySku = AmazonAdsMissingLink::query()
            ->select('sku', 'type')
            ->get()
            ->groupBy(fn ($l) => (string) $l->sku)
            ->map(function ($links) {
                return $links->pluck('type')->map(fn ($t) => (string) $t)->unique()->all();
            });

        $total = 0;
        foreach ($parents as $parentKey => $parent) {
            if ((int) round($inventoryByParent[$parentKey] ?? 0) <= 0) {
                continue;
            }
            $types = $linkedTypesBySku->get('PARENT '.$parent, []);
            if (! in_array('PT', $types, true)) {
                $total++;
            }
            if (! in_array('KW', $types, true)) {
                $total++;
            }
        }

        return $total;
    }

    /**
     * SUM(shopify_skus.inv) for child (non-PARENT) SKUs, keyed by normalized-uppercase parent name
     * (whitespace collapsed) so it lines up with the grouped parent rows.
     *
     * @param  \Illuminate\Support\Collection  $rows  product_master rows with parent + sku
     * @return array<string, float>
     */
    private function buildInventorySumByParent($rows): array
    {
        $childSkus = [];
        foreach ($rows as $r) {
            $s = trim((string) ($r->sku ?? ''));
            if ($s === '' || Str::startsWith(strtoupper($s), 'PARENT') || $this->isDcProduct($r)) {
                continue;
            }
            $childSkus[] = $s;
        }

        $shopifyByPmSku = ShopifySku::mapByProductSkus(array_values(array_unique($childSkus)));

        $totals = [];
        foreach ($rows as $r) {
            $s = trim((string) ($r->sku ?? ''));
            if ($s === '' || Str::startsWith(strtoupper($s), 'PARENT') || $this->isDcProduct($r)) {
                continue;
            }
            $pKey = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($r->parent ?? ''))));
            if ($pKey === '') {
                continue;
            }
            $rec = $shopifyByPmSku->get($s);
            $totals[$pKey] = ($totals[$pKey] ?? 0) + (float) ($rec?->inv ?? 0);
        }

        return $totals;
    }

    /**
     * True when product_master.Values.status is DC (discontinued).
     */
    private function isDcProduct(object $row): bool
    {
        $values = $row->Values ?? null;
        if (is_string($values)) {
            $values = json_decode($values, true);
        }
        if (! is_array($values)) {
            return false;
        }

        return strtoupper(trim((string) ($values['status'] ?? ''))) === 'DC';
    }

    /**
     * @return array{ok: bool, sku: string, pt: array, kw: array}
     */
    private function linksResponseForSku(string $sku): array
    {
        return AmazonAdsMissingLinks::listsResponseForSku($sku);
    }

    /**
     * @param  \Illuminate\Support\Collection  $links
     * @param  array<string, string>  $statusMap  normalized campaign name => ENABLED/PAUSED
     * @return array<int, array{id: int, campaign_id: ?string, campaign_name: string, status: string, dot: string}>
     */
    private function linkListForType($links, string $type, array $statusMap = []): array
    {
        return AmazonAdsMissingLinks::linkListForType($links, $type, $statusMap);
    }

    /**
     * Latest campaignStatus (ENABLED / PAUSED / …) per SP campaign, keyed by normalized campaign name.
     * Same source as /amazon-ads/all so the dot beside each linked campaign matches that page's status.
     *
     * @return array<string, string>
     */
    private function campaignStatusMap(): array
    {
        return AmazonAdsMissingLinks::campaignStatusMap();
    }

    /**
     * Normalize a campaign name for status lookups: collapse whitespace, drop a trailing period, upper-case.
     */
    private function normalizeCampaignName(string $name): string
    {
        return AmazonAdsMissingLinks::normalizeCampaignName($name);
    }

    /**
     * Child SKUs + ASINs for create modal, keyed by normalized parent.
     *
     * @param  \Illuminate\Support\Collection  $productRows
     * @param  array<string, string>  $parents
     * @return array<string, array{target_sku: string, children: list<array{target_sku: string, asin: string, inv: float}>}>
     */
    private function childrenMetaByParent($productRows, array $parents): array
    {
        $childrenByParent = [];
        foreach ($productRows as $r) {
            $s = trim((string) ($r->sku ?? ''));
            if ($s === '' || Str::startsWith(strtoupper($s), 'PARENT') || $this->isDcProduct($r)) {
                continue;
            }
            $pKey = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($r->parent ?? ''))));
            if ($pKey === '' || ! isset($parents[$pKey])) {
                continue;
            }
            $childrenByParent[$pKey][] = $s;
        }

        $allChildSkus = [];
        foreach ($childrenByParent as $list) {
            foreach ($list as $s) {
                $allChildSkus[] = $s;
            }
        }
        $allChildSkus = array_values(array_unique($allChildSkus));

        $shopifyBySku = $allChildSkus === []
            ? collect()
            : ShopifySku::mapByProductSkus($allChildSkus);
        $asinBySku = $this->resolveAsinsForSkus($allChildSkus);

        $out = [];
        foreach ($parents as $pKey => $parent) {
            $children = array_values(array_unique($childrenByParent[$pKey] ?? []));
            sort($children, SORT_NATURAL | SORT_FLAG_CASE);

            $childRows = [];
            $targetSku = '';
            $fallbackSku = '';
            foreach ($children as $childSku) {
                if ($fallbackSku === '') {
                    $fallbackSku = $childSku;
                }
                $inv = (float) ($shopifyBySku->get($childSku)?->inv ?? 0);
                if ($targetSku === '' && $inv > 0) {
                    $targetSku = $childSku;
                }
                $childRows[] = [
                    'target_sku' => $childSku,
                    'asin' => (string) ($asinBySku[$childSku] ?? ''),
                    'inv' => $inv,
                ];
            }
            if ($targetSku === '') {
                $targetSku = $fallbackSku;
            }

            $out[$pKey] = [
                'target_sku' => $targetSku,
                'children' => $childRows,
            ];
        }

        return $out;
    }

    private function resolveAsinForSku(string $sku): ?string
    {
        $map = $this->resolveAsinsForSkus([$sku]);

        return $map[$sku] ?? null;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, string> sku => ASIN
     */
    private function resolveAsinsForSkus(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ))));
        if ($skus === []) {
            return [];
        }

        $out = [];
        if (Schema::hasTable('amazon_datsheets')) {
            DB::table('amazon_datsheets')
                ->whereIn('sku', $skus)
                ->whereNotNull('asin')
                ->where('asin', '!=', '')
                ->orderByDesc('id')
                ->get(['sku', 'asin'])
                ->each(function ($row) use (&$out) {
                    $sku = trim((string) ($row->sku ?? ''));
                    $asin = strtoupper(trim((string) ($row->asin ?? '')));
                    if ($sku === '' || $asin === '' || isset($out[$sku])) {
                        return;
                    }
                    $out[$sku] = $asin;
                });
        }

        $missing = array_values(array_filter($skus, static fn ($s) => ! isset($out[$s])));
        if ($missing !== [] && Schema::hasTable('amazon_sp_product_ads')) {
            DB::table('amazon_sp_product_ads')
                ->whereIn('sku', $missing)
                ->whereNotNull('asin')
                ->where('asin', '!=', '')
                ->orderByDesc('id')
                ->get(['sku', 'asin'])
                ->each(function ($row) use (&$out) {
                    $sku = trim((string) ($row->sku ?? ''));
                    $asin = strtoupper(trim((string) ($row->asin ?? '')));
                    if ($sku === '' || $asin === '' || isset($out[$sku])) {
                        return;
                    }
                    $out[$sku] = $asin;
                });
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function existingAmazonNegativesForParent(string $parent): array
    {
        $linkSku = 'PARENT '.$parent;
        if (! Schema::hasTable('amazon_ads_missing_links') || ! Schema::hasTable('amazon_sp_negative_keywords')) {
            return [];
        }

        $campaignNames = AmazonAdsMissingLink::query()
            ->where('sku', $linkSku)
            ->where('type', 'KW')
            ->pluck('campaign_name')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($campaignNames === []) {
            return [];
        }

        return DB::table('amazon_sp_negative_keywords')
            ->whereIn('campaignName', $campaignNames)
            ->whereNotNull('keywordText')
            ->where('keywordText', '!=', '')
            ->distinct()
            ->orderBy('keywordText')
            ->limit(200)
            ->pluck('keywordText')
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Existing positive keywords from linked KW campaigns (amazon_sp_keyword_reports).
     *
     * @return list<string>
     */
    private function existingAmazonPositivesForParent(string $parent): array
    {
        $linkSku = 'PARENT '.$parent;
        if (! Schema::hasTable('amazon_ads_missing_links') || ! Schema::hasTable('amazon_sp_keyword_reports')) {
            return [];
        }

        $campaignNames = AmazonAdsMissingLink::query()
            ->where('sku', $linkSku)
            ->where('type', 'KW')
            ->pluck('campaign_name')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($campaignNames === []) {
            return [];
        }

        $q = DB::table('amazon_sp_keyword_reports')
            ->whereIn('campaignName', $campaignNames)
            ->whereNotNull('keyword')
            ->where('keyword', '!=', '');

        if (Schema::hasColumn('amazon_sp_keyword_reports', 'matchType')) {
            $q->whereIn(DB::raw('UPPER(matchType)'), ['BROAD', 'PHRASE', 'EXACT']);
        }

        return $q->distinct()
            ->orderBy('keyword')
            ->limit(200)
            ->pluck('keyword')
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->values()
            ->all();
    }

    private function resolveProductTitleForAi(string $parent, string $targetSku): string
    {
        if ($targetSku !== '' && Schema::hasTable('amazon_datsheets')) {
            $title = DB::table('amazon_datsheets')
                ->where('sku', $targetSku)
                ->whereNotNull('amazon_title')
                ->where('amazon_title', '!=', '')
                ->orderByDesc('id')
                ->value('amazon_title');
            if (is_string($title) && trim($title) !== '') {
                return trim($title);
            }
        }

        return $parent.($targetSku !== '' ? ' / '.$targetSku : '');
    }

    /**
     * Resolve an Amazon SP campaign id for push/archive.
     * Tries: exact name + type → name variants (PT/KW) → latest PT link → latest any link → SP reports.
     *
     * @return array{campaign_id: string, campaign_name: string}
     */
    private function resolveCampaignIdForParent(string $sku, string $campaignName, ?string $type = null): array
    {
        $campaignName = trim($campaignName);
        $nameCandidates = $this->campaignNameLookupCandidates($sku, $campaignName);

        // 1) Exact / variant name match on missing links (optionally typed).
        foreach ($nameCandidates as $name) {
            $q = AmazonAdsMissingLink::query()->where('sku', $sku)->where('campaign_name', $name);
            if ($type !== null && $type !== '') {
                $q->where('type', $type);
            }
            $link = $q->orderByDesc('id')->first();
            if ($link) {
                $id = preg_replace('/\D+/', '', trim((string) ($link->campaign_id ?? ''))) ?: '';
                $resolvedName = trim((string) ($link->campaign_name ?? $name));
                if ($id !== '') {
                    return ['campaign_id' => $id, 'campaign_name' => $resolvedName];
                }
                // Name matched but id missing — look up id from SP reports by that name.
                $fromReports = $this->campaignIdFromSpReports($resolvedName !== '' ? $resolvedName : $name);
                if ($fromReports !== '') {
                    return ['campaign_id' => $fromReports, 'campaign_name' => $resolvedName !== '' ? $resolvedName : $name];
                }
            }
        }

        // 2) Latest linked campaign for this parent with a campaign_id (PT preferred, then KW, then any).
        $typeOrder = [];
        if ($type !== null && $type !== '') {
            $typeOrder[] = strtoupper($type);
        }
        foreach (['PT', 'KW'] as $t) {
            if (! in_array($t, $typeOrder, true)) {
                $typeOrder[] = $t;
            }
        }
        foreach ($typeOrder as $t) {
            $link = AmazonAdsMissingLink::query()
                ->where('sku', $sku)
                ->where('type', $t)
                ->whereNotNull('campaign_id')
                ->where('campaign_id', '!=', '')
                ->orderByDesc('id')
                ->first();
            if ($link) {
                $id = preg_replace('/\D+/', '', trim((string) ($link->campaign_id ?? ''))) ?: '';
                if ($id !== '') {
                    return [
                        'campaign_id' => $id,
                        'campaign_name' => trim((string) ($link->campaign_name ?? $campaignName)),
                    ];
                }
            }
        }

        $anyLink = AmazonAdsMissingLink::query()
            ->where('sku', $sku)
            ->orderByDesc('id')
            ->first();
        if ($anyLink) {
            $id = preg_replace('/\D+/', '', trim((string) ($anyLink->campaign_id ?? ''))) ?: '';
            $name = trim((string) ($anyLink->campaign_name ?? $campaignName));
            if ($id !== '') {
                return ['campaign_id' => $id, 'campaign_name' => $name];
            }
            if ($name !== '') {
                $fromReports = $this->campaignIdFromSpReports($name);
                if ($fromReports !== '') {
                    return ['campaign_id' => $fromReports, 'campaign_name' => $name];
                }
            }
        }

        // 3) SP reports by candidate names.
        foreach ($nameCandidates as $name) {
            $id = $this->campaignIdFromSpReports($name);
            if ($id !== '') {
                return ['campaign_id' => $id, 'campaign_name' => $name];
            }
        }

        return ['campaign_id' => '', 'campaign_name' => $campaignName];
    }

    /**
     * @return list<string>
     */
    private function campaignNameLookupCandidates(string $sku, string $campaignName): array
    {
        $base = preg_replace('/\s+(PT|KW)$/i', '', trim($campaignName));
        $base = trim(preg_replace('/\s+/', ' ', (string) $base));
        $skuBase = preg_replace('/\s+(PT|KW)$/i', '', trim($sku));
        $skuBase = trim(preg_replace('/\s+/', ' ', (string) $skuBase));

        $candidates = [];
        foreach ([
            $campaignName,
            $base,
            $base !== '' ? $base.' PT' : '',
            $base !== '' ? $base.' KW' : '',
            $sku,
            $skuBase,
            $skuBase !== '' ? $skuBase.' PT' : '',
            $skuBase !== '' ? $skuBase.' KW' : '',
        ] as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $candidates[$name] = true;
        }

        return array_keys($candidates);
    }

    private function campaignIdFromSpReports(string $campaignName): string
    {
        $campaignName = trim($campaignName);
        if ($campaignName === '' || ! Schema::hasTable(self::SP_TABLE) || ! Schema::hasColumn(self::SP_TABLE, 'campaignName')) {
            return '';
        }

        $id = DB::table(self::SP_TABLE)
            ->where('campaignName', $campaignName)
            ->where(function ($q) {
                $q->whereNull('campaignStatus')
                    ->orWhere('campaignStatus', '!=', 'ARCHIVED');
            })
            ->max('campaign_id');

        return preg_replace('/\D+/', '', trim((string) $id)) ?: '';
    }

    private function ensureUniqueCampaignName(string $base): string
    {
        $base = mb_substr(trim($base), 0, 128);
        if ($base === '') {
            return 'PARENT';
        }

        $existing = [];
        if (Schema::hasTable(self::SP_TABLE) && Schema::hasColumn(self::SP_TABLE, 'campaignName')) {
            $existing = DB::table(self::SP_TABLE)
                ->whereNotNull('campaignName')
                ->where('campaignName', '!=', '')
                ->distinct()
                ->pluck('campaignName')
                ->map(fn ($n) => strtoupper(trim((string) $n)))
                ->filter()
                ->flip()
                ->all();
        }

        $linked = AmazonAdsMissingLink::query()
            ->pluck('campaign_name')
            ->map(fn ($n) => strtoupper(trim((string) $n)))
            ->filter()
            ->flip()
            ->all();
        $existing = $existing + $linked;

        $candidate = $base;
        $i = 2;
        while (isset($existing[strtoupper($candidate)]) && $i < 50) {
            $candidate = mb_substr($base.' '.$i, 0, 128);
            $i++;
        }

        return $candidate;
    }

    private function upsertLocalSpCampaignRow(
        string $campaignId,
        string $campaignName,
        string $status,
        float $budget
    ): void {
        if ($campaignId === '' || $campaignName === '' || ! Schema::hasTable(self::SP_TABLE)) {
            return;
        }

        try {
            $exists = DB::table(self::SP_TABLE)->where('campaign_id', $campaignId)->exists();
            $payload = [
                'campaignName' => $campaignName,
                'campaignStatus' => $status,
                'updated_at' => now(),
            ];
            if (Schema::hasColumn(self::SP_TABLE, 'campaignBudgetAmount')) {
                $payload['campaignBudgetAmount'] = $budget;
            }
            if (Schema::hasColumn(self::SP_TABLE, 'ad_type')) {
                $payload['ad_type'] = 'SP';
            }

            if ($exists) {
                DB::table(self::SP_TABLE)->where('campaign_id', $campaignId)->update($payload);

                return;
            }

            $payload['campaign_id'] = $campaignId;
            $payload['created_at'] = now();
            DB::table(self::SP_TABLE)->insert($payload);
        } catch (\Throwable $e) {
            Log::warning('Amazon Ads missing: could not upsert local SP campaign row', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
