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
            return (int) Cache::remember(self::SIDEBAR_COUNT_CACHE_KEY, 300, function () {
                return (new self)->computeMissingTotal();
            });
        } catch (\Throwable $e) {
            try {
                return (new self)->computeMissingTotal();
            } catch (\Throwable $e2) {
                return 0;
            }
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
            ];

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
                'suggested_campaign_name' => $sku,
                'default_merchant_id' => $defaultMerchantId,
                'campaigns' => $this->campaignsForParentSku($sku, $manualBySku->get($sku) ?? collect(), $statusMap),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    /**
     * Create a new Google Shopping product campaign for a parent (PAUSED), then
     * auto-link it on this missing page. Form fields are prefilled from parent /
     * Shopify B2C buyer link (/shopify-b2c-pricing listing status).
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent' => ['required', 'string', 'max:255'],
            'campaign_name' => ['required', 'string', 'max:255'],
            'budget_amount' => ['nullable', 'numeric', 'min:1'],
            'cpc_bid' => ['nullable', 'numeric', 'min:0.01'],
            'merchant_id' => ['nullable', 'integer', 'min:1'],
            'campaign_priority' => ['nullable', 'integer', 'min:0', 'max:2'],
            'feed_label' => ['nullable', 'string', 'max:32'],
            'target_sku' => ['required', 'string', 'max:255'],
            'buyer_link' => ['nullable', 'string', 'max:2000'],
        ]);

        $parent = preg_replace('/\s+/', ' ', trim($validated['parent']));
        $campaignName = trim($validated['campaign_name']);
        $itemId = trim((string) $validated['target_sku']);
        if ($itemId === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Item ID (Merchant Center) is required.',
            ], 422);
        }
        $sku = 'PARENT '.$parent;

        $existing = $this->autoMatchedShoppingCampaign($sku);
        if ($existing !== null) {
            return response()->json([
                'ok' => false,
                'message' => 'A Shopping campaign already exists for this parent: '.$existing['campaign_name'],
            ], 422);
        }

        $normName = $this->normalizeCampaignName($campaignName);
        foreach ($this->shoppingCampaignIndex() as $key => $info) {
            if ($key === $normName) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Campaign name already exists in Google Shopping: '.$info['campaign_name'],
                ], 422);
            }
        }

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
            $created = app(GoogleAdsSbidService::class)->createShoppingProductCampaign($customerId, [
                'campaign_name' => $campaignName,
                'budget_amount' => (float) ($validated['budget_amount'] ?? 1),
                'cpc_bid' => (float) ($validated['cpc_bid'] ?? 0.5),
                'merchant_id' => $merchantId,
                'campaign_priority' => (int) ($validated['campaign_priority'] ?? 0),
                'feed_label' => $validated['feed_label'] ?? 'US',
                'enable_local' => true,
                'item_id' => $itemId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Google Shopping missing create failed', [
                'parent' => $parent,
                'campaign_name' => $campaignName,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Google Ads create failed: '.$e->getMessage(),
            ], 500);
        }

        // Persist manual link so the Campaign column updates immediately (before next sync).
        GoogleAdsMissingLink::firstOrCreate(
            [
                'channel' => self::CHANNEL,
                'sku' => $sku,
                'campaign_name' => $created['campaign_name'],
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
            'message' => 'Shopping campaign created (PAUSED).',
            'parent' => $parent,
            'sku' => $sku,
            'target_sku' => $itemId,
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

        foreach ($manualLinks as $l) {
            $name = (string) $l->campaign_name;
            $key = $this->normalizeCampaignName($name);
            if ($key !== '' && isset($seen[$key])) {
                continue;
            }
            $status = $statusMap[$key] ?? '';
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
                $index[$key] = [
                    'campaign_name' => (string) ($row->campaign_name ?? $name),
                    'campaign_id' => $row->campaign_id !== null ? (string) $row->campaign_id : null,
                    'status' => strtoupper(trim((string) ($row->campaign_status ?? ''))),
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
     * Buyer / seller links from /shopify-b2c-pricing (shopify_b2c_listing_statuses)
     * plus a target child SKU for the selected parent.
     *
     * @param  \Illuminate\Support\Collection  $productRows
     * @param  array<string, string>  $parents  upper(parent) => display parent
     * @return array<string, array{buyer_link: string, seller_link: string, target_sku: string}>
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

        $out = [];
        foreach ($parents as $pKey => $_parent) {
            $children = $childrenByParent[$pKey] ?? [];
            $buyer = '';
            $seller = '';
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
                $links = $listingBySku[$childSku] ?? null;
                if (! is_array($links)) {
                    continue;
                }
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

            if ($targetSku === '') {
                $targetSku = $fallbackSku;
            }

            $out[$pKey] = [
                'buyer_link' => $buyer,
                'seller_link' => $seller,
                'target_sku' => $targetSku,
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
