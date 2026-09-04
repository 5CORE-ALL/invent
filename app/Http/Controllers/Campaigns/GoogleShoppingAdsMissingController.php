<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\AmazonAdsMissingLink;
use App\Models\GoogleAdsMissingLink;
use App\Models\ShopifyB2CListingStatus;
use App\Models\ShopifySku;
use App\Services\GoogleAdsSbidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GoogleShoppingAdsMissingController extends Controller
{
    private const CAMPAIGNS_TABLE = 'google_ads_campaigns';

    private const CHANNEL = 'shopping';

    private const SIDEBAR_COUNT_CACHE_KEY = 'google_shopping_ads_missing_sidebar_count';

    /** @var Collection<string, Collection<int, GoogleAdsMissingLink>>|null */
    private ?Collection $manualLinksCache = null;

    /** @var array<string, string>|null */
    private ?array $campaignStatusMapCache = null;

    /**
     * Shopping campaigns from /google/shopping/google-shopping, keyed by normalized name.
     *
     * @var array<string, array{campaign_name: string, campaign_id: ?string, status: string}>|null
     */
    private ?array $shoppingCampaignIndexCache = null;

    public function index()
    {
        return view('campaign.google_shopping_ads_missing');
    }

    /**
     * In-stock parents with no auto-matched or manually linked Google Shopping campaign.
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

        try {
            $total = (new static)->computeMissingTotal();
            try {
                Cache::put(self::SIDEBAR_COUNT_CACHE_KEY, $total, now()->addMinutes(5));
            } catch (\Throwable $e) {
                // ignore
            }

            return $total;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function forgetMissingTotalCache(): void
    {
        Cache::forget(self::SIDEBAR_COUNT_CACHE_KEY);
    }

    /**
     * One synthetic parent row per distinct parent — same method as /amazon-ads/missing:
     * soft-deleted and DC rows skipped; inventory = SUM child Shopify inv.
     * Campaigns = auto-match from /google/shopping/google-shopping (name = PARENT {parent})
     * plus any manual links (via +).
     */
    public function data(): JsonResponse
    {
        if (! Schema::hasTable('product_master')) {
            return response()->json(['data' => []]);
        }

        $rows = DB::table('product_master')
            ->select('parent', 'sku', 'Values')
            ->whereNull('deleted_at')
            ->orderBy('parent')
            ->orderBy('sku')
            ->get();

        $metricsByParent = $this->buildShopifyMetricsByParent($rows);
        $inventoryByParent = $metricsByParent['inventory'];
        $priceByParent = $metricsByParent['price'];

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
        ksort($parents);

        $manualBySku = $this->manualLinksBySku();
        $statusMap = $this->campaignStatusMapForLinks($manualBySku);

        // Same KW(-) source as /purchase-master/ads-link:
        // amazon_ads_missing_links (type=KW) + amazon_sp_negative_keywords counts.
        $parentSkus = array_values(array_map(static fn ($p) => 'PARENT '.$p, $parents));
        $kwNegBySku = $this->adsLinkNegativeCountsByParentSku($parentSkus);
        $shopifyMetaByParent = $this->shopifyB2cMetaByParent($rows, $parents);

        $defaultMerchantId = (int) (DB::table(self::CAMPAIGNS_TABLE)
            ->where('advertising_channel_type', 'SHOPPING')
            ->whereNotNull('shopping_merchant_id')
            ->orderByDesc('id')
            ->value('shopping_merchant_id') ?: 198980051);

        $data = collect($parents)->map(function ($parent, $parentKey) use (
            $inventoryByParent,
            $priceByParent,
            $manualBySku,
            $statusMap,
            $kwNegBySku,
            $shopifyMetaByParent,
            $defaultMerchantId
        ) {
            $sku = 'PARENT '.$parent;
            $neg = $kwNegBySku[$sku] ?? ['negative_count' => 0, 'kw_campaigns' => []];
            $meta = $shopifyMetaByParent[$parentKey] ?? [
                'buyer_link' => '',
                'seller_link' => '',
                'target_sku' => '',
                'merchant_item_id' => '',
                'children' => [],
            ];
            $children = is_array($meta['children'] ?? null) ? $meta['children'] : [];

            return [
                'parent' => $parent,
                'sku' => $sku,
                'is_parent' => true,
                'inventory' => (int) round($inventoryByParent[$parentKey] ?? 0),
                // Same Shopify price source as /shopify-b2c-pricing (shopify_skus.price);
                // parent row = avg of children with price > 0 (same as B2C parent summary).
                'price' => (float) ($priceByParent[$parentKey] ?? 0),
                'negative_count' => (int) ($neg['negative_count'] ?? 0),
                'kw_campaigns' => $neg['kw_campaigns'] ?? [],
                'buyer_link' => $meta['buyer_link'] ?? '',
                'seller_link' => $meta['seller_link'] ?? '',
                'target_sku' => $meta['target_sku'] ?? '',
                // Google Merchant Center offer id, e.g. shopify_us_{productId}_{variantId}
                'merchant_item_id' => $meta['merchant_item_id'] ?? '',
                'suggested_campaign_name' => $sku,
                // All child SKUs included under one parent campaign.
                'children' => $children,
                'default_merchant_id' => $defaultMerchantId,
                'campaigns' => $this->campaignsForParentSku($sku, $manualBySku->get($sku) ?? collect(), $statusMap),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    /**
     * Create one Google Shopping campaign for a parent (PAUSED), including all
     * selected child SKUs as product Item IDs under that single campaign.
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent' => ['required', 'string', 'max:255'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'budget_amount' => ['nullable', 'numeric', 'min:1'],
            'cpc_bid' => ['nullable', 'numeric', 'min:0.01'],
            'merchant_id' => ['nullable', 'integer', 'min:1'],
            'campaign_priority' => ['nullable', 'integer', 'min:0', 'max:2'],
            'feed_label' => ['nullable', 'string', 'max:32'],
            'buyer_link' => ['nullable', 'string', 'max:2000'],
            'children' => ['required', 'array', 'min:1'],
            'children.*.target_sku' => ['required', 'string', 'max:255'],
            'children.*.item_id' => ['nullable', 'string', 'max:255'],
        ]);

        $parent = preg_replace('/\s+/', ' ', trim($validated['parent']));
        $sku = 'PARENT '.$parent;
        $campaignName = trim((string) ($validated['campaign_name'] ?? ''));
        if ($campaignName === '') {
            $campaignName = $sku;
        }

        $existing = $this->autoMatchedShoppingCampaign($sku);
        if ($existing !== null) {
            return response()->json([
                'ok' => false,
                'message' => 'A Shopping campaign already exists for this parent: '.$existing['campaign_name'],
            ], 422);
        }

        $resolvedChildren = [];
        $itemIds = [];
        foreach ($validated['children'] as $child) {
            $targetSku = trim((string) ($child['target_sku'] ?? ''));
            if ($targetSku === '') {
                continue;
            }
            $itemId = trim((string) ($child['item_id'] ?? ''));
            if ($itemId === '' || ! str_starts_with($itemId, 'shopify_us_')) {
                if (str_starts_with($targetSku, 'shopify_us_')) {
                    $itemId = $targetSku;
                } else {
                    $itemId = (string) ($this->resolveMerchantCenterItemId($targetSku) ?? '');
                }
            }
            if ($itemId === '' || ! str_starts_with($itemId, 'shopify_us_')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Could not resolve Merchant Center Item ID for SKU: '.$targetSku,
                ], 422);
            }
            $resolvedChildren[] = [
                'target_sku' => $targetSku,
                'item_id' => $itemId,
            ];
            $itemIds[] = $itemId;
        }
        $itemIds = array_values(array_unique($itemIds));

        if ($itemIds === []) {
            return response()->json([
                'ok' => false,
                'message' => 'Select at least one child SKU with a valid Merchant Center Item ID.',
            ], 422);
        }

        $campaignName = $this->ensureUniqueCampaignName($campaignName);

        $customerId = str_replace('-', '', (string) config('services.google_ads.login_customer_id'));
        if ($customerId === '') {
            return response()->json(['ok' => false, 'message' => 'Google Ads customer ID is not configured.'], 500);
        }

        $merchantId = (int) ($validated['merchant_id'] ?? 0);
        if ($merchantId <= 0) {
            $merchantId = (int) (DB::table(self::CAMPAIGNS_TABLE)
                ->where('advertising_channel_type', 'SHOPPING')
                ->whereNotNull('shopping_merchant_id')
                ->orderByDesc('id')
                ->value('shopping_merchant_id') ?: 198980051);
        }

        try {
            $created = $this->createShoppingCampaignWithUniqueNameRetry($customerId, [
                'campaign_name' => $campaignName,
                'budget_amount' => (float) ($validated['budget_amount'] ?? 1),
                'cpc_bid' => (float) ($validated['cpc_bid'] ?? 0.5),
                'merchant_id' => $merchantId,
                'campaign_priority' => (int) ($validated['campaign_priority'] ?? 0),
                'feed_label' => $validated['feed_label'] ?? 'US',
                'enable_local' => true,
                'item_ids' => $itemIds,
            ]);
            $campaignName = (string) ($created['campaign_name'] ?? $campaignName);
        } catch (\Throwable $e) {
            Log::error('Google Shopping missing create failed', [
                'parent' => $parent,
                'campaign_name' => $campaignName,
                'item_ids' => $itemIds,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Google Ads create failed: '.$this->formatGoogleAdsCreateError($e),
            ], 500);
        }

        GoogleAdsMissingLink::firstOrCreate(
            [
                'channel' => self::CHANNEL,
                'sku' => $sku,
                'campaign_name' => $campaignName,
            ],
            [
                'campaign_id' => $created['campaign_id'] ?: null,
                'user_id' => Auth::id(),
                'created_at' => Carbon::now(),
            ]
        );

        $this->manualLinksCache = null;
        $this->campaignStatusMapCache = null;
        $this->shoppingCampaignIndexCache = null;
        self::forgetMissingTotalCache();

        return response()->json([
            'ok' => true,
            'message' => 'Shopping campaign created (PAUSED): '.$campaignName
                .' with '.count($itemIds).' child Item ID(s).',
            'parent' => $parent,
            'sku' => $sku,
            'campaign_name' => $campaignName,
            'item_ids' => $itemIds,
            'children' => $resolvedChildren,
            'buyer_link' => $validated['buyer_link'] ?? '',
            'campaign' => $created,
            'campaigns' => $this->campaignsResponseForSku($sku)['campaigns'],
        ]);
    }

    /**
     * AI-suggested negative keywords for a parent/product (shown from Create modal).
     * Also returns existing Amazon KW(-) negatives for the same parent when available.
     */
    public function aiNegativeKeywords(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent' => ['required', 'string', 'max:255'],
            'target_sku' => ['nullable', 'string', 'max:255'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'buyer_link' => ['nullable', 'string', 'max:2000'],
            'ideas' => ['nullable', 'string', 'max:2000'],
            'mode' => ['nullable', 'string', 'in:generate,add_more'],
            'already_suggested' => ['nullable', 'array'],
            'already_suggested.*' => ['string', 'max:255'],
        ]);

        $parent = preg_replace('/\s+/', ' ', trim($validated['parent']));
        $targetSku = trim((string) ($validated['target_sku'] ?? ''));
        $campaignName = trim((string) ($validated['campaign_name'] ?? ('PARENT '.$parent)));
        $buyerLink = trim((string) ($validated['buyer_link'] ?? ''));
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
Task: Expand the media buyer ideas into ADDITIONAL negative keywords for this Shopping campaign.
Return 15–30 NEW negatives inspired by those ideas (and closely related variants).
Do NOT repeat Existing Amazon KW negatives or Already suggested negatives.
TASK;
        } else {
            $task = <<<TASK
Task: Suggest negative keywords that should be added so this Shopping campaign does NOT attract irrelevant traffic (wrong product types, free/cheap/diy, incompatible uses, competitor brands, unrelated accessories).
If media buyer ideas are provided, prioritize and expand those themes, then fill with other strong negatives.
Return 25–40 short, high-value negative keyword phrases.
TASK;
        }

        $prompt = <<<PROMPT
You are a Google Shopping / Google Ads negative-keyword strategist for an e-commerce brand (5 Core / musical gear & accessories).

Product parent: {$parent}
Target SKU: {$targetSku}
Campaign name: {$campaignName}
Product title/context: {$productTitle}
Buyer page URL: {$buyerLink}
Existing Amazon KW negatives already linked for this parent: {$existingList}
Already suggested negatives (do not repeat): {$alreadyList}
{$ideasBlock}

{$task}

Rules:
- Prefer phrase-level ideas a media buyer would add as broad/phrase negatives (1–4 words each).
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
                Log::warning('AI negatives Claude failed', [
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
            Log::error('AI negatives generation error', ['error' => $e->getMessage()]);

            return response()->json([
                'ok' => false,
                'message' => 'AI generation failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Push negative keywords to the parent's linked/created Google Shopping campaign.
     * Keywords typically come from AI suggestions and/or Amazon KW(-) for the parent.
     */
    public function pushNegativeKeywords(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent' => ['required', 'string', 'max:255'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'campaign_id' => ['nullable', 'string', 'max:64'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:255'],
            'include_amazon' => ['nullable', 'boolean'],
            'match_type' => ['nullable', 'string', 'in:PHRASE,BROAD,EXACT'],
        ]);

        $parent = preg_replace('/\s+/', ' ', trim($validated['parent']));
        $sku = 'PARENT '.$parent;
        $campaignName = trim((string) ($validated['campaign_name'] ?? ''));
        if ($campaignName === '') {
            $campaignName = $sku;
        }
        $matchType = strtoupper(trim((string) ($validated['match_type'] ?? 'PHRASE')));

        $keywords = collect($validated['keywords'] ?? [])
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->values()
            ->all();

        if (! empty($validated['include_amazon'])) {
            $keywords = array_values(array_unique(array_merge(
                $keywords,
                $this->existingAmazonNegativesForParent($parent)
            )));
        }

        if ($keywords === []) {
            return response()->json([
                'ok' => false,
                'message' => 'No negative keywords to push. Generate AI suggestions first (or include Amazon KW negatives).',
            ], 422);
        }

        $campaignId = preg_replace('/\D+/', '', trim((string) ($validated['campaign_id'] ?? ''))) ?: '';

        if ($campaignId === '') {
            $resolved = $this->resolveShoppingCampaignIdForParent($sku, $campaignName);
            $campaignId = $resolved['campaign_id'] ?? '';
            if (($resolved['campaign_name'] ?? '') !== '') {
                $campaignName = $resolved['campaign_name'];
            }
        }

        if ($campaignId === '') {
            return response()->json([
                'ok' => false,
                'message' => 'No Google Shopping campaign found for this parent. Create the campaign first, then push negatives.',
            ], 422);
        }

        $customerId = str_replace('-', '', (string) config('services.google_ads.login_customer_id'));
        if ($customerId === '') {
            return response()->json(['ok' => false, 'message' => 'Google Ads customer ID is not configured.'], 500);
        }

        try {
            $result = app(GoogleAdsSbidService::class)->pushCampaignNegativeKeywords(
                $customerId,
                $campaignId,
                $keywords,
                $matchType
            );
        } catch (\Throwable $e) {
            Log::error('Google Shopping push negatives failed', [
                'parent' => $parent,
                'campaign_id' => $campaignId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Push failed: '.$this->formatGoogleAdsCreateError($e),
            ], 500);
        }

        $added = (int) ($result['added'] ?? 0);
        $failed = (int) ($result['failed'] ?? 0);
        $message = "Pushed {$added} negative keyword(s) to {$campaignName} ({$matchType}).";
        if ($failed > 0) {
            $message .= " {$failed} failed (may already exist).";
        }

        return response()->json([
            'ok' => $added > 0 || $failed === 0,
            'message' => $message,
            'parent' => $parent,
            'campaign_name' => $campaignName,
            'campaign_id' => $campaignId,
            'result' => $result,
        ], $added > 0 || $failed === 0 ? 200 : 500);
    }

    /**
     * Resolve a Shopping campaign_id for PARENT {parent} from links / local campaign index.
     *
     * @return array{campaign_id: string, campaign_name: string}
     */
    private function resolveShoppingCampaignIdForParent(string $parentSku, string $preferredName = ''): array
    {
        $preferredName = trim($preferredName);

        if ($preferredName !== '' && Schema::hasTable(self::CAMPAIGNS_TABLE)) {
            $id = DB::table(self::CAMPAIGNS_TABLE)
                ->where('advertising_channel_type', 'SHOPPING')
                ->where('campaign_name', $preferredName)
                ->orderByDesc('id')
                ->value('campaign_id');
            if ($id) {
                return ['campaign_id' => (string) $id, 'campaign_name' => $preferredName];
            }
        }

        $auto = $this->autoMatchedShoppingCampaign($parentSku);
        if ($auto !== null && ! empty($auto['campaign_id'])) {
            return [
                'campaign_id' => (string) $auto['campaign_id'],
                'campaign_name' => (string) $auto['campaign_name'],
            ];
        }

        $links = $this->manualLinksBySku()->get($parentSku) ?? collect();
        foreach ($links as $link) {
            $cid = trim((string) ($link->campaign_id ?? ''));
            $name = trim((string) ($link->campaign_name ?? ''));
            if ($cid !== '') {
                return ['campaign_id' => $cid, 'campaign_name' => $name !== '' ? $name : $preferredName];
            }
            if ($name !== '' && Schema::hasTable(self::CAMPAIGNS_TABLE)) {
                $id = DB::table(self::CAMPAIGNS_TABLE)
                    ->where('advertising_channel_type', 'SHOPPING')
                    ->where('campaign_name', $name)
                    ->orderByDesc('id')
                    ->value('campaign_id');
                if ($id) {
                    return ['campaign_id' => (string) $id, 'campaign_name' => $name];
                }
            }
        }

        // Freshly created campaigns may only exist in Google Ads until sync —
        // try GAQL by campaign name when we have a preferred name.
        if ($preferredName !== '') {
            try {
                $customerId = str_replace('-', '', (string) config('services.google_ads.login_customer_id'));
                if ($customerId !== '') {
                    $escaped = str_replace("'", "\\'", $preferredName);
                    $rows = app(GoogleAdsSbidService::class)->runQuery(
                        $customerId,
                        "SELECT campaign.id, campaign.name FROM campaign "
                        ."WHERE campaign.name = '{$escaped}' AND campaign.advertising_channel_type = 'SHOPPING' "
                        .'LIMIT 1'
                    );
                    $id = (string) (data_get($rows, '0.campaign.id') ?: data_get($rows, '0.campaignId') ?: '');
                    if ($id !== '') {
                        return ['campaign_id' => $id, 'campaign_name' => $preferredName];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('resolveShoppingCampaignIdForParent GAQL failed', [
                    'campaign_name' => $preferredName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['campaign_id' => '', 'campaign_name' => $preferredName];
    }

    /**
     * Distinct Google Shopping campaigns for the manual link picker.
     */
    public function campaigns(): JsonResponse
    {
        if (! Schema::hasTable(self::CAMPAIGNS_TABLE)) {
            return response()->json(['data' => []]);
        }

        $rows = DB::table(self::CAMPAIGNS_TABLE)
            ->selectRaw('campaign_name, MAX(campaign_id) AS campaign_id')
            ->where('advertising_channel_type', 'SHOPPING')
            ->whereNotNull('campaign_name')
            ->where('campaign_name', '!=', '')
            ->whereRaw('UPPER(campaign_name) NOT LIKE ?', ['% SEARCH%'])
            ->whereRaw('UPPER(campaign_name) NOT LIKE ?', ['% YT'])
            ->groupBy('campaign_name')
            ->orderBy('campaign_name')
            ->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * Manually link a Google Shopping campaign to a PARENT sku.
     */
    public function link(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:255'],
            'campaign_name' => ['required', 'string', 'max:255'],
        ]);

        $campaignId = null;
        if (Schema::hasTable(self::CAMPAIGNS_TABLE)) {
            $campaignId = DB::table(self::CAMPAIGNS_TABLE)
                ->where('campaign_name', $validated['campaign_name'])
                ->max('campaign_id');
        }

        GoogleAdsMissingLink::firstOrCreate(
            [
                'channel' => self::CHANNEL,
                'sku' => $validated['sku'],
                'campaign_name' => $validated['campaign_name'],
            ],
            [
                'campaign_id' => $campaignId,
                'user_id' => Auth::id(),
                'created_at' => Carbon::now(),
            ]
        );

        $this->manualLinksCache = null;
        $this->campaignStatusMapCache = null;
        self::forgetMissingTotalCache();

        return response()->json($this->campaignsResponseForSku($validated['sku']));
    }

    /**
     * Remove a manually linked campaign by its id.
     */
    public function unlink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $link = GoogleAdsMissingLink::find($validated['id']);
        $sku = (string) ($link?->sku ?? '');
        if ($link && (string) $link->channel === self::CHANNEL) {
            $link->delete();
            $this->manualLinksCache = null;
            $this->campaignStatusMapCache = null;
            self::forgetMissingTotalCache();
        }

        return response()->json($this->campaignsResponseForSku($sku));
    }

    /**
     * Delete (remove) a Google Shopping campaign in Google Ads and unlink it locally.
     */
    public function delete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:255'],
            'campaign_id' => ['nullable', 'string', 'max:64'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'link_id' => ['nullable', 'integer'],
        ]);

        $sku = trim($validated['sku']);
        $campaignName = trim((string) ($validated['campaign_name'] ?? ''));
        $campaignId = preg_replace('/\D+/', '', trim((string) ($validated['campaign_id'] ?? ''))) ?: '';

        if ($campaignId === '' && $campaignName !== '') {
            $resolved = $this->resolveShoppingCampaignIdForParent($sku, $campaignName);
            $campaignId = $resolved['campaign_id'] ?? '';
            if (($resolved['campaign_name'] ?? '') !== '') {
                $campaignName = $resolved['campaign_name'];
            }
        }

        if ($campaignId === '' && ! empty($validated['link_id'])) {
            $link = GoogleAdsMissingLink::query()
                ->where('id', (int) $validated['link_id'])
                ->where('channel', self::CHANNEL)
                ->first();
            if ($link) {
                $campaignId = preg_replace('/\D+/', '', trim((string) ($link->campaign_id ?? ''))) ?: '';
                if ($campaignName === '') {
                    $campaignName = trim((string) ($link->campaign_name ?? ''));
                }
                if ($campaignId === '' && $campaignName !== '') {
                    $resolved = $this->resolveShoppingCampaignIdForParent($sku, $campaignName);
                    $campaignId = $resolved['campaign_id'] ?? '';
                }
            }
        }

        if ($campaignId === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Could not resolve campaign ID to delete. Provide campaign_id or a known campaign_name.',
            ], 422);
        }

        $customerId = str_replace('-', '', (string) config('services.google_ads.login_customer_id'));
        if ($customerId === '') {
            return response()->json(['ok' => false, 'message' => 'Google Ads customer ID is not configured.'], 500);
        }

        try {
            $removed = app(GoogleAdsSbidService::class)->removeCampaign($customerId, $campaignId);
        } catch (\Throwable $e) {
            Log::error('Google Shopping missing delete failed', [
                'sku' => $sku,
                'campaign_id' => $campaignId,
                'campaign_name' => $campaignName,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Google Ads delete failed: '.$this->formatGoogleAdsCreateError($e),
            ], 500);
        }

        // Drop local link(s) for this parent + campaign.
        $linkQuery = GoogleAdsMissingLink::query()
            ->where('channel', self::CHANNEL)
            ->where('sku', $sku);
        $linkQuery->where(function ($q) use ($campaignId, $campaignName, $validated) {
            $q->where('campaign_id', $campaignId);
            if ($campaignName !== '') {
                $q->orWhere('campaign_name', $campaignName);
            }
            if (! empty($validated['link_id'])) {
                $q->orWhere('id', (int) $validated['link_id']);
            }
        })->delete();

        if (Schema::hasTable(self::CAMPAIGNS_TABLE)) {
            DB::table(self::CAMPAIGNS_TABLE)
                ->where('campaign_id', $campaignId)
                ->update(['campaign_status' => 'REMOVED']);
        }

        $this->manualLinksCache = null;
        $this->campaignStatusMapCache = null;
        $this->shoppingCampaignIndexCache = null;
        self::forgetMissingTotalCache();

        return response()->json([
            'ok' => true,
            'message' => 'Campaign removed in Google Ads'
                .($campaignName !== '' ? ': '.$campaignName : ' (ID '.$campaignId.')').'.',
            'sku' => $sku,
            'campaign_id' => $campaignId,
            'campaign_name' => $campaignName,
            'removed' => $removed,
            'campaigns' => $this->campaignsResponseForSku($sku)['campaigns'],
        ]);
    }

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

        $inventoryByParent = $this->buildShopifyMetricsByParent($rows)['inventory'];

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

        $manualBySku = $this->manualLinksBySku();
        $statusMap = $this->campaignStatusMapForLinks($manualBySku);

        $total = 0;
        foreach ($parents as $parentKey => $parent) {
            if ((int) round($inventoryByParent[$parentKey] ?? 0) <= 0) {
                continue;
            }
            $sku = 'PARENT '.$parent;
            $campaigns = $this->campaignsForParentSku(
                $sku,
                $manualBySku->get($sku) ?? collect(),
                $statusMap
            );
            if ($campaigns === []) {
                $total++;
            }
        }

        return $total;
    }

    /**
     * @return Collection<string, Collection<int, GoogleAdsMissingLink>>
     */
    private function manualLinksBySku(): Collection
    {
        if ($this->manualLinksCache === null) {
            $this->manualLinksCache = GoogleAdsMissingLink::query()
                ->where('channel', self::CHANNEL)
                ->orderBy('id')
                ->get()
                ->groupBy(fn ($l) => (string) $l->sku);
        }

        return $this->manualLinksCache;
    }

    /**
     * Auto-match from google shopping grid + manual links for one PARENT sku.
     *
     * @param  Collection<int, GoogleAdsMissingLink>  $manualLinks
     * @param  array<string, string>  $statusMap
     * @return list<array{id: int, campaign_id: ?string, campaign_name: string, status: string, dot: string, source: string}>
     */
    private function campaignsForParentSku(string $sku, Collection $manualLinks, array $statusMap): array
    {
        $out = [];
        $seen = [];

        $auto = $this->autoMatchedShoppingCampaign($sku);
        if ($auto !== null) {
            $key = $this->normalizeCampaignName($auto['campaign_name']);
            $status = $auto['status'] !== ''
                ? $auto['status']
                : ($statusMap[$key] ?? '');
            if ($status !== 'REMOVED') {
                $out[] = [
                    'id' => 0,
                    'campaign_id' => $auto['campaign_id'],
                    'campaign_name' => $auto['campaign_name'],
                    'status' => $status,
                    'dot' => $status === 'ENABLED' ? 'green' : ($status !== '' ? 'red' : ''),
                    'source' => 'auto',
                ];
                if ($key !== '') {
                    $seen[$key] = true;
                }
            }
        }

        foreach ($manualLinks as $l) {
            $name = (string) $l->campaign_name;
            $key = $this->normalizeCampaignName($name);
            if ($key !== '' && isset($seen[$key])) {
                continue;
            }
            $status = $statusMap[$key] ?? '';
            if ($status === 'REMOVED') {
                continue;
            }
            $out[] = [
                'id' => (int) $l->id,
                'campaign_id' => $l->campaign_id,
                'campaign_name' => $name,
                'status' => $status,
                'dot' => $status === 'ENABLED' ? 'green' : ($status !== '' ? 'red' : ''),
                'source' => 'manual',
            ];
            if ($key !== '') {
                $seen[$key] = true;
            }
        }

        return $out;
    }

    /**
     * Match PARENT {parent} to the same campaign_name on /google/shopping/google-shopping.
     *
     * @return array{campaign_name: string, campaign_id: ?string, status: string}|null
     */
    private function autoMatchedShoppingCampaign(string $sku): ?array
    {
        $key = $this->normalizeCampaignName($sku);
        if ($key === '') {
            return null;
        }

        return $this->shoppingCampaignIndex()[$key] ?? null;
    }

    /**
     * Distinct Shopping campaigns (same scope as google-shopping grid), keyed by normalized name.
     *
     * @return array<string, array{campaign_name: string, campaign_id: ?string, status: string}>
     */
    private function shoppingCampaignIndex(): array
    {
        if ($this->shoppingCampaignIndexCache !== null) {
            return $this->shoppingCampaignIndexCache;
        }

        if (! Schema::hasTable(self::CAMPAIGNS_TABLE)) {
            return $this->shoppingCampaignIndexCache = [];
        }

        $latestIds = DB::table(self::CAMPAIGNS_TABLE)
            ->selectRaw('campaign_name, MAX(id) AS max_id')
            ->where('advertising_channel_type', 'SHOPPING')
            ->whereNotNull('campaign_name')
            ->where('campaign_name', '!=', '')
            ->whereRaw('UPPER(campaign_name) NOT LIKE ?', ['% SEARCH%'])
            ->whereRaw('UPPER(campaign_name) NOT LIKE ?', ['% YT'])
            ->groupBy('campaign_name')
            ->pluck('max_id', 'campaign_name');

        $index = [];
        if ($latestIds->isNotEmpty()) {
            $byId = DB::table(self::CAMPAIGNS_TABLE)
                ->whereIn('id', $latestIds->values()->all())
                ->get(['id', 'campaign_name', 'campaign_id', 'campaign_status'])
                ->keyBy('id');

            foreach ($latestIds as $name => $id) {
                $row = $byId->get((int) $id);
                $key = $this->normalizeCampaignName((string) $name);
                if ($key === '') {
                    continue;
                }
                $status = strtoupper(trim((string) ($row->campaign_status ?? '')));
                if ($status === 'REMOVED') {
                    continue;
                }
                $index[$key] = [
                    'campaign_name' => (string) ($row->campaign_name ?? $name),
                    'campaign_id' => $row->campaign_id !== null ? (string) $row->campaign_id : null,
                    'status' => $status,
                ];
            }
        }

        return $this->shoppingCampaignIndexCache = $index;
    }

    /**
     * @return array{ok: bool, sku: string, campaigns: list<array{id: int, campaign_id: ?string, campaign_name: string, status: string, dot: string, source: string}>}
     */
    private function campaignsResponseForSku(string $sku): array
    {
        if ($sku === '') {
            return ['ok' => true, 'sku' => '', 'campaigns' => []];
        }

        $links = $this->manualLinksBySku()->get($sku) ?? collect();
        $statusMap = $this->campaignStatusMapForLinks(collect([$sku => $links]));

        return [
            'ok' => true,
            'sku' => $sku,
            'campaigns' => $this->campaignsForParentSku($sku, $links, $statusMap),
        ];
    }

    /**
     * Status lookup only for campaign names that are actually linked.
     *
     * @param  Collection<string, Collection<int, GoogleAdsMissingLink>>  $manualBySku
     * @return array<string, string>
     */
    private function campaignStatusMapForLinks(Collection $manualBySku): array
    {
        if ($this->campaignStatusMapCache !== null) {
            return $this->campaignStatusMapCache;
        }

        // Prefer statuses from the shopping grid index (covers auto + manual names).
        $map = [];
        foreach ($this->shoppingCampaignIndex() as $key => $info) {
            $map[$key] = $info['status'];
        }

        $names = $manualBySku
            ->flatten(1)
            ->pluck('campaign_name')
            ->filter(fn ($n) => is_string($n) && trim($n) !== '')
            ->unique()
            ->values()
            ->all();

        if ($names !== [] && Schema::hasTable(self::CAMPAIGNS_TABLE)) {
            $missingNames = array_values(array_filter(
                $names,
                fn ($n) => ! isset($map[$this->normalizeCampaignName((string) $n)])
            ));

            if ($missingNames !== []) {
                $latestIds = DB::table(self::CAMPAIGNS_TABLE)
                    ->selectRaw('campaign_name, MAX(id) AS max_id')
                    ->whereIn('campaign_name', $missingNames)
                    ->groupBy('campaign_name')
                    ->pluck('max_id', 'campaign_name');

                if ($latestIds->isNotEmpty()) {
                    $byId = DB::table(self::CAMPAIGNS_TABLE)
                        ->whereIn('id', $latestIds->values()->all())
                        ->get(['id', 'campaign_name', 'campaign_status'])
                        ->keyBy('id');

                    foreach ($latestIds as $name => $id) {
                        $row = $byId->get((int) $id);
                        $key = $this->normalizeCampaignName((string) $name);
                        if ($key === '') {
                            continue;
                        }
                        $map[$key] = strtoupper(trim((string) ($row->campaign_status ?? '')));
                    }
                }
            }
        }

        return $this->campaignStatusMapCache = $map;
    }

    private function normalizeCampaignName(string $name): string
    {
        return strtoupper(rtrim(preg_replace('/\s+/', ' ', trim($name)), '.'));
    }

    /**
     * Ensure campaign name is unique against locally synced Shopping campaigns.
     */
    private function ensureUniqueCampaignName(string $desired): string
    {
        $base = trim($desired);
        if ($base === '') {
            return $base;
        }

        $index = $this->shoppingCampaignIndex();
        $candidate = $base;
        $n = 2;
        while (isset($index[$this->normalizeCampaignName($candidate)])) {
            $candidate = $base.' #'.$n;
            $n++;
            if ($n > 50) {
                $candidate = $base.' '.date('YmdHis');
                break;
            }
        }

        return $candidate;
    }

    /**
     * Create Shopping campaign; on Google DUPLICATE_CAMPAIGN_NAME, retry once with a unique suffix.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function createShoppingCampaignWithUniqueNameRetry(string $customerId, array $params): array
    {
        $service = app(GoogleAdsSbidService::class);
        try {
            return $service->createShoppingProductCampaign($customerId, $params);
        } catch (\Throwable $e) {
            if (! $this->isDuplicateCampaignNameError($e)) {
                throw $e;
            }

            $original = trim((string) ($params['campaign_name'] ?? ''));
            $retryName = $this->ensureUniqueCampaignName($original.' '.date('YmdHis'));
            if ($this->normalizeCampaignName($retryName) === $this->normalizeCampaignName($original)) {
                $retryName = $original.' '.substr(uniqid('', true), -6);
            }

            Log::warning('Google Shopping create retrying with unique campaign name', [
                'original' => $original,
                'retry' => $retryName,
            ]);

            $params['campaign_name'] = $retryName;

            return $service->createShoppingProductCampaign($customerId, $params);
        }
    }

    private function isDuplicateCampaignNameError(\Throwable $e): bool
    {
        $msg = $e->getMessage();

        return stripos($msg, 'DUPLICATE_CAMPAIGN_NAME') !== false
            || stripos($msg, 'already assigned to another active or paused campaign') !== false;
    }

    /**
     * Prefer a short Google Ads error over the raw JSON blob.
     */
    private function formatGoogleAdsCreateError(\Throwable $e): string
    {
        $raw = $e->getMessage();

        if ($this->isDuplicateCampaignNameError($e)) {
            return 'Campaign name already exists in Google Ads. Change the Campaign name (include the Item ID) and try again.';
        }

        // Prefer the most specific GoogleAdsFailure error message (skip generic wrapper text).
        if (preg_match_all('/"message"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/', $raw, $matches)) {
            $generic = [
                'request contains an invalid argument.',
                'request contains an invalid argument',
            ];
            foreach (array_reverse($matches[1]) as $encoded) {
                $msg = stripcslashes($encoded);
                if ($msg !== '' && ! in_array(strtolower($msg), $generic, true)) {
                    return $msg;
                }
            }
        }

        return $raw;
    }

    /**
     * Best-effort product title for AI context (Shopify title / product_master Values).
     */
    private function resolveProductTitleForAi(string $parent, string $targetSku): string
    {
        $sku = $targetSku !== '' ? $targetSku : '';
        if ($sku === '' && Schema::hasTable('product_master')) {
            $sku = (string) (DB::table('product_master')
                ->whereNull('deleted_at')
                ->where('parent', $parent)
                ->where('sku', 'not like', 'PARENT%')
                ->orderBy('sku')
                ->value('sku') ?? '');
        }

        if ($sku !== '') {
            $shopify = ShopifySku::firstForProductSku($sku);
            $title = trim((string) ($shopify->product_title ?? ''));
            if ($title !== '') {
                return $title;
            }

            if (Schema::hasTable('product_master')) {
                $values = DB::table('product_master')->where('sku', $sku)->value('Values');
                if (is_string($values)) {
                    $values = json_decode($values, true);
                }
                if (is_array($values)) {
                    foreach (['title', 'Title', 'title150', 'product_title', 'name'] as $key) {
                        $t = trim((string) ($values[$key] ?? ''));
                        if ($t !== '') {
                            return $t;
                        }
                    }
                }
            }
        }

        return $parent;
    }

    /**
     * Existing Amazon KW(-) negative keyword texts for PARENT {parent}.
     *
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
     * Merchant Center Item ID used in Google Shopping product groups:
     * shopify_us_{shopifyProductId}_{shopifyVariantId}
     */
    private function resolveMerchantCenterItemId(string $sku): ?string
    {
        $map = $this->resolveMerchantCenterItemIds([$sku]);

        return $map[$sku] ?? null;
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

    /**
     * Buyer / seller links from /shopify-b2c-pricing (shopify_b2c_listing_statuses)
     * plus all child SKUs (with Merchant Center Item IDs) for the selected parent.
     *
     * @param  \Illuminate\Support\Collection  $productRows
     * @param  array<string, string>  $parents  upper(parent) => display parent
     * @return array<string, array{
     *   buyer_link: string,
     *   seller_link: string,
     *   target_sku: string,
     *   merchant_item_id: string,
     *   children: list<array{target_sku: string, merchant_item_id: string, inv: float}>
     * }>
     */
    private function shopifyB2cMetaByParent($productRows, array $parents): array
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

        $listingBySku = [];
        if ($allChildSkus !== [] && Schema::hasTable('shopify_b2c_listing_statuses')) {
            ShopifyB2CListingStatus::query()
                ->whereIn('sku', $allChildSkus)
                ->orderByDesc('updated_at')
                ->get()
                ->each(function ($row) use (&$listingBySku) {
                    $sku = (string) $row->sku;
                    if (isset($listingBySku[$sku])) {
                        return;
                    }
                    $val = is_array($row->value) ? $row->value : [];
                    $listingBySku[$sku] = $val;
                });
        }

        $itemIds = $this->resolveMerchantCenterItemIds($allChildSkus);

        $out = [];
        foreach ($parents as $pKey => $parent) {
            $children = array_values(array_unique($childrenByParent[$pKey] ?? []));
            sort($children, SORT_NATURAL | SORT_FLAG_CASE);

            $buyer = '';
            $seller = '';
            $targetSku = '';
            $fallbackSku = '';
            $childRows = [];

            foreach ($children as $childSku) {
                if ($fallbackSku === '') {
                    $fallbackSku = $childSku;
                }
                $inv = (float) ($shopifyBySku->get($childSku)?->inv ?? 0);
                if ($targetSku === '' && $inv > 0) {
                    $targetSku = $childSku;
                }
                $links = $listingBySku[$childSku] ?? null;
                if (is_array($links)) {
                    if ($buyer === '' && ! empty($links['buyer_link'])) {
                        $buyer = (string) $links['buyer_link'];
                        if ($targetSku === '') {
                            $targetSku = $childSku;
                        }
                    }
                    if ($seller === '' && ! empty($links['seller_link'])) {
                        $seller = (string) $links['seller_link'];
                    }
                }

                $merchantItemId = $itemIds[$childSku] ?? '';
                $childRows[] = [
                    'target_sku' => $childSku,
                    'merchant_item_id' => $merchantItemId,
                    'inv' => $inv,
                ];
            }

            if ($targetSku === '') {
                $targetSku = $fallbackSku;
            }

            $out[$pKey] = [
                'buyer_link' => $buyer,
                'seller_link' => $seller,
                'target_sku' => $targetSku,
                'merchant_item_id' => $targetSku !== '' ? ($itemIds[$targetSku] ?? '') : '',
                'children' => $childRows,
            ];
        }

        return $out;
    }

    /**
     * KW(-) counts — identical method/tables as /purchase-master/ads-link:
     * linked KW campaigns from amazon_ads_missing_links + DISTINCT keyword_id
     * counts from amazon_sp_negative_keywords.
     *
     * @param  list<string>  $parentSkus  e.g. ["PARENT 04 CS", ...]
     * @return array<string, array{negative_count: int, kw_campaigns: list<string>}>
     */
    private function adsLinkNegativeCountsByParentSku(array $parentSkus): array
    {
        $skus = collect($parentSkus)
            ->map(fn ($s) => trim((string) $s))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($skus === [] || ! Schema::hasTable('amazon_ads_missing_links')) {
            return [];
        }

        $linksBySku = AmazonAdsMissingLink::query()
            ->whereIn('sku', $skus)
            ->where('type', 'KW')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($l) => (string) $l->sku);

        $campaignNames = $linksBySku
            ->flatten(1)
            ->pluck('campaign_name')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $countsByCampaign = $this->adsLinkNegativeCountsByCampaign($campaignNames);

        $out = [];
        foreach ($skus as $sku) {
            $names = ($linksBySku->get($sku) ?? collect())
                ->pluck('campaign_name')
                ->map(fn ($n) => trim((string) $n))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $total = 0;
            foreach ($names as $name) {
                $total += (int) ($countsByCampaign[$name] ?? 0);
            }

            $out[$sku] = [
                'negative_count' => $total,
                'kw_campaigns' => $names,
            ];
        }

        return $out;
    }

    /**
     * Same source/count method as AdsLinkController::negativeCountsByCampaign().
     *
     * @param  list<string>  $campaignNames
     * @return array<string, int>
     */
    private function adsLinkNegativeCountsByCampaign(array $campaignNames): array
    {
        $names = collect($campaignNames)
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($names === [] || ! Schema::hasTable('amazon_sp_negative_keywords')) {
            return [];
        }

        return DB::table('amazon_sp_negative_keywords')
            ->whereIn('campaignName', $names)
            ->whereNotNull('campaignName')
            ->where('campaignName', '!=', '')
            ->select('campaignName', DB::raw('COUNT(DISTINCT keyword_id) AS keyword_count'))
            ->groupBy('campaignName')
            ->pluck('keyword_count', 'campaignName')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Shopify INV sum + Price avg per parent — same shopify_skus source as /shopify-b2c-pricing.
     *
     * @param  \Illuminate\Support\Collection  $rows
     * @return array{inventory: array<string, float>, price: array<string, float>}
     */
    private function buildShopifyMetricsByParent($rows): array
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

        $invTotals = [];
        $priceSums = [];
        $priceCounts = [];

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
            $invTotals[$pKey] = ($invTotals[$pKey] ?? 0) + (float) ($rec?->inv ?? 0);

            $price = (float) ($rec?->price ?? 0);
            if ($price > 0) {
                $priceSums[$pKey] = ($priceSums[$pKey] ?? 0) + $price;
                $priceCounts[$pKey] = ($priceCounts[$pKey] ?? 0) + 1;
            }
        }

        $priceAvgs = [];
        foreach ($priceSums as $pKey => $sum) {
            $count = (int) ($priceCounts[$pKey] ?? 0);
            $priceAvgs[$pKey] = $count > 0 ? round($sum / $count, 2) : 0.0;
        }

        return [
            'inventory' => $invTotals,
            'price' => $priceAvgs,
        ];
    }

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
}
