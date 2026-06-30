<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\Concerns\GuardsMarketplaceApiConfiguration;
use App\Http\Controllers\ProductMaster\Concerns\RetriesMarketplacePush;
use App\Models\ProductMaster;
use App\Services\AmazonSpApiService;
use App\Services\BestBuyApiService;
use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use App\Services\MacysApiService;
use App\Services\ReverbApiService;
use App\Services\ShopifyApiService;
use App\Services\ShopifyPLSApiService;
use App\Services\Support\DescriptionWithImagesFormatter;
use App\Services\Support\ProductMasterMarketplaceMaps;
use App\Services\TemuApiService;
use App\Services\WayfairApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DescriptionMasterController extends Controller
{
    use GuardsMarketplaceApiConfiguration;
    use RetriesMarketplacePush;

    public function index(Request $request)
    {
        return view('product-description', [
            'mode' => $request->query('mode', ''),
            'demo' => $request->query('demo', ''),
        ]);
    }

    /**
     * GET /product-description-data — paginated Product Master rows + per-marketplace description_master for page SKUs only.
     */
    public function getDescriptionMasterData(Request $request)
    {
        try {
            @set_time_limit(180);
            @ini_set('memory_limit', '512M');

            // Load ALL non-parent rows (no pagination) — the page filters/searches client-side, like Bullet Points.
            $products = ProductMaster::query()
                ->orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->whereRaw("UPPER(COALESCE(sku, '')) NOT LIKE '%PARENT%'")
                ->select([
                    'id', 'parent', 'sku', 'title150',
                    'product_description', 'description_1500', 'description_1000', 'description_800', 'description_600',
                ])
                ->get();

            $rawSkus = [];
            foreach ($products as $product) {
                if ($product->sku) {
                    $rawSkus[] = $product->sku;
                }
            }

            $marketTables = $this->marketplaceTableMap();
            $descriptionsByMp = [];
            foreach ($marketTables as $marketplace => $table) {
                $descriptionsByMp[$marketplace] = $this->loadDescriptionMasterForSkuList($table, $rawSkus);
            }
            $pushStatusesBySku = $this->loadDescriptionPushStatusesBySku();

            $result = [];
            foreach ($products as $product) {
                $sku = $this->normalizeSku($product->sku);
                $desc = [];
                foreach (array_keys($marketTables) as $mp) {
                    $desc[$mp] = $descriptionsByMp[$mp][$sku] ?? '';
                }
                $result[] = [
                    'id' => $product->id,
                    'Parent' => $product->parent,
                    'SKU' => $product->sku,
                    'title150' => $product->title150,
                    'product_description' => $product->product_description,
                    'description_1500' => $product->description_1500,
                    'description_1000' => $product->description_1000,
                    'description_800' => $product->description_800,
                    'description_600' => $product->description_600,
                    'descriptions' => $desc,
                    'description_push_statuses' => $pushStatusesBySku[$sku] ?? [],
                ];
            }

            return response()->json([
                'message' => 'Description Master data loaded',
                'data' => $result,
                'status' => 200,
                'meta' => ['total' => count($result)],
            ]);
        } catch (\Throwable $e) {
            Log::error('DescriptionMaster: getDescriptionMasterData failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to load description master data.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * POST /product-description/update — save description_master rows and push to APIs.
     */
    public function pushDescriptionToMarketplaces(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'updates' => 'required|array|min:1',
                'updates.*.marketplace' => 'required|string',
                'updates.*.description' => 'nullable|string',
                'updates.*.description_html' => 'nullable|string',
                'updates.*.image_urls' => 'nullable|array',
                'updates.*.image_urls.*' => 'nullable|string',
            ]);

            $sku = $this->normalizeSku($validated['sku']);
            $results = [];
            $allowedMarketplaces = array_keys($this->marketplaceTableMap());

            foreach ($validated['updates'] as $u) {
                $marketplace = strtolower(trim($u['marketplace']));
                $requestPlain = trim((string) ($u['description'] ?? ''));
                $requestHtml = trim((string) ($u['description_html'] ?? ''));
                $masterText = $this->loadMasterDescriptionTextForSku($sku, $marketplace);
                $rawForSave = $this->resolveDescriptionForMetricsSave(
                    $sku,
                    $marketplace,
                    $requestPlain,
                    $requestHtml,
                    $masterText
                );
                $text = $this->prepareDescriptionForPush($rawForSave, $marketplace);
                $storedText = $this->prepareDescriptionForMetricsSave($rawForSave, $marketplace);
                $imageUrls = array_values(array_filter(array_map('trim', (array) ($u['image_urls'] ?? []))));

                if (! in_array($marketplace, $allowedMarketplaces, true)) {
                    $results[$marketplace] = ['success' => false, 'message' => 'Unknown or unsupported marketplace'];

                    continue;
                }

                if ($blocked = $this->marketplaceApiNotConfiguredResult($marketplace)) {
                    $results[$marketplace] = $blocked;
                    continue;
                }

                if ($text === '') {
                    $results[$marketplace] = ['success' => false, 'message' => 'Description cannot be empty'];
                    $this->saveDescriptionPushStatus($sku, $marketplace, 'failed', 'Description cannot be empty');

                    continue;
                }

                $max = $this->maxCharsForMarketplace($marketplace);
                $plainLen = mb_strlen($this->normalizeDescriptionPlainText($text));
                if ($plainLen > $max) {
                    $msg = "Description exceeds {$max} characters for this marketplace.";
                    $results[$marketplace] = ['success' => false, 'message' => $msg];
                    $this->saveDescriptionPushStatus($sku, $marketplace, 'failed', $msg);

                    continue;
                }

                Log::info('DescriptionMaster marketplace push started', [
                    'sku' => $sku,
                    'marketplace' => $marketplace,
                    'request_plain_chars' => mb_strlen($requestPlain),
                    'request_html_chars' => mb_strlen($requestHtml),
                    'push_plain_chars' => $plainLen,
                    'push_payload_chars' => mb_strlen($text),
                    'push_uses_html' => $this->marketplacesPushHtml($marketplace),
                    'master_chars' => mb_strlen($masterText),
                    'using_master_description' => $requestPlain === '' && $requestHtml === '' && $masterText !== '',
                    'text_preview' => mb_substr($this->normalizeDescriptionPlainText($text), 0, 80),
                ]);

                $serviceResult = $this->invokeMarketplacePushWithRetries(
                    fn () => $this->callMarketplaceDescriptionService($marketplace, $sku, $text, $imageUrls),
                    'DescriptionMaster',
                    $marketplace,
                    $sku
                );

                $success = (bool) ($serviceResult['success'] ?? false);
                $tableSaved = $success
                    ? $this->saveDescriptionToMarketplaceTable($marketplace, $sku, $storedText)
                    : false;
                if ($success) {
                    if ($marketplace === 'amazon') {
                        $this->syncAmazonPlainDescriptionToProductMaster($sku, $text);
                    } elseif (in_array($marketplace, ['shopify_main', 'shopify_pls'], true)) {
                        $this->syncShopifyPlainDescriptionToProductMaster($sku, $storedText);
                    }
                }
                $pushStatus = $success ? 'success' : 'failed';
                $pushMessage = $success
                    ? ($serviceResult['message'] ?? 'Updated')
                    : ($serviceResult['message'] ?? 'Unable to update this marketplace');
                $this->saveDescriptionPushStatus($sku, $marketplace, $pushStatus, $pushMessage);
                Log::info('DescriptionMaster marketplace push finished', [
                    'sku' => $sku,
                    'marketplace' => $marketplace,
                    'success' => $success,
                    'local_saved' => $tableSaved,
                    'push_status' => $pushStatus,
                    'message' => $pushMessage,
                    'attempts' => (int) ($serviceResult['attempts'] ?? 1),
                    'retried' => (bool) ($serviceResult['retried'] ?? false),
                ]);
                $results[$marketplace] = [
                    'success' => $success,
                    'marketplace_success' => $success,
                    'push_status' => $pushStatus,
                    'message' => $pushMessage,
                    'local_saved' => $tableSaved,
                    'attempts' => (int) ($serviceResult['attempts'] ?? 1),
                    'retried' => (bool) ($serviceResult['retried'] ?? false),
                ];
            }

            $totalSuccess = collect($results)->where('success', true)->count();
            $totalFailed = collect($results)->where('success', false)->count();

            if ($totalFailed > 0) {
                Log::warning('DescriptionMaster: push completed with failures', [
                    'sku' => $sku,
                    'total_success' => $totalSuccess,
                    'total_failed' => $totalFailed,
                    'results' => $results,
                ]);
            }

            return response()->json([
                'success' => $totalFailed === 0,
                'results' => $results,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'message' => "Updated {$totalSuccess} marketplace(s).".($totalFailed > 0 ? " {$totalFailed} failed." : ''),
            ]);
        } catch (\Throwable $e) {
            Log::error('DescriptionMaster: pushDescriptionToMarketplaces failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /product-description/reset-marketplace — clear saved description_master for one SKU + marketplace (metrics only).
     */
    public function resetMarketplaceDescription(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'marketplace' => 'required|string',
            ]);

            $sku = $this->normalizeSku($validated['sku']);
            $marketplace = strtolower(trim($validated['marketplace']));
            $allowed = array_keys($this->marketplaceTableMap());

            if (! in_array($marketplace, $allowed, true)) {
                return response()->json(['success' => false, 'message' => 'Unknown or unsupported marketplace'], 422);
            }

            if ($sku === '') {
                return response()->json(['success' => false, 'message' => 'Invalid SKU'], 422);
            }

            $cleared = $this->clearDescriptionInMarketplaceTable($marketplace, $sku);

            return response()->json([
                'success' => $cleared,
                'message' => $cleared
                    ? 'Saved description cleared for this marketplace.'
                    : 'Could not clear description (table missing or error).',
            ]);
        } catch (\Throwable $e) {
            Log::error('DescriptionMaster: resetMarketplaceDescription failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /product-description/generate — Anthropic Claude; tier or max_chars sets length target.
     */
    public function generateDescriptionWithAI(Request $request)
    {
        try {
            $validated = $request->validate([
                'product_name' => 'required|string',
                'current_text' => 'nullable|string',
                'tier' => 'nullable|string|in:1500,1000,800,600,2000',
                'max_chars' => 'nullable|integer|min:150|max:500000',
                'marketplace' => 'nullable|string',
            ]);

            $productName = $validated['product_name'];
            $current = trim((string) ($validated['current_text'] ?? ''));
            $marketplace = strtolower(trim((string) ($validated['marketplace'] ?? '')));

            if (! empty($validated['max_chars'])) {
                $maxLen = (int) $validated['max_chars'];
                $minLen = $marketplace !== ''
                    ? $this->minCharsForMarketplace($marketplace)
                    : max(150, (int) floor($maxLen * 0.85));
            } else {
                $tier = $validated['tier'] ?? '1500';
                [$minLen, $maxLen] = match ($tier) {
                    '2000' => [1900, 2000],
                    '1000' => [900, 1000],
                    '800' => [700, 800],
                    '600' => [500, 600],
                    default => [1400, 1500],
                };
            }

            if ($maxLen > 10000) {
                // Shopify / eBay "unlimited" tiers — generate a strong long-form block, not 500k chars.
                $maxLen = min($maxLen, 8000);
                $minLen = min($minLen, max(150, (int) floor($maxLen * 0.85)));
            }

            $prompt = "Generate a detailed product description of minimum {$minLen} characters and maximum {$maxLen} characters.\n".
                "Product: {$productName}\n".
                ($current !== '' ? "Existing notes (optional reference):\n{$current}\n\n" : '').
                'Include features, benefits, specifications, use cases, and quality highlights. '.
                'Make it comprehensive and persuasive. Plain text only (no HTML, no markdown headings).';

            $apiKey = config('services.anthropic.key') ?: env('ANTHROPIC_API_KEY');
            if (! $apiKey) {
                Log::warning('DescriptionMaster: generateDescriptionWithAI skipped, ANTHROPIC_API_KEY not configured');

                return response()->json([
                    'success' => false,
                    'message' => 'Anthropic API key is not configured.',
                ], 422);
            }

            $url = 'https://api.anthropic.com/v1/messages';
            $model = config('services.anthropic.model', 'claude-3-haiku-20240307');
            $params = [
                'model' => $model,
                'max_tokens' => 4096,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ];

            $resp = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(120)->post($url, $params);

            if (! $resp->successful()) {
                Log::warning('DescriptionMaster: Anthropic API error', [
                    'status' => $resp->status(),
                    'body_preview' => mb_substr($resp->body(), 0, 500),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'AI request failed: '.$resp->body(),
                ], 500);
            }

            $text = trim((string) data_get($resp->json(), 'content.0.text', ''));
            if ($text === '') {
                return response()->json(['success' => false, 'message' => 'Empty AI response.'], 422);
            }

            $pad = ' Additional quality details, warranty confidence, and everyday usability make this product a dependable choice for home and professional use.';
            while (mb_strlen($text) < $minLen) {
                $text .= $pad;
            }
            if (mb_strlen($text) > $maxLen) {
                $text = mb_substr($text, 0, $maxLen);
            }

            return response()->json([
                'success' => true,
                'description' => $text,
                'length' => mb_strlen($text),
                'tier' => $validated['tier'] ?? (string) $maxLen,
                'max_chars' => $maxLen,
            ]);
        } catch (\Throwable $e) {
            Log::error('DescriptionMaster: generateDescriptionWithAI failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /product-description/fetch-amazon-aplus
     */
    public function fetchAmazonAplusContent(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
            ]);
            $sku = $this->normalizeSku($validated['sku']);
            if ($sku === '') {
                return response()->json(['success' => false, 'message' => 'Invalid SKU'], 422);
            }

            $res = app(AmazonSpApiService::class)->fetchAplusContent($sku);
            if (! ($res['success'] ?? false)) {
                return response()->json(['success' => false, 'message' => (string) ($res['message'] ?? 'Fetch failed')], 422);
            }

            $data = (array) ($res['data'] ?? []);
            $html = (string) ($data['description_html'] ?? '');
            $images = (array) ($data['images'] ?? []);
            $plain = $this->truncateDescriptionForMarketplace($html !== '' ? $html : (string) ($data['description_plain'] ?? ''), 'amazon');

            if (Schema::hasTable('product_master')) {
                $update = [];
                if (Schema::hasColumn('product_master', 'amazon_aplus_content')) {
                    $update['amazon_aplus_content'] = $html;
                }
                if (Schema::hasColumn('product_master', 'amazon_aplus_images')) {
                    $encoded = json_encode(array_values($images), JSON_UNESCAPED_SLASHES);
                    if ($encoded !== false) {
                        $update['amazon_aplus_images'] = $encoded;
                    }
                }
                if ($plain !== '') {
                    if (Schema::hasColumn('product_master', 'description_1500')) {
                        $update['description_1500'] = $plain;
                    }
                    if (Schema::hasColumn('product_master', 'product_description')) {
                        $update['product_description'] = $plain;
                    }
                }
                if ($update !== []) {
                    ProductMaster::query()->where('sku', $sku)->update($update);
                }
            }

            if ($plain !== '') {
                $this->saveDescriptionToMarketplaceTable('amazon', $sku, $plain);
            }

            return response()->json([
                'success' => true,
                'message' => 'Amazon listing description fetched (product_description attribute; plain text synced to DESC 1500).',
                'data' => array_merge($data, [
                    'description_plain' => $plain,
                    'plain_length' => mb_strlen($plain),
                    'char_limit' => $this->maxCharsForMarketplace('amazon'),
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::error('DescriptionMaster: fetchAmazonAplusContent failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /product-description/regenerate-marketplace
     */
    public function regenerateDescriptionForMarketplace(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'marketplace' => 'required|string',
                'description' => 'nullable|string',
            ]);

            $sku = $this->normalizeSku($validated['sku']);
            $marketplace = strtolower(trim($validated['marketplace']));
            $description = trim((string) ($validated['description'] ?? ''));
            if ($description === '') {
                $pm = ProductMaster::query()->where('sku', $sku)->first();
                $description = trim((string) ($pm->description_1500 ?? $pm->product_description ?? ''));
            }

            $payload = $this->buildDescriptionPayloadWithImages($sku, $description, $marketplace);

            return response()->json([
                'success' => true,
                'marketplace' => $marketplace,
                'description' => $payload['plain'],
                'description_html' => $payload['html'],
                'images' => $payload['images'],
            ]);
        } catch (\Throwable $e) {
            Log::error('DescriptionMaster: regenerateDescriptionForMarketplace failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /product-description/with-images
     */
    public function getDescriptionWithImages(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'marketplace' => 'nullable|string',
                'description' => 'nullable|string',
            ]);

            $sku = $this->normalizeSku($validated['sku']);
            $marketplace = strtolower(trim((string) ($validated['marketplace'] ?? 'amazon')));
            $description = trim((string) ($validated['description'] ?? ''));
            if ($description === '') {
                $pm = ProductMaster::query()->where('sku', $sku)->first();
                $description = trim((string) ($pm->description_1500 ?? $pm->product_description ?? ''));
            }

            $payload = $this->buildDescriptionPayloadWithImages($sku, $description, $marketplace);

            return response()->json([
                'success' => true,
                'marketplace' => $marketplace,
                'description' => $payload['plain'],
                'description_html' => $payload['html'],
                'images' => $payload['images'],
            ]);
        } catch (\Throwable $e) {
            Log::error('DescriptionMaster: getDescriptionWithImages failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /product-description/pull-shopify — fetch the live Shopify description (rich HTML + product images) for one SKU.
     * Read-only against Shopify. Optionally persists to product_master.description_html when save=true.
     */
    public function pullShopifyDescription(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'save' => 'nullable|boolean',
            ]);
            $sku = $this->normalizeSku($validated['sku']);
            if ($sku === '') {
                return response()->json(['success' => false, 'message' => 'Invalid SKU'], 422);
            }

            $res = app(ShopifyApiService::class)->fetchProductDescriptionHtml($sku);
            if (! ($res['success'] ?? false)) {
                return response()->json(['success' => false, 'message' => (string) ($res['message'] ?? 'Fetch failed')], 422);
            }

            $html = (string) ($res['html'] ?? '');
            $images = array_values((array) ($res['images'] ?? []));

            if ($request->boolean('save') && Schema::hasColumn('product_master', 'description_html')) {
                ProductMaster::query()->where('sku', $sku)->update(['description_html' => $html]);
            }

            return response()->json([
                'success' => true,
                'message' => (string) ($res['message'] ?? 'Fetched.'),
                'sku' => $sku,
                'description_html' => $html,
                'images' => $images,
                'title' => (string) ($res['title'] ?? ''),
                'source' => (string) ($res['source'] ?? ''),
            ]);
        } catch (\Throwable $e) {
            Log::error('DescriptionMaster: pullShopifyDescription failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch the LIVE description (rich HTML) for one SKU from a specific marketplace. Read-only.
     * Returns ['success'=>bool,'message'=>string,'html'=>?string,'images'=>?array,'source'=>?string].
     */
    private function fetchLiveMarketplaceDescription(string $marketplace, string $sku): array
    {
        try {
            switch ($marketplace) {
                case 'shopify_main':
                    return $this->stripBulletsIfNeeded($marketplace, app(ShopifyApiService::class)->fetchProductDescriptionHtml($sku));

                case 'ebay':
                    return $this->stripBulletsIfNeeded($marketplace, app(EbayApiService::class)->fetchRawDescriptionHtml($sku));

                case 'ebay2':
                    return $this->stripBulletsIfNeeded($marketplace, app(Ebay2ApiService::class)->fetchRawDescriptionHtml($sku));

                case 'temu':
                    return app(TemuApiService::class)->fetchDescriptionHtml($sku);

                case 'ebay3':
                    return $this->stripBulletsIfNeeded($marketplace, app(EbayThreeApiService::class)->fetchRawDescriptionHtml($sku));

                case 'shopify_pls':
                    return $this->stripBulletsIfNeeded($marketplace, app(ShopifyPLSApiService::class)->fetchProductDescriptionHtml($sku));

                case 'reverb':
                    return $this->stripBulletsIfNeeded($marketplace, app(ReverbApiService::class)->fetchDescriptionHtml($sku));

                case 'macy':
                    return $this->stripBulletsIfNeeded($marketplace, app(MacysApiService::class)->fetchDescriptionHtml($sku));

                case 'amazon':
                    $r = app(AmazonSpApiService::class)->fetchAplusContent($sku);
                    if ($r['success'] ?? false) {
                        $data = (array) ($r['data'] ?? []);
                        $html = (string) ($data['description_html'] ?? '');
                        $images = array_values((array) ($data['images'] ?? []));
                        // Amazon A+ stores its images separately from the text body; embed any that aren't
                        // already referenced in the HTML so they render in the editor.
                        foreach ($images as $u) {
                            $u = (string) $u;
                            if ($u !== '' && strpos($html, $u) === false) {
                                $html .= '<p><img src="'.e($u).'" alt=""></p>';
                            }
                        }

                        return [
                            'success' => true,
                            'message' => 'Fetched Amazon listing description (product_description attribute via SP-API).',
                            'html' => $html,
                            'images' => $images,
                            'source' => 'amazon',
                        ];
                    }

                    return ['success' => false, 'message' => (string) ($r['message'] ?? 'Amazon fetch failed.')];

                case 'wayfair':
                    return app(WayfairApiService::class)->fetchDescriptionHtml($sku);

                case 'bestbuy':
                    return app(BestBuyApiService::class)->fetchDescriptionHtml($sku);

                default:
                    return ['success' => false, 'message' => 'Live fetch not available for this platform yet.'];
            }
        } catch (\Throwable $e) {
            Log::warning('DescriptionMaster: live fetch failed', ['marketplace' => $marketplace, 'sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Remove the Bullet Points block from a fetched description so the editor shows only the description
     * (bullets have their own master). Bullet placement differs per channel:
     *  - Shopify Main/PLS, eBay1/eBay2: HTML "About Item" block in the body  -> removeAboutItemBlock()
     *  - Reverb: HTML "Highlighted Features" block in the body               -> removeAboutItemBlock()
     *  - Macy's: plain-text "About Item ..." before "Product Description"     -> stripMacyAboutItemText()
     *  - eBay3 (Item Specifics), Temu (goodsSummary), Amazon (A+): bullets are a
     *    SEPARATE field, never in the description -> nothing to strip.
     *  - Wayfair / Best Buy: key-feature lines are converted to/from HTML lists on fetch/push.
     */
    private function stripBulletsIfNeeded(string $marketplace, array $res): array
    {
        if (! ($res['success'] ?? false) || empty($res['html'])) {
            return $res;
        }

        $html = (string) $res['html'];
        if (in_array($marketplace, ['shopify_main', 'shopify_pls', 'ebay', 'ebay2', 'reverb'], true)) {
            $res['html'] = \App\Services\Support\ShopifyBulletPointsFormatter::removeAboutItemBlock($html);
        } elseif ($marketplace === 'macy') {
            $res['html'] = $this->stripMacyAboutItemText($html);
        }

        return $res;
    }

    /**
     * Macy's stores bullets as leading plain "About Item ..." text before the "Product Description" body.
     * Keep from "Product Description" onward; if there's no such marker, leave the text unchanged.
     */
    private function stripMacyAboutItemText(string $text): string
    {
        $t = trim($text);
        $pos = mb_stripos($t, 'Product Description');
        if ($pos !== false) {
            return trim(mb_substr($t, $pos));
        }

        return $t;
    }

    /**
     * POST /product-description/pull-marketplace — fetch ONE marketplace's live description into the editor.
     * Optionally persists to that marketplace's metrics description_master when save=true.
     */
    public function pullMarketplaceDescription(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'marketplace' => 'required|string',
                'save' => 'nullable|boolean',
            ]);
            $sku = $this->normalizeSku($validated['sku']);
            $marketplace = strtolower(trim($validated['marketplace']));
            if (! in_array($marketplace, array_keys($this->marketplaceTableMap()), true)) {
                return response()->json(['success' => false, 'message' => 'Unknown or unsupported marketplace'], 422);
            }
            if ($sku === '') {
                return response()->json(['success' => false, 'message' => 'Invalid SKU'], 422);
            }

            $res = $this->fetchLiveMarketplaceDescription($marketplace, $sku);
            if (! ($res['success'] ?? false)) {
                return response()->json(['success' => false, 'message' => (string) ($res['message'] ?? 'Fetch failed')], 422);
            }

            $html = (string) ($res['html'] ?? '');
            if ($request->boolean('save')) {
                $this->saveDescriptionToMarketplaceTable(
                    $marketplace,
                    $sku,
                    $this->prepareDescriptionForMetricsSave($html, $marketplace)
                );
                if ($marketplace === 'amazon') {
                    $this->syncAmazonPlainDescriptionToProductMaster($sku, $html);
                }
            }

            return response()->json([
                'success' => true,
                'message' => (string) ($res['message'] ?? 'Fetched.'),
                'sku' => $sku,
                'marketplace' => $marketplace,
                'description_html' => $html,
                'description_plain' => $this->truncateDescriptionForMarketplace($html, $marketplace),
                'char_limit' => $this->maxCharsForMarketplace($marketplace),
                'images' => array_values((array) ($res['images'] ?? [])),
                'source' => (string) ($res['source'] ?? ''),
            ]);
        } catch (\Throwable $e) {
            Log::error('DescriptionMaster: pullMarketplaceDescription failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /product-description/pull-all — fetch EVERY marketplace's live description for one SKU and save each
     * to its metrics description_master. Used by the row Pull button (no modal). Returns per-marketplace results.
     */
    public function pullAllDescriptions(Request $request)
    {
        try {
            $validated = $request->validate(['sku' => 'required|string']);
            $sku = $this->normalizeSku($validated['sku']);
            if ($sku === '') {
                return response()->json(['success' => false, 'message' => 'Invalid SKU'], 422);
            }

            $results = [];
            foreach (array_keys($this->marketplaceTableMap()) as $marketplace) {
                $res = $this->fetchLiveMarketplaceDescription($marketplace, $sku);
                $ok = (bool) ($res['success'] ?? false);
                $html = $ok ? (string) ($res['html'] ?? '') : '';
                if ($ok) {
                    $stored = $this->prepareDescriptionForMetricsSave($html, $marketplace);
                    $this->saveDescriptionToMarketplaceTable($marketplace, $sku, $stored);
                    if ($marketplace === 'amazon') {
                        $this->syncAmazonPlainDescriptionToProductMaster($sku, $html);
                    }
                }
                $results[$marketplace] = [
                    'success' => $ok,
                    'message' => (string) ($res['message'] ?? ''),
                    'chars' => mb_strlen($ok ? $this->truncateDescriptionForMarketplace($html, $marketplace) : ''),
                    'char_limit' => $this->maxCharsForMarketplace($marketplace),
                ];
            }

            $okCount = count(array_filter($results, fn ($r) => $r['success']));

            return response()->json([
                'success' => $okCount > 0,
                'sku' => $sku,
                'results' => $results,
                'total_success' => $okCount,
                'total' => count($results),
                'message' => "Fetched {$okCount} of ".count($results).' marketplace(s).',
            ]);
        } catch (\Throwable $e) {
            Log::error('DescriptionMaster: pullAllDescriptions failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /product-description/save-marketplace — store the editor HTML for one marketplace into its
     * metrics description_master (no marketplace push, no character-limit truncation).
     */
    public function saveMarketplaceDescription(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'marketplace' => 'required|string',
                'description' => 'nullable|string',
            ]);
            $sku = $this->normalizeSku($validated['sku']);
            $marketplace = strtolower(trim($validated['marketplace']));
            if (! in_array($marketplace, array_keys($this->marketplaceTableMap()), true)) {
                return response()->json(['success' => false, 'message' => 'Unknown or unsupported marketplace'], 422);
            }
            if ($sku === '') {
                return response()->json(['success' => false, 'message' => 'Invalid SKU'], 422);
            }

            $stored = $this->prepareDescriptionForMetricsSave((string) ($validated['description'] ?? ''), $marketplace);
            $ok = $this->saveDescriptionToMarketplaceTable($marketplace, $sku, $stored);

            return response()->json([
                'success' => $ok,
                'message' => $ok ? 'Saved.' : 'Could not save (metrics table/column missing).',
                'description_stored' => $stored,
                'description_plain' => $this->normalizeDescriptionPlainText($stored),
            ]);
        } catch (\Throwable $e) {
            Log::error('DescriptionMaster: saveMarketplaceDescription failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function marketplaceTableMap(): array
    {
        return ProductMasterMarketplaceMaps::descriptionTableMap();
    }

    private function maxCharsForMarketplace(string $marketplace): int
    {
        // Platform listing limits (Amazon/Temu: plain text; Shopify: no hard cap; eBay: 500k — ~800 visible on mobile).
        return match ($marketplace) {
            'amazon', 'temu', 'temu2', 'walmart', 'shein', 'aliexpress' => 2000,
            'shopify_main', 'shopify_pls' => 500000,
            'ebay', 'ebay2', 'ebay3' => 500000,
            'reverb', 'bestbuy', 'doba' => 1500,
            'wayfair' => 2000,
            'macy', 'faire' => 600,
            default => 1500,
        };
    }

    /**
     * Minimum plain-text length for AI generation (Amazon recommends ≥150 chars).
     */
    private function minCharsForMarketplace(string $marketplace): int
    {
        return match ($marketplace) {
            'amazon' => 150,
            default => max(150, (int) floor($this->maxCharsForMarketplace($marketplace) * 0.85)),
        };
    }

    /**
     * Push payloads use plain text. Strip HTML from pulled listing / editor content before counting chars.
     */
    private function normalizeDescriptionPlainText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (preg_match('/<[^>]+>/', $text)) {
            $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Plain-text body truncated to the marketplace character limit (used before API push).
     */
    private function truncateDescriptionForMarketplace(string $text, string $marketplace): string
    {
        $plain = $this->normalizeDescriptionPlainText($text);
        if ($plain === '') {
            return '';
        }

        $max = $this->maxCharsForMarketplace($marketplace);
        if (mb_strlen($plain) > $max) {
            return mb_substr($plain, 0, $max);
        }

        return $plain;
    }

    /**
     * All Description Master marketplaces push formatted descriptions (HTML-aware per channel API).
     */
    private function marketplacesPushHtml(string $marketplace): bool
    {
        return isset($this->marketplaceTableMap()[$marketplace]);
    }

    /**
     * Push payload: preserve HTML for all marketplaces; char limits apply to plain-text length.
     */
    private function prepareDescriptionForPush(string $text, string $marketplace): string
    {
        return $this->prepareDescriptionForMetricsSave($text, $marketplace);
    }

    /**
     * Preserve TinyMCE HTML in description_master; enforce limits on plain-text length.
     */
    private function prepareDescriptionForMetricsSave(string $text, string $marketplace): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $max = $this->maxCharsForMarketplace($marketplace);
        if (mb_strlen($this->normalizeDescriptionPlainText($text)) > $max) {
            return $this->truncateDescriptionForMarketplace($text, $marketplace);
        }

        return $text;
    }

    /**
     * Prefer explicit HTML from the client; otherwise keep stored HTML when push plain text matches.
     */
    private function resolveDescriptionForMetricsSave(
        string $sku,
        string $marketplace,
        string $requestPlain,
        string $requestHtml,
        string $masterText
    ): string {
        if ($requestHtml !== '') {
            return $requestHtml;
        }

        if ($requestPlain !== '') {
            $existing = $this->loadDescriptionMasterTextForSku($sku, $marketplace);
            if ($existing !== ''
                && $this->normalizeDescriptionPlainText($existing) === $this->normalizeDescriptionPlainText($requestPlain)) {
                return $existing;
            }

            return $requestPlain;
        }

        return $masterText;
    }

    private function loadDescriptionMasterTextForSku(string $sku, string $marketplace): string
    {
        $tables = $this->marketplaceTableMap();
        if (! isset($tables[$marketplace])) {
            return '';
        }

        $map = $this->loadDescriptionMasterForSkuList($tables[$marketplace], [$sku]);

        return $map[$sku] ?? '';
    }

    /**
     * After Amazon live fetch, keep PM tier columns in sync with listing plain text (1500-char group).
     */
    private function syncAmazonPlainDescriptionToProductMaster(string $sku, string $content): void
    {
        $plain = $this->truncateDescriptionForMarketplace($content, 'amazon');
        if ($plain === '' || ! Schema::hasTable('product_master')) {
            return;
        }

        $update = [];
        if (Schema::hasColumn('product_master', 'description_1500')) {
            $update['description_1500'] = $plain;
        }
        if (Schema::hasColumn('product_master', 'product_description')) {
            $update['product_description'] = $plain;
        }
        if ($update !== []) {
            ProductMaster::query()->where('sku', $sku)->update($update);
        }
    }

    /**
     * After Shopify push, keep PM description_1000 in sync so Preview (PM) reflects the pushed copy.
     */
    private function syncShopifyPlainDescriptionToProductMaster(string $sku, string $content): void
    {
        $plain = $this->truncateDescriptionForMarketplace($content, 'shopify_main');
        if ($plain === '' || ! Schema::hasTable('product_master')) {
            return;
        }

        $update = [];
        if (Schema::hasColumn('product_master', 'description_1000')) {
            $update['description_1000'] = $plain;
        }
        if ($update !== []) {
            ProductMaster::query()->where('sku', $sku)->update($update);
        }
    }

    /**
     * @return array<string, string> normalized sku => description_master
     */
    private function loadDescriptionMasterForSkuList(string $table, array $rawSkus): array
    {
        if ($rawSkus === []) {
            return [];
        }
        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
                return [];
            }
            if (! Schema::hasColumn($table, 'description_master')) {
                return [];
            }

            return DB::table($table)
                ->whereIn('sku', $rawSkus)
                ->select('sku', 'description_master')
                ->get()
                ->mapWithKeys(function ($row) {
                    return [$this->normalizeSku($row->sku) => (string) ($row->description_master ?? '')];
                })
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning("DescriptionMaster: unable to load {$table}", ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array<string, string> normalized sku => bullet_points plain text
     */
    private function loadBulletPointsForSkuList(string $table, array $rawSkus): array
    {
        if ($rawSkus === []) {
            return [];
        }
        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'bullet_points')) {
                return [];
            }

            return DB::table($table)
                ->whereIn('sku', $rawSkus)
                ->select('sku', 'bullet_points')
                ->get()
                ->mapWithKeys(function ($row) {
                    return [$this->normalizeSku($row->sku) => (string) ($row->bullet_points ?? '')];
                })
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning("DescriptionMaster: unable to load bullet_points from {$table}", ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function defaultBulletsFromPmArray(array $row): string
    {
        $parts = array_filter(array_map('trim', [
            $row['bullet1'] ?? '',
            $row['bullet2'] ?? '',
            $row['bullet3'] ?? '',
            $row['bullet4'] ?? '',
            $row['bullet5'] ?? '',
        ]));

        return implode("\n", $parts);
    }

    private function saveDescriptionToMarketplaceTable(string $marketplace, string $sku, string $text): bool
    {
        $table = $this->marketplaceTableMap()[$marketplace] ?? null;
        if (! $table) {
            return false;
        }

        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
                return false;
            }
            if (! Schema::hasColumn($table, 'description_master')) {
                return false;
            }

            $existing = DB::table($table)->where('sku', $sku)->first();
            if ($existing) {
                DB::table($table)->where('sku', $sku)->update(['description_master' => $text, 'updated_at' => now()]);
            } else {
                DB::table($table)->insert(['sku' => $sku, 'description_master' => $text, 'created_at' => now(), 'updated_at' => now()]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning("DescriptionMaster: save failed for {$table}", ['sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Set description_master to empty for an existing metrics row (does not call external APIs).
     */
    private function clearDescriptionInMarketplaceTable(string $marketplace, string $sku): bool
    {
        $table = $this->marketplaceTableMap()[$marketplace] ?? null;
        if (! $table) {
            return false;
        }

        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
                return false;
            }
            if (! Schema::hasColumn($table, 'description_master')) {
                return false;
            }

            $exists = DB::table($table)->where('sku', $sku)->exists();
            if (! $exists) {
                return true;
            }

            DB::table($table)->where('sku', $sku)->update(['description_master' => '', 'updated_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            Log::warning("DescriptionMaster: clear failed for {$table}", ['sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function callMarketplaceDescriptionService(string $marketplace, string $sku, string $text, array $imageUrls = []): array
    {
        if ($marketplace === 'shopify_main') {
            return app(ShopifyApiService::class)->updateDescription($sku, $text, $imageUrls);
        }
        if ($marketplace === 'shopify_pls') {
            return app(ShopifyPLSApiService::class)->updateDescription($sku, $text, $imageUrls);
        }

        $map = ProductMasterMarketplaceMaps::descriptionServiceMap();

        try {
            if (! isset($map[$marketplace])) {
                return ['success' => false, 'message' => 'Unknown marketplace'];
            }
            [$class, $method] = $map[$marketplace];
            $service = app($class);
            if (! method_exists($service, $method)) {
                return ['success' => false, 'message' => 'Service method not available'];
            }

            $twoArgOnly = in_array($marketplace, ['ebay', 'ebay2', 'ebay3', 'doba', 'walmart', 'faire', 'shein', 'aliexpress'], true);
            $result = $twoArgOnly
                ? $service->{$method}($sku, $text)
                : $service->{$method}($sku, $text, $imageUrls);
            if (is_array($result)) {
                return [
                    'success' => (bool) ($result['success'] ?? false),
                    'message' => (string) ($result['message'] ?? 'Done'),
                ];
            }

            return ['success' => (bool) $result, 'message' => $result ? 'Updated' : 'Failed'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function normalizeSku(?string $sku): string
    {
        if (! $sku) {
            return '';
        }

        return str_replace("\u{00a0}", ' ', trim((string) $sku));
    }

    /**
     * Product Master tier text for a marketplace (mirrors getPmTextForMp() in the blade).
     */
    private function loadMasterDescriptionTextForSku(string $sku, string $marketplace): string
    {
        $pm = ProductMaster::query()->where('sku', $sku)->first();
        if (! $pm) {
            return '';
        }

        $d1500 = trim((string) ($pm->description_1500 ?? $pm->product_description ?? ''));
        $d1000 = trim((string) ($pm->description_1000 ?? ''));
        $d800 = trim((string) ($pm->description_800 ?? ''));
        $d600 = trim((string) ($pm->description_600 ?? ''));

        if (in_array($marketplace, ['amazon', 'temu', 'temu2', 'reverb', 'wayfair', 'bestbuy', 'walmart', 'shein', 'aliexpress'], true)) {
            $text = $d1500;
        } elseif (in_array($marketplace, ['shopify_main', 'shopify_pls', 'doba'], true)) {
            $text = $d1000 !== '' ? $d1000 : $d1500;
        } elseif (in_array($marketplace, ['ebay', 'ebay2', 'ebay3'], true)) {
            $text = $d800 !== '' ? $d800 : $d1500;
        } else {
            $text = $d600 !== '' ? $d600 : $d1500;
        }

        return $this->truncateDescriptionForMarketplace($text, $marketplace);
    }

    /**
     * @return array<string, array<string, string>> normalized sku => [marketplace => status]
     */
    private function loadDescriptionPushStatusesBySku(): array
    {
        if (! Schema::hasTable('description_marketplace_push_statuses')) {
            return [];
        }

        try {
            $statuses = [];
            DB::table('description_marketplace_push_statuses')
                ->select('sku', 'marketplace', 'status')
                ->whereNotNull('sku')
                ->orderByDesc('attempted_at')
                ->get()
                ->each(function ($row) use (&$statuses) {
                    $sku = $this->normalizeSku($row->sku);
                    $marketplace = strtolower(trim((string) $row->marketplace));
                    $status = strtolower(trim((string) $row->status));

                    if ($sku !== '' && $marketplace !== '' && in_array($status, ['success', 'failed'], true)) {
                        $statuses[$sku][$marketplace] = $status;
                    }
                });

            return $statuses;
        } catch (\Throwable $e) {
            Log::warning('DescriptionMaster: unable to load description push statuses', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function saveDescriptionPushStatus(string $sku, string $marketplace, string $status, string $message = ''): void
    {
        if (! Schema::hasTable('description_marketplace_push_statuses')) {
            return;
        }

        try {
            DB::table('description_marketplace_push_statuses')->updateOrInsert(
                [
                    'sku' => $this->normalizeSku($sku),
                    'marketplace' => strtolower(trim($marketplace)),
                ],
                [
                    'status' => in_array($status, ['success', 'failed'], true) ? $status : 'failed',
                    'message' => $message !== '' ? mb_substr($message, 0, 1000) : null,
                    'attempted_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('DescriptionMaster: unable to save description push status', [
                'sku' => $sku,
                'marketplace' => $marketplace,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{html: string, plain: string, images: array<int, string>}
     */
    private function buildDescriptionPayloadWithImages(string $sku, string $description, string $marketplace): array
    {
        $pm = ProductMaster::query()->where('sku', $sku)->first();
        $base = trim($description);
        $aplusHtml = trim((string) ($pm?->amazon_aplus_content ?? ''));
        $aplusImages = [];
        $aplusRaw = $pm?->amazon_aplus_images ?? null;
        if (is_string($aplusRaw) && trim($aplusRaw) !== '') {
            $decoded = json_decode($aplusRaw, true);
            if (is_array($decoded)) {
                $aplusImages = array_values(array_filter($decoded, fn ($v) => is_string($v) && trim($v) !== ''));
            }
        }
        if ($base === '' && $aplusHtml !== '') {
            $base = trim(strip_tags($aplusHtml));
        }

        $limit = $this->maxCharsForMarketplace($marketplace);
        if (mb_strlen($base) > $limit) {
            $base = mb_substr($base, 0, $limit);
        }

        $formatted = DescriptionWithImagesFormatter::buildHtmlWithImages(
            $base,
            $sku,
            $sku,
            (string) ($pm?->title150 ?? 'Product Image'),
            $marketplace === 'amazon' ? 9 : 12,
            $aplusImages
        );

        return [
            'html' => $formatted['html'],
            'plain' => $base,
            'images' => $formatted['images'],
        ];
    }
}
