<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\Concerns\RetriesMarketplacePush;
use App\Http\Controllers\ProductMaster\ProductMasterController as PMController;
use App\Models\BulletPointAiPromptRule;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ShopifyVariant;
use App\Services\ShopifyPlsTokenService;
use App\Services\Support\ShopifyBulletPointsFormatter;
use App\Services\Support\ShopifyBulletPullJobStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BulletPointMasterController extends Controller
{
    use RetriesMarketplacePush;

    public function index(Request $request)
    {
        $mode = $request->query('mode', '');
        $demo = $request->query('demo', '');

        return view('bullet-point-master', compact('mode', 'demo'));
    }

    public function getData(Request $request)
    {
        try {
            $this->ensureMarketplaceModelClassesResolvable();

            $baseResponse = app(PMController::class)->getViewProductData($request);
            $baseData = $baseResponse->getData(true);
            $products = $baseData['data'] ?? [];

            $marketTables = $this->marketplaceTableMap();
            $metricsByMarketplace = [];
            foreach ($marketTables as $marketplace => $table) {
                $metricsByMarketplace[$marketplace] = $this->loadMetricsBySku($table);
            }
            $pushStatusesBySku = $this->loadPushStatusesBySku();
            $shopifyTitlesBySku = $this->loadShopifyTitlesBySku();

            foreach ($products as &$row) {
                $sku = $this->normalizeSku($row['SKU'] ?? null);
                $defaultBullets = $this->combineBullets(
                    $row['bullet1'] ?? null,
                    $row['bullet2'] ?? null,
                    $row['bullet3'] ?? null,
                    $row['bullet4'] ?? null,
                    $row['bullet5'] ?? null
                );

                $bp = [];
                foreach (array_keys($marketTables) as $mp) {
                    // Only values saved for this marketplace (no default fallback) — UI uses this for push-state dots.
                    $bp[$mp] = $metricsByMarketplace[$mp][$sku] ?? '';
                }

                $row['bullet_points'] = $bp;
                $row['bullet_push_statuses'] = $pushStatusesBySku[$sku] ?? [];
                $row['default_bullets'] = $defaultBullets;
                $row['shopify_product_title'] = $shopifyTitlesBySku[mb_strtolower($sku)] ?? '';
            }

            return response()->json([
                'message' => 'Data loaded from database',
                'data' => $products,
                'status' => 200,
            ]);
        } catch (\Throwable $e) {
            Log::error('BulletPointMaster getData failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to load bullet points data.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * Backward-compatible endpoint used by the current blade.
     */
    public function getCombinedData(Request $request)
    {
        return $this->getData($request);
    }

    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'updates' => 'required|array|min:1',
                'updates.*.marketplace' => 'required|string',
                'updates.*.bullet_points' => 'nullable|string',
            ]);

            $sku = $this->normalizeSku($validated['sku']);
            $results = [];

            $allowedMarketplaces = array_keys($this->marketplaceTableMap());

            foreach ($validated['updates'] as $u) {
                $marketplace = strtolower(trim($u['marketplace']));
                $requestText = $this->cleanBulletText((string) ($u['bullet_points'] ?? ''));
                $masterText = $this->loadMasterBulletTextForSku($sku);
                $text = $requestText !== '' ? $requestText : ($masterText ?? '');

                if (! in_array($marketplace, $allowedMarketplaces, true)) {
                    $results[$marketplace] = ['success' => false, 'message' => 'Unknown or unsupported marketplace'];
                    continue;
                }

                Log::info('BulletPointMaster marketplace push started', [
                    'sku' => $sku,
                    'marketplace' => $marketplace,
                    'request_bullet_count' => $this->countBulletLines($requestText),
                    'master_bullet_count' => $this->countBulletLines($masterText ?? ''),
                    'using_master_bullets' => $requestText === '' && $masterText !== null,
                ]);

                $serviceResult = $this->invokeMarketplacePushWithRetries(
                    fn () => $this->callMarketplaceService($marketplace, $sku, $text),
                    'BulletPointMaster',
                    $marketplace,
                    $sku
                );

                $success = (bool) ($serviceResult['success'] ?? false);
                $tableSaved = $success
                    ? $this->saveToMarketplaceTable($marketplace, $sku, $text)
                    : false;
                $pushStatus = $success ? 'success' : 'failed';
                $pushMessage = $success
                    ? ($serviceResult['message'] ?? 'Updated')
                    : ($serviceResult['message'] ?? 'Unable to update this marketplace');
                $this->savePushStatus($sku, $marketplace, $pushStatus, $pushMessage);
                Log::info('BulletPointMaster marketplace push finished', [
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

            return response()->json([
                'success' => $totalFailed === 0,
                'results' => $results,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'message' => "Updated {$totalSuccess} marketplace(s)." . ($totalFailed > 0 ? " {$totalFailed} failed." : ''),
            ]);
        } catch (\Throwable $e) {
            Log::error('BulletPointMaster update failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateBulk(Request $request)
    {
        try {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.sku' => 'required|string',
                'items.*.updates' => 'required|array|min:1',
                'items.*.updates.*.marketplace' => 'required|string',
                'items.*.updates.*.bullet_points' => 'nullable|string',
            ]);

            $items = [];
            $totalSuccess = 0;
            $totalFailed = 0;

            foreach ($validated['items'] as $item) {
                $res = $this->update(new Request([
                    'sku' => $item['sku'],
                    'updates' => $item['updates'],
                ]));
                $payload = $res->getData(true);
                $items[] = ['sku' => $item['sku'], 'results' => $payload['results'] ?? []];
                $totalSuccess += $payload['total_success'] ?? 0;
                $totalFailed += $payload['total_failed'] ?? 0;
            }

            return response()->json([
                'success' => $totalFailed === 0,
                'items' => $items,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'message' => "Bulk update finished: {$totalSuccess} success, {$totalFailed} failed.",
            ]);
        } catch (\Throwable $e) {
            Log::error('BulletPointMaster updateBulk failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function pullShopifyBulletsToMaster(Request $request)
    {
        $pullLog = $this->shopifyBulletPullLogger();
        $sku = '';
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
            ]);

            $sku = $this->normalizeSku($validated['sku']);
            $pullLog->info('Shopify bullet pull started', ['sku' => $sku]);
            $product = $this->findProductMasterBySku($sku);
            if (! $product) {
                $pullLog->warning('Product Master row not found', ['sku' => $sku]);

                return response()->json([
                    'success' => false,
                    'sku' => $sku,
                    'status' => 'product_not_found',
                    'message' => 'Product Master row not found.',
                ], 404);
            }

            $currentBullets = $this->productMasterBulletArray($product);
            $shopify = $this->fetchShopifyBodyHtmlForSku($sku);
            if (! ($shopify['success'] ?? false)) {
                $pullLog->warning('Shopify fetch failed', [
                    'sku' => $sku,
                    'message' => $shopify['message'] ?? 'Unable to fetch Shopify product.',
                    'variant_id' => $shopify['variant_id'] ?? null,
                    'product_id' => $shopify['product_id'] ?? null,
                ]);

                return response()->json([
                    'success' => false,
                    'sku' => $sku,
                    'status' => 'shopify_fetch_failed',
                    'message' => $shopify['message'] ?? 'Unable to fetch Shopify product.',
                    'current_bullets' => $currentBullets,
                ], 422);
            }

            $adminExtracted = ShopifyBulletPointsFormatter::extractBulletPointsForImport((string) ($shopify['body_html'] ?? ''));
            $extracted = $adminExtracted;
            $publicShopify = $this->fetchPublicShopifyProductHtmlForSku($sku);
            if (($publicShopify['body_html'] ?? '') !== '') {
                $publicExtracted = ShopifyBulletPointsFormatter::extractBulletPointsForImport((string) $publicShopify['body_html']);
                if (count($publicExtracted['bullets'] ?? []) > 0) {
                    $pullLog->info('Using public Shopify storefront bullets for Product Master pull', [
                        'sku' => $sku,
                        'admin_format' => $adminExtracted['format'] ?? null,
                        'admin_count' => count($adminExtracted['bullets'] ?? []),
                        'public_format' => $publicExtracted['format'] ?? null,
                        'public_count' => count($publicExtracted['bullets'] ?? []),
                        'public_url' => $publicShopify['url'] ?? null,
                    ]);
                    $extracted = $publicExtracted;
                    $shopify['body_html'] = $publicShopify['body_html'];
                }
            }

            if (count($extracted['bullets'] ?? []) <= 1) {
                $cachedShopify = $this->fetchCachedShopifyBodyHtmlForSku($sku);
                if (($cachedShopify['body_html'] ?? '') !== '') {
                    $cachedExtracted = ShopifyBulletPointsFormatter::extractBulletPointsForImport((string) $cachedShopify['body_html']);
                    if (count($cachedExtracted['bullets'] ?? []) > count($extracted['bullets'] ?? [])) {
                        $pullLog->info('Using cached Shopify catalog bullets because live sources returned fewer bullets', [
                            'sku' => $sku,
                            'selected_format' => $extracted['format'] ?? null,
                            'selected_count' => count($extracted['bullets'] ?? []),
                            'cached_format' => $cachedExtracted['format'] ?? null,
                            'cached_count' => count($cachedExtracted['bullets'] ?? []),
                            'cached_product_id' => $cachedShopify['product_id'] ?? null,
                            'cached_variant_id' => $cachedShopify['variant_id'] ?? null,
                        ]);
                        $extracted = $cachedExtracted;
                        $shopify['body_html'] = $cachedShopify['body_html'];
                        $shopify['product_id'] = $cachedShopify['product_id'] ?? ($shopify['product_id'] ?? null);
                        $shopify['variant_id'] = $cachedShopify['variant_id'] ?? ($shopify['variant_id'] ?? null);
                    }
                }
            }
            $shopifyBullets = array_values(array_filter(array_map(
                fn ($line) => ShopifyBulletPointsFormatter::cleanBulletLine((string) $line),
                array_slice($extracted['bullets'] ?? [], 0, 5)
            ), fn ($line) => $line !== ''));
            if ($shopifyBullets === []) {
                $pullLog->warning('No Shopify bullets detected', [
                    'sku' => $sku,
                    'shopify_product_id' => $shopify['product_id'] ?? null,
                    'variant_id' => $shopify['variant_id'] ?? null,
                    'format' => $extracted['format'] ?? 'not_detected',
                    'confidence' => $extracted['confidence'] ?? 0,
                    'body_html_chars' => strlen((string) ($shopify['body_html'] ?? '')),
                    'body_preview' => mb_substr(strip_tags((string) ($shopify['body_html'] ?? '')), 0, 500),
                ]);

                return response()->json([
                    'success' => false,
                    'sku' => $sku,
                    'status' => 'no_bullets_detected',
                    'message' => 'No supported Shopify bullet format detected.',
                    'format' => $extracted['format'] ?? 'not_detected',
                    'confidence' => $extracted['confidence'] ?? 0,
                    'current_bullets' => $currentBullets,
                    'shopify_product_id' => $shopify['product_id'] ?? null,
                ], 422);
            }

            $update = [];
            for ($i = 1; $i <= 5; $i++) {
                $update['bullet'.$i] = $shopifyBullets[$i - 1] ?? '';
            }
            $product->fill($update);
            $product->save();

            $newBullets = $this->productMasterBulletArray($product->fresh());
            $matchedBefore = $this->normalizedBulletArray($currentBullets) === $this->normalizedBulletArray($shopifyBullets);
            Log::info('BulletPointMaster: pulled Shopify bullets to Product Master', [
                'sku' => $sku,
                'shopify_product_id' => $shopify['product_id'] ?? null,
                'variant_id' => $shopify['variant_id'] ?? null,
                'format' => $extracted['format'] ?? null,
                'confidence' => $extracted['confidence'] ?? null,
                'matched_before' => $matchedBefore,
                'bullet_count' => count($shopifyBullets),
            ]);
            $pullLog->info('Shopify bullets saved to Product Master', [
                'sku' => $sku,
                'shopify_product_id' => $shopify['product_id'] ?? null,
                'variant_id' => $shopify['variant_id'] ?? null,
                'format' => $extracted['format'] ?? null,
                'confidence' => $extracted['confidence'] ?? null,
                'matched_before' => $matchedBefore,
                'before_count' => count($currentBullets),
                'shopify_count' => count($shopifyBullets),
                'after_count' => count($newBullets),
            ]);

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'status' => $matchedBefore ? 'already_matched' : 'imported_to_product_master',
                'message' => $matchedBefore ? 'Already matched Shopify bullets.' : 'Imported Shopify bullets to Product Master.',
                'format' => $extracted['format'] ?? 'unknown',
                'confidence' => $extracted['confidence'] ?? 0,
                'shopify_product_id' => $shopify['product_id'] ?? null,
                'variant_id' => $shopify['variant_id'] ?? null,
                'before_bullets' => $currentBullets,
                'shopify_bullets' => $shopifyBullets,
                'after_bullets' => $newBullets,
            ]);
        } catch (\Throwable $e) {
            Log::error('BulletPointMaster pullShopifyBulletsToMaster failed', ['error' => $e->getMessage()]);
            $pullLog->error('Shopify bullet pull exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function startShopifyPullJob(Request $request, ShopifyBulletPullJobStore $store)
    {
        $validated = $request->validate([
            'skus' => 'required|array|min:1',
            'skus.*' => 'required|string',
        ]);

        $current = $store->load();
        if ($store->isActive($current)) {
            return response()->json([
                'success' => false,
                'message' => 'A Shopify pull is already running or paused.',
                'job' => $current,
            ], 409);
        }

        $job = $store->create($validated['skus'], 6);
        $this->launchShopifyPullProcess();
        $this->shopifyBulletPullLogger()->info('Background Shopify bullet pull queued', [
            'total' => $job['total'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Background Shopify pull started.',
            'job' => $job,
        ]);
    }

    public function shopifyPullJobStatus(ShopifyBulletPullJobStore $store)
    {
        return response()->json([
            'success' => true,
            'job' => $store->load(),
        ]);
    }

    public function pauseShopifyPullJob(ShopifyBulletPullJobStore $store)
    {
        $job = $store->update(function (array $state) {
            if (($state['status'] ?? 'idle') === 'running') {
                $state['status'] = 'paused';
                $state['last_message'] = 'Pause requested. Current SKU will finish first.';
            }

            return $state;
        });
        $store->appendMessage('Pause requested. Current SKU will finish first.', false);

        return response()->json(['success' => true, 'job' => $job]);
    }

    public function resumeShopifyPullJob(ShopifyBulletPullJobStore $store)
    {
        $job = $store->update(function (array $state) {
            if (($state['status'] ?? 'idle') === 'paused') {
                $state['status'] = 'running';
                $state['last_message'] = 'Resumed Shopify pull.';
                $state['finished_at'] = null;
            }

            return $state;
        });
        $store->appendMessage('Resumed Shopify pull.', true);

        return response()->json(['success' => true, 'job' => $job]);
    }

    public function stopShopifyPullJob(ShopifyBulletPullJobStore $store)
    {
        $job = $store->update(function (array $state) {
            if (in_array($state['status'] ?? 'idle', ['running', 'paused'], true)) {
                $state['status'] = 'stopping';
                $state['last_message'] = 'Stop requested. Current SKU will finish first.';
            }

            return $state;
        });
        $store->appendMessage('Stop requested. Current SKU will finish first.', false);

        return response()->json(['success' => true, 'job' => $job]);
    }

    public function aiPromptRules()
    {
        return response()->json([
            'success' => true,
            'rules' => $this->bulletPointAiPromptRules(),
        ]);
    }

    public function saveAiPromptRules(Request $request)
    {
        $validated = $request->validate([
            'rules' => ['required', 'string', 'max:12000'],
        ]);

        if (! Schema::hasTable('bullet_point_ai_prompt_rules')) {
            return response()->json([
                'success' => false,
                'message' => 'Prompt rules table is missing. Please run migrations.',
            ], 422);
        }

        BulletPointAiPromptRule::query()->updateOrCreate(
            ['id' => 1],
            ['rules' => trim($validated['rules'])]
        );

        return response()->json([
            'success' => true,
            'message' => 'AI prompt rules saved.',
            'rules' => trim($validated['rules']),
        ]);
    }

    public function generateBulletPoints(Request $request)
    {
        try {
            $validated = $request->validate([
                'product_name' => 'required|string',
                'current_text' => 'nullable|string',
                'product_id' => 'nullable|string',
                'prompt_details' => 'nullable|string|max:4000',
            ]);

            $productId = (string) ($validated['product_id'] ?? $request->input('sku', ''));
            $productName = (string) $validated['product_name'];
            $promptDetails = trim((string) ($validated['prompt_details'] ?? ''));
            $currentText = trim((string) ($validated['current_text'] ?? ''));
            Log::info('AI Generation Started', [
                'product_name' => $productName,
                'product_id' => $productId,
                'has_prompt_details' => $promptDetails !== '',
                'has_current_text' => $currentText !== '',
            ]);
            $promptRules = $this->bulletPointAiPromptRules();

            $prompt = ($currentText !== ''
                ? "Regenerate the existing 5 product bullet points for this product: {$productName}.\n\n" .
                    "CURRENT BULLET POINTS TO USE AS CONTENT:\n{$currentText}\n\n" .
                    "Use the current bullets as the source content, preserve accurate product facts, remove repetition, and rewrite them according to the AI prompt rules below. Return exactly 5 regenerated bullet lines.\n\n"
                : "Create 5 product bullet points optimized for Amazon, Walmart, eBay, Shopify, and other eCommerce marketplaces for this product: {$productName}.\n\n") .
                ($promptDetails !== '' ? "USER PROVIDED PRODUCT DETAILS / KEYWORDS:\n{$promptDetails}\n\n" : '') .
                "AI PROMPT RULES:\n" .
                $promptRules;
            Log::info('AI Prompt:', ['prompt' => $prompt]);

            $apiKey = config('services.anthropic.key') ?: env('ANTHROPIC_API_KEY');
            if (! $apiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anthropic API key is not configured.',
                ], 422);
            }

            $url = 'https://api.anthropic.com/v1/messages';
            $model = (string) config('services.anthropic.model', 'claude-sonnet-4-20250514');
            $params = [
                'model' => $model,
                'max_tokens' => 2500,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ];
            Log::info('AI Generation Request', ['model' => $model, 'prompt' => $prompt]);
            Log::info('AI API Request', ['url' => $url, 'params' => $params]);

            $resp = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(90)->post($url, $params);
            Log::info('AI API Response', ['response' => $resp->json()]);

            if (! $resp->successful()) {
                $errorType = (string) data_get($resp->json(), 'error.type', '');
                $errorMessage = (string) data_get($resp->json(), 'error.message', '');
                Log::error('AI Generation Failed', [
                    'status' => $resp->status(),
                    'body' => $resp->body(),
                    'model' => $model,
                ]);
                $message = $errorType === 'not_found_error'
                    ? "AI model '{$model}' is not available for this API key. Update ANTHROPIC_MODEL in .env."
                    : ($errorMessage !== '' ? $errorMessage : 'AI request failed');

                return response()->json(['success' => false, 'message' => $message, 'error' => $resp->body()], 500);
            }

            $text = data_get($resp->json(), 'content.0.text', '');
            $bullets = $this->parseBulletsFromText($text);
            $validationIssues = $this->validateMarketplaceAiBullets($bullets);

            if ($validationIssues !== []) {
                Log::info('AI Generation Needs Repair', ['issues' => $validationIssues, 'bullets' => $bullets]);
                $repairPrompt = $prompt."\n\nThe previous output did not follow the rules. Fix these issues:\n"
                    .implode("\n", $validationIssues)
                    ."\n\nPrevious output:\n".implode("\n", $bullets)
                    ."\n\nReturn exactly 5 corrected bullet lines only.";

                $repairParams = $params;
                $repairParams['messages'] = [
                    ['role' => 'user', 'content' => $repairPrompt],
                ];

                $repairResp = Http::withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])->timeout(90)->post($url, $repairParams);

                if ($repairResp->successful()) {
                    $repairText = data_get($repairResp->json(), 'content.0.text', '');
                    $repairBullets = $this->parseBulletsFromText($repairText);
                    if (count($repairBullets) >= 5) {
                        $bullets = $repairBullets;
                    }
                } else {
                    Log::warning('AI Generation Repair Failed', [
                        'status' => $repairResp->status(),
                        'body' => $repairResp->body(),
                    ]);
                }
            }

            if (count($bullets) < 5) {
                $bullets = array_pad($bullets, 5, '');
            }

            $bullets = array_map(fn ($b) => trim((string) $b), array_slice($bullets, 0, 5));

            Log::info('AI Response Lengths', ['bullets' => array_map(fn ($b) => mb_strlen((string) $b), $bullets)]);
            Log::info('AI Generation Success', ['bullet_points' => $bullets]);
            return response()->json([
                'success' => true,
                'bullets' => $bullets,
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Generation Failed', ['error' => $e->getMessage()]);
            Log::error('BulletPointMaster generateBulletPoints failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function generate(Request $request)
    {
        return $this->generateBulletPoints($request);
    }

    public function rewriteBulletPoint(Request $request)
    {
        try {
            $validated = $request->validate([
                'product_name' => 'required|string',
                'sku' => 'nullable|string',
                'prompt_details' => 'nullable|string|max:4000',
                'change_prompt' => 'required|string|max:2000',
                'bullet_index' => 'required|integer|min:1|max:5',
                'bullet_text' => 'nullable|string',
                'current_bullets' => 'required|array|size:5',
                'current_bullets.*' => 'nullable|string',
            ]);

            $productName = (string) $validated['product_name'];
            $sku = (string) ($validated['sku'] ?? '');
            $promptDetails = trim((string) ($validated['prompt_details'] ?? ''));
            $changePrompt = trim((string) $validated['change_prompt']);
            $bulletIndex = (int) $validated['bullet_index'];
            $bulletText = trim((string) $validated['bullet_text']);
            $currentBullets = array_map(
                fn ($value) => trim((string) $value),
                array_slice($validated['current_bullets'], 0, 5)
            );

            $apiKey = config('services.anthropic.key') ?: env('ANTHROPIC_API_KEY');
            if (! $apiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anthropic API key is not configured.',
                ], 422);
            }

            $formattedBullets = collect($currentBullets)
                ->map(fn ($bullet, $index) => ($index + 1).'. '.$bullet)
                ->implode("\n");

            $prompt = "Rewrite only bullet {$bulletIndex} for this product: {$productName}.\n" .
                ($sku !== '' ? "SKU: {$sku}\n" : '') .
                ($promptDetails !== '' ? "\nUSER PROVIDED PRODUCT DETAILS / KEYWORDS:\n{$promptDetails}\n" : '') .
                "\nCURRENT 5 BULLETS FOR CONTEXT:\n{$formattedBullets}\n\n" .
                "CURRENT BULLET {$bulletIndex}:\n{$bulletText}\n\n" .
                "USER CHANGE REQUEST FOR BULLET {$bulletIndex}:\n{$changePrompt}\n\n" .
                "IMPORTANT:\n" .
                "- Return only the rewritten bullet {$bulletIndex}; do not return the other bullets.\n" .
                "- Keep the same product facts and use the other bullets only as context so this point stays unique.\n" .
                "- Do not introduce random features, unsupported claims, pricing, shipping, warranty, or guarantees.\n" .
                "- The rewritten bullet must be 90-100 characters only.\n" .
                "- Start with an ALL-CAPS feature title followed by ' - '.\n" .
                "- Avoid these phrases: best, perfect, ideal, great for, suitable for.\n" .
                "- Plain text only. No numbering, no markdown, no bullet symbol.";

            $url = 'https://api.anthropic.com/v1/messages';
            $model = (string) config('services.anthropic.model', 'claude-sonnet-4-20250514');
            $params = [
                'model' => $model,
                'max_tokens' => 800,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ];

            Log::info('AI Bullet Rewrite Request', [
                'sku' => $sku,
                'bullet_index' => $bulletIndex,
                'model' => $model,
                'has_prompt_details' => $promptDetails !== '',
            ]);

            $resp = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(90)->post($url, $params);

            if (! $resp->successful()) {
                $errorMessage = (string) data_get($resp->json(), 'error.message', '');
                Log::error('AI Bullet Rewrite Failed', [
                    'status' => $resp->status(),
                    'body' => $resp->body(),
                    'model' => $model,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage !== '' ? $errorMessage : 'AI rewrite request failed',
                ], 500);
            }

            $text = data_get($resp->json(), 'content.0.text', '');
            $bullets = $this->parseBulletsFromText($text);
            $rewritten = trim((string) ($bullets[0] ?? $text));
            $rewritten = preg_replace('/^(?:[-*•●▪✅✔✓☑]+|\d+[.)])\s*/u', '', $rewritten) ?: $rewritten;
            $rewritten = trim(preg_replace('/\s+/', ' ', $rewritten) ?: $rewritten);

            $issues = $this->validateMarketplaceAiBullet($rewritten, $bulletIndex);
            if ($issues !== []) {
                $repairPrompt = $prompt."\n\nThe previous rewrite failed these rules:\n"
                    .implode("\n", $issues)
                    ."\n\nPrevious rewrite:\n{$rewritten}\n\nReturn one corrected bullet line only.";

                $repairParams = $params;
                $repairParams['messages'] = [
                    ['role' => 'user', 'content' => $repairPrompt],
                ];

                $repairResp = Http::withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])->timeout(90)->post($url, $repairParams);

                if ($repairResp->successful()) {
                    $repairText = data_get($repairResp->json(), 'content.0.text', '');
                    $repairBullets = $this->parseBulletsFromText($repairText);
                    $rewritten = trim((string) ($repairBullets[0] ?? $repairText));
                    $rewritten = preg_replace('/^(?:[-*•●▪✅✔✓☑]+|\d+[.)])\s*/u', '', $rewritten) ?: $rewritten;
                    $rewritten = trim(preg_replace('/\s+/', ' ', $rewritten) ?: $rewritten);
                }
            }

            Log::info('AI Bullet Rewrite Success', [
                'sku' => $sku,
                'bullet_index' => $bulletIndex,
                'length' => mb_strlen($rewritten),
            ]);

            return response()->json([
                'success' => true,
                'bullet' => $rewritten,
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Bullet Rewrite Exception', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function bulletPointAiPromptRules(): string
    {
        if (! Schema::hasTable('bullet_point_ai_prompt_rules')) {
            return $this->defaultBulletPointAiPromptRules();
        }

        $savedRules = BulletPointAiPromptRule::query()->whereKey(1)->value('rules');

        if (is_string($savedRules) && trim($savedRules) !== '') {
            return trim($savedRules);
        }

        return $this->defaultBulletPointAiPromptRules();
    }

    private function defaultBulletPointAiPromptRules(): string
    {
        return trim(implode("\n", [
            'REQUIREMENTS:',
            '- Each bullet point must contain 90-100 characters only, including the feature title and hyphen.',
            '- Start every bullet with an ALL-CAPS feature title followed by a hyphen.',
            '- Example format: STURDY KEYBOARD STAND - Durable metal construction provides reliable support.',
            '- Naturally incorporate relevant product keywords without keyword stuffing.',
            '- Prioritize customer benefits first, then support them with relevant product features.',
            '- Answer buyer questions: what problem it solves, what benefit they receive, why it is worth buying, and how it improves performance, comfort, convenience, or reliability.',
            '- Every bullet must communicate a unique selling point.',
            '- Distribute keywords naturally across all bullets for SEO coverage.',
            '- Focus equally on search relevance, readability, and conversion.',
            '- Use concise, persuasive, easy-to-understand language with a professional tone.',
            '',
            'STRICT RULES:',
            '- Do not repeat features, specifications, benefits, keywords, or phrases.',
            '- Do not mention pricing, discounts, promotions, shipping, guarantees, or warranty.',
            '- Do not use competitor comparisons, unsupported claims, or subjective superlatives.',
            '- Avoid filler words, generic marketing language, unnecessary adjectives, emojis, special characters, excessive punctuation, and keyword stuffing.',
            '- Avoid phrases such as best, perfect, ideal, great for, suitable for, or similar promotional language.',
            '- Return exactly 5 lines, one bullet per line, plain text only. No numbering, no markdown.',
            '',
            'OUTPUT FORMAT:',
            'FEATURE TITLE IN ALL CAPS - Benefit-focused description with naturally integrated keyword',
        ]));
    }

    private function cleanBulletText(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $cleaned = array_filter(array_map(
            fn ($line) => ShopifyBulletPointsFormatter::cleanBulletLine((string) $line),
            $lines
        ), fn ($line) => $line !== '');

        return implode("\n", $cleaned);
    }

    private function countBulletLines(string $text): int
    {
        return count(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', trim($text)) ?: []), fn ($line) => $line !== ''));
    }

    private function marketplaceTableMap(): array
    {
        return [
            'ebay' => 'ebay_metrics',
            'ebay2' => 'ebay_2_metrics',
            'ebay3' => 'ebay_3_metrics',
            'macy' => 'macy_metrics',
            'amazon' => 'amazon_metrics',
            'temu' => 'temu_metrics',
            'temu2' => 'temu2_metrics',
            'reverb' => 'reverb_metrics',
            'wayfair' => 'wayfair_metrics',
            'bestbuy' => 'bestbuy_metrics',
            'shopify_main' => 'shopify_metrics',
            'shopify_pls' => 'shopify_pls_metrics',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function loadShopifyTitlesBySku(): array
    {
        if (! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return [];
        }

        try {
            $rows = DB::table('shopify_catalog_variants as v')
                ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
                ->whereNotNull('v.sku')
                ->whereRaw('TRIM(COALESCE(v.sku, \'\')) <> \'\'')
                ->orderByDesc('v.synced_at')
                ->orderByDesc('v.id')
                ->selectRaw('TRIM(v.sku) as sku, p.title')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('BulletPointMaster: unable to load Shopify titles', ['error' => $e->getMessage()]);
            return [];
        }

        $titles = [];
        foreach ($rows as $row) {
            $sku = mb_strtolower($this->normalizeSku((string) ($row->sku ?? '')));
            $title = trim((string) ($row->title ?? ''));
            if ($sku !== '' && $title !== '' && ! isset($titles[$sku])) {
                $titles[$sku] = $title;
            }
        }

        return $titles;
    }

    private function loadMetricsBySku(string $table): array
    {
        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
                return [];
            }

            $hasBp = Schema::hasColumn($table, 'bullet_points');
            if (! $hasBp) {
                return [];
            }

            return DB::table($table)
                ->select('sku', 'bullet_points')
                ->whereNotNull('sku')
                ->get()
                ->mapWithKeys(function ($row) {
                    return [$this->normalizeSku($row->sku) => (string) ($row->bullet_points ?? '')];
                })
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning("Unable to load table {$table}", ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function loadPushStatusesBySku(): array
    {
        if (! Schema::hasTable('bullet_point_marketplace_push_statuses')) {
            return [];
        }

        try {
            $statuses = [];
            DB::table('bullet_point_marketplace_push_statuses')
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
            Log::warning('Unable to load bullet point push statuses', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function savePushStatus(string $sku, string $marketplace, string $status, string $message = ''): void
    {
        if (! Schema::hasTable('bullet_point_marketplace_push_statuses')) {
            return;
        }

        try {
            DB::table('bullet_point_marketplace_push_statuses')->updateOrInsert(
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
            Log::warning('Unable to save bullet point push status', [
                'sku' => $sku,
                'marketplace' => $marketplace,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function saveToMarketplaceTable(string $marketplace, string $sku, string $text): bool
    {
        $text = $this->cleanBulletText($text);
        $table = $this->marketplaceTableMap()[$marketplace] ?? null;
        if (! $table) {
            return false;
        }

        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku') || ! Schema::hasColumn($table, 'bullet_points')) {
                return false;
            }

            $update = ['bullet_points' => $text];
            if (Schema::hasColumn($table, 'updated_at')) {
                $update['updated_at'] = now();
            }
            if (in_array($table, ['temu_metrics', 'temu2_metrics'], true) && Schema::hasColumn($table, 'goods_summary')) {
                $update['goods_summary'] = $text;
            }

            $existing = DB::table($table)->where('sku', $sku)->first();
            if ($existing) {
                DB::table($table)->where('sku', $sku)->update($update);
            } else {
                $insert = ['sku' => $sku, 'bullet_points' => $text];
                if (Schema::hasColumn($table, 'created_at')) {
                    $insert['created_at'] = now();
                }
                if (Schema::hasColumn($table, 'updated_at')) {
                    $insert['updated_at'] = now();
                }
                if (in_array($table, ['temu_metrics', 'temu2_metrics'], true) && Schema::hasColumn($table, 'goods_summary')) {
                    $insert['goods_summary'] = $text;
                }
                DB::table($table)->insert($insert);
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning("Unable to save bullet points to {$table}", ['sku' => $sku, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function shopifyBulletPullLogger(): \Psr\Log\LoggerInterface
    {
        return Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/shopify-bullet-pull.log'),
            'level' => 'debug',
        ]);
    }

    private function loadMasterBulletTextForSku(string $sku): ?string
    {
        $normalizedSku = $this->normalizeSku($sku);
        if ($normalizedSku === '') {
            return null;
        }

        try {
            $skuWithNbsp = str_replace(' ', "\u{00a0}", $normalizedSku);
            $product = ProductMaster::query()
                ->where('sku', $normalizedSku)
                ->orWhere('sku', strtoupper($normalizedSku))
                ->orWhere('sku', strtolower($normalizedSku))
                ->orWhere('sku', $skuWithNbsp)
                ->first();

            if (! $product) {
                return null;
            }

            $parts = array_filter(array_map(
                fn ($line) => ShopifyBulletPointsFormatter::cleanBulletLine((string) $line),
                [
                $product->bullet1 ?? '',
                $product->bullet2 ?? '',
                $product->bullet3 ?? '',
                $product->bullet4 ?? '',
                $product->bullet5 ?? '',
                ]
            ), fn ($line) => $line !== '');

            return implode("\n", $parts);
        } catch (\Throwable $e) {
            Log::warning('BulletPointMaster: unable to load master bullets for push', [
                'sku' => $normalizedSku,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function findProductMasterBySku(string $sku): ?ProductMaster
    {
        $normalizedSku = $this->normalizeSku($sku);
        $skuWithNbsp = str_replace(' ', "\u{00a0}", $normalizedSku);

        return ProductMaster::query()
            ->where('sku', $normalizedSku)
            ->orWhere('sku', strtoupper($normalizedSku))
            ->orWhere('sku', strtolower($normalizedSku))
            ->orWhere('sku', $skuWithNbsp)
            ->first();
    }

    /**
     * @return array{success: bool, message?: string, body_html?: string, product_id?: string, variant_id?: string}
     */
    private function fetchShopifyBodyHtmlForSku(string $sku): array
    {
        $mapping = $this->resolveShopifyMappingForSku($sku);
        $store = (string) ($mapping['store'] ?? 'main');
        if ($store === 'pls') {
            $plsTokenService = app(ShopifyPlsTokenService::class);
            $domain = $plsTokenService->getDomain();
            $token = $plsTokenService->getAccessToken();
        } else {
            $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');
            $token = config('services.shopify.access_token') ?: config('services.shopify.password');
        }

        if (! $domain || ! $token) {
            return ['success' => false, 'message' => strtoupper($store).' Shopify credentials not configured.'];
        }

        $domain = rtrim(preg_replace('#^https?://#', '', (string) $domain), '/');
        $variantId = $mapping['variant_id'] ?? null;
        $productId = $mapping['product_id'] ?? null;
        if (! $variantId) {
            return ['success' => false, 'message' => 'Shopify variant mapping not found.'];
        }

        if (! $productId) {
            $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$variantId}.json";
            $variantRes = $this->shopifyPullAdminGet($variantUrl, (string) $token);

            if (! $variantRes->successful()) {
                return ['success' => false, 'message' => 'Variant lookup failed: '.$variantRes->body(), 'variant_id' => (string) $variantId];
            }

            $productId = $variantRes->json('variant.product_id');
        }
        if (! $productId) {
            return ['success' => false, 'message' => 'Product ID missing from Shopify variant.', 'variant_id' => (string) $variantId];
        }

        $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
        $productRes = $this->shopifyPullAdminGet($productUrl, (string) $token);

        if (! $productRes->successful()) {
            return [
                'success' => false,
                'message' => 'Product fetch failed: '.$productRes->body(),
                'variant_id' => (string) $variantId,
                'product_id' => (string) $productId,
            ];
        }

        return [
            'success' => true,
            'body_html' => (string) ($productRes->json('product.body_html') ?? ''),
            'variant_id' => (string) $variantId,
            'product_id' => (string) $productId,
            'store' => $store,
        ];
    }

    /**
     * @return array{body_html?: string, product_id?: string, variant_id?: string, store?: string}
     */
    private function fetchCachedShopifyBodyHtmlForSku(string $sku): array
    {
        if (! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return [];
        }

        $row = DB::table('shopify_catalog_variants as v')
            ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
            ->whereRaw('LOWER(TRIM(COALESCE(v.sku, \'\'))) = ?', [mb_strtolower(trim($sku))])
            ->orderByDesc('v.synced_at')
            ->orderByDesc('v.id')
            ->select('v.store', 'v.shopify_variant_id', 'v.shopify_product_id', 'p.body_html')
            ->first();

        if (! $row) {
            return [];
        }

        return [
            'body_html' => (string) ($row->body_html ?? ''),
            'product_id' => $row->shopify_product_id ? (string) $row->shopify_product_id : null,
            'variant_id' => $row->shopify_variant_id ? (string) $row->shopify_variant_id : null,
            'store' => (string) ($row->store ?? ''),
        ];
    }

    /**
     * @return array{body_html?: string, url?: string}
     */
    private function fetchPublicShopifyProductHtmlForSku(string $sku): array
    {
        if (! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return [];
        }

        $row = DB::table('shopify_catalog_variants as v')
            ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
            ->whereRaw('LOWER(TRIM(COALESCE(v.sku, \'\'))) = ?', [mb_strtolower(trim($sku))])
            ->orderByDesc('v.synced_at')
            ->orderByDesc('v.id')
            ->select('p.handle')
            ->first();

        $handle = trim((string) ($row->handle ?? ''));
        if ($handle === '') {
            return [];
        }

        $domains = array_values(array_unique(array_filter([
            config('services.shopify_5core.domain'),
            'www.5core.com',
        ])));

        foreach ($domains as $domain) {
            $domain = rtrim(preg_replace('#^https?://#', '', (string) $domain), '/');
            if ($domain === '') {
                continue;
            }

            $url = "https://{$domain}/products/{$handle}.js";
            try {
                $response = Http::timeout(30)->connectTimeout(15)->get($url);
            } catch (\Throwable $e) {
                Log::warning('BulletPointMaster: public Shopify product fetch exception', [
                    'sku' => $sku,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $description = (string) ($response->json('description') ?? '');
            if (trim($description) !== '') {
                return [
                    'body_html' => $description,
                    'url' => $url,
                ];
            }
        }

        return [];
    }

    private function launchShopifyPullProcess(): void
    {
        $php = PHP_BINARY;
        $artisan = base_path('artisan');

        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            $cmd = 'start /B "" "'.$php.'" "'.$artisan.'" bullet-points:shopify-pull-run > NUL 2>&1';
            pclose(popen($cmd, 'r'));

            return;
        }

        $cmd = escapeshellarg($php).' '.escapeshellarg($artisan).' bullet-points:shopify-pull-run > /dev/null 2>&1 &';
        exec($cmd);
    }

    private function shopifyPullAdminGet(string $url, string $token): \Illuminate\Http\Client\Response
    {
        $last = null;
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $last = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->connectTimeout(15)->get($url);

            if ($last->status() !== 429 || $attempt >= 6) {
                return $last;
            }

            $retryAfter = $last->header('Retry-After');
            $waitMs = is_numeric($retryAfter)
                ? (int) ((float) $retryAfter * 1000000)
                : 2000000 * $attempt;
            usleep(max(2000000, min(10000000, $waitMs)));
        }

        return $last;
    }

    private function resolveShopifyVariantIdForSku(string $sku): ?string
    {
        $mapping = $this->resolveShopifyMappingForSku($sku);
        return $mapping['variant_id'] ?? null;
    }

    /**
     * @return array{variant_id?: string, product_id?: string}
     */
    private function resolveShopifyMappingForSku(string $sku): array
    {
        $trim = $this->normalizeSku($sku);
        if ($trim === '') {
            return [];
        }

        $lowerSku = mb_strtolower($trim);
        if (Schema::hasTable('shopify_catalog_variants')) {
            $catalogRow = DB::table('shopify_catalog_variants')
                ->whereRaw('LOWER(TRIM(COALESCE(sku, \'\'))) = ?', [$lowerSku])
                ->orderByDesc('synced_at')
                ->orderByDesc('id')
                ->first();
            if ($catalogRow && $catalogRow->shopify_variant_id) {
                return array_filter([
                    'variant_id' => (string) $catalogRow->shopify_variant_id,
                    'product_id' => $catalogRow->shopify_product_id ? (string) $catalogRow->shopify_product_id : null,
                    'store' => (string) ($catalogRow->store ?? 'main'),
                ]);
            }

            $cat = ShopifyVariant::query()
                ->whereRaw('LOWER(TRIM(COALESCE(sku, \'\'))) = ?', [$lowerSku])
                ->orderByDesc('synced_at')
                ->orderByDesc('id')
                ->first();
            if ($cat && $cat->shopify_variant_id) {
                return array_filter([
                    'variant_id' => (string) $cat->shopify_variant_id,
                    'product_id' => $cat->shopify_product_id ? (string) $cat->shopify_product_id : null,
                    'store' => (string) ($cat->store ?? 'main'),
                ]);
            }
        }

        $row = ShopifySku::query()
            ->where('sku', $trim)
            ->orWhereRaw('LOWER(TRIM(COALESCE(sku, \'\'))) = ?', [$lowerSku])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        return $row && $row->variant_id ? ['variant_id' => (string) $row->variant_id, 'store' => 'main'] : [];
    }

    /**
     * @return list<string>
     */
    private function productMasterBulletArray(?ProductMaster $product): array
    {
        if (! $product) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($line) => ShopifyBulletPointsFormatter::cleanBulletLine((string) $line),
            [
            $product->bullet1 ?? '',
            $product->bullet2 ?? '',
            $product->bullet3 ?? '',
            $product->bullet4 ?? '',
            $product->bullet5 ?? '',
            ]
        ), fn ($line) => $line !== ''));
    }

    /**
     * @param  list<string>  $bullets
     * @return list<string>
     */
    private function normalizedBulletArray(array $bullets): array
    {
        return array_values(array_map(function ($line) {
            $line = html_entity_decode((string) $line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $line = ShopifyBulletPointsFormatter::cleanBulletLine($line);
            $line = str_replace("\xc2\xa0", ' ', $line);
            $line = preg_replace('/\s+/u', ' ', $line) ?? $line;

            return mb_strtolower(trim($line));
        }, $bullets));
    }

    private function callMarketplaceService(string $marketplace, string $sku, string $text): array
    {
        $serviceMap = [
            'ebay' => \App\Services\EbayApiService::class,
            'ebay2' => \App\Services\Ebay2ApiService::class,
            'ebay3' => \App\Services\EbayThreeApiService::class,
            'macy' => \App\Services\MacysApiService::class,
            'amazon' => \App\Services\AmazonSpApiService::class,
            'temu' => \App\Services\TemuApiService::class,
            'temu2' => \App\Services\Temu2ApiService::class,
            'reverb' => \App\Services\ReverbApiService::class,
            'wayfair' => \App\Services\WayfairApiService::class,
            'bestbuy' => \App\Services\BestBuyApiService::class,
            'shopify_main' => \App\Services\ShopifyApiService::class,
            'shopify_pls' => \App\Services\ShopifyPLSApiService::class,
        ];

        try {
            $serviceClass = $serviceMap[$marketplace] ?? null;
            if (! $serviceClass || ! class_exists($serviceClass)) {
                return ['success' => false, 'message' => 'Service not available'];
            }

            $service = app($serviceClass);
            if (! method_exists($service, 'updateBulletPoints')) {
                return ['success' => false, 'message' => 'Service does not support bullet point update'];
            }

            $result = $service->updateBulletPoints($sku, $text);
            if (is_array($result)) {
                return $result + ['success' => false, 'message' => 'Unknown service response'];
            }
            if (is_bool($result)) {
                return ['success' => $result, 'message' => $result ? 'Updated' : 'Failed'];
            }
            return ['success' => false, 'message' => 'Unexpected service response'];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'not found') && str_contains($msg, 'Class')) {
                Log::error('Marketplace service failed: missing class or autoload issue', [
                    'marketplace' => $marketplace,
                    'sku' => $sku,
                    'error' => $msg,
                ]);
            } else {
                Log::warning('Marketplace service update failed', [
                    'marketplace' => $marketplace,
                    'sku' => $sku,
                    'error' => $msg,
                ]);
            }

            return ['success' => false, 'message' => $msg];
        }
    }

    /**
     * Fails soft (log only) if expected marketplace Eloquent classes are missing.
     */
    private function ensureMarketplaceModelClassesResolvable(): void
    {
        $classes = [
            \App\Models\ReverbListing::class,
            \App\Models\ShopifyProduct::class,
            \App\Models\ShopifyVariant::class,
            \App\Models\ShopifyPlsProduct::class,
            \App\Models\ShopifyPlsVariant::class,
        ];

        foreach ($classes as $class) {
            if (! class_exists($class)) {
                Log::warning('BulletPointMaster: marketplace model class missing', ['class' => $class]);
            }
        }
    }

    private function parseBulletsFromText(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text));
        $bullets = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/^(?:[-*•●▪✅✔✓☑]+|\d+[.)])\s*/u', '', $line));
            if ($line !== '') {
                $bullets[] = $line;
            }
        }

        if (count($bullets) === 0 && trim($text) !== '') {
            $parts = preg_split('/\s*[;\|]\s*/', trim($text));
            foreach ($parts as $p) {
                if (trim($p) !== '') {
                    $bullets[] = trim($p);
                }
            }
        }

        return array_values(array_unique(array_slice($bullets, 0, 5)));
    }

    /**
     * @param array<int, string> $bullets
     * @return array<int, string>
     */
    private function validateMarketplaceAiBullets(array $bullets): array
    {
        $issues = [];
        if (count($bullets) !== 5) {
            $issues[] = 'Output must contain exactly 5 bullet points.';
        }

        $bannedPhrases = [
            'best',
            'perfect',
            'ideal',
            'great for',
            'suitable for',
            'warranty',
            'guarantee',
            'shipping',
            'discount',
            'promotion',
        ];

        foreach (array_slice($bullets, 0, 5) as $index => $bullet) {
            $lineNo = $index + 1;
            $length = mb_strlen($bullet);
            if ($length < 90 || $length > 100) {
                $issues[] = "Bullet {$lineNo} must be 90-100 characters; current length is {$length}.";
            }

            if (preg_match('/^[A-Z0-9][A-Z0-9 &\/+.]{2,60}\s-\s\S/u', $bullet) !== 1) {
                $issues[] = "Bullet {$lineNo} must start with an ALL-CAPS feature title followed by ' - '.";
            }

            $lower = mb_strtolower($bullet);
            foreach ($bannedPhrases as $phrase) {
                if (str_contains($lower, $phrase)) {
                    $issues[] = "Bullet {$lineNo} uses banned phrase '{$phrase}'.";
                    break;
                }
            }
        }

        return $issues;
    }

    /**
     * @return array<int, string>
     */
    private function validateMarketplaceAiBullet(string $bullet, int $lineNo): array
    {
        $issues = [];
        $length = mb_strlen($bullet);
        if ($length < 90 || $length > 100) {
            $issues[] = "Bullet {$lineNo} must be 90-100 characters; current length is {$length}.";
        }

        if (preg_match('/^[A-Z0-9][A-Z0-9 &\/+.]{2,60}\s-\s\S/u', $bullet) !== 1) {
            $issues[] = "Bullet {$lineNo} must start with an ALL-CAPS feature title followed by ' - '.";
        }

        $bannedPhrases = [
            'best',
            'perfect',
            'ideal',
            'great for',
            'suitable for',
            'warranty',
            'guarantee',
            'shipping',
            'discount',
            'promotion',
        ];

        $lower = mb_strtolower($bullet);
        foreach ($bannedPhrases as $phrase) {
            if (str_contains($lower, $phrase)) {
                $issues[] = "Bullet {$lineNo} uses banned phrase '{$phrase}'.";
                break;
            }
        }

        return $issues;
    }

    private function normalizeSku(?string $sku): string
    {
        if (! $sku) {
            return '';
        }

        return str_replace("\u{00a0}", ' ', trim((string) $sku));
    }

    private function combineBullets(?string $b1, ?string $b2, ?string $b3, ?string $b4, ?string $b5): string
    {
        $parts = array_filter(array_map('trim', [$b1 ?? '', $b2 ?? '', $b3 ?? '', $b4 ?? '', $b5 ?? '']));
        return implode(' ', $parts);
    }
}
