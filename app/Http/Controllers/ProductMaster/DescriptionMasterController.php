<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Services\AmazonSpApiService;
use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use App\Services\MacysApiService;
use App\Services\ReverbApiService;
use App\Services\ShopifyApiService;
use App\Services\ShopifyPLSApiService;
use App\Services\Support\DescriptionWithImagesFormatter;
use App\Services\TemuApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DescriptionMasterController extends Controller
{
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
            @set_time_limit(120);
            @ini_set('memory_limit', '256M');

            $perPage = min(max((int) $request->query('per_page', 75), 10), 100);
            $page = max((int) $request->query('page', 1), 1);
            $qSku = trim((string) $request->query('q_sku', ''));
            $qText = trim((string) $request->query('q_text', ''));

            $query = ProductMaster::query()
                ->orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->whereRaw("UPPER(COALESCE(sku, '')) NOT LIKE '%PARENT%'");

            if ($qSku !== '') {
                $safe = addcslashes($qSku, '%_\\');
                $query->where('sku', 'like', '%'.$safe.'%');
            }
            if ($qText !== '') {
                $safe = addcslashes($qText, '%_\\');
                $like = '%'.$safe.'%';
                $query->where(function ($w) use ($like) {
                    $w->where('parent', 'like', $like)
                        ->orWhere('product_description', 'like', $like)
                        ->orWhere('description_1500', 'like', $like)
                        ->orWhere('description_1000', 'like', $like)
                        ->orWhere('description_800', 'like', $like)
                        ->orWhere('description_600', 'like', $like);
                });
            }

            $query->select([
                'id', 'parent', 'sku', 'title150',
                'bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5',
                'product_description', 'description_1500', 'description_1000', 'description_800', 'description_600',
            ]);
            if (Schema::hasColumn('product_master', 'amazon_aplus_content')) {
                $query->addSelect('amazon_aplus_content');
            }
            if (Schema::hasColumn('product_master', 'amazon_aplus_images')) {
                $query->addSelect('amazon_aplus_images');
            }

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);
            $products = $paginator->items();

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

            $bulletsShopifyMain = [];
            $bulletsShopifyPls = [];
            if (Schema::hasTable('shopify_metrics') && Schema::hasColumn('shopify_metrics', 'bullet_points')) {
                $bulletsShopifyMain = $this->loadBulletPointsForSkuList('shopify_metrics', $rawSkus);
            }
            if (Schema::hasTable('shopify_pls_metrics') && Schema::hasColumn('shopify_pls_metrics', 'bullet_points')) {
                $bulletsShopifyPls = $this->loadBulletPointsForSkuList('shopify_pls_metrics', $rawSkus);
            }

            $result = [];
            foreach ($products as $product) {
                $sku = $this->normalizeSku($product->sku);
                $row = [
                    'id' => $product->id,
                    'Parent' => $product->parent,
                    'SKU' => $product->sku,
                    'title150' => $product->title150,
                    'bullet1' => $product->bullet1,
                    'bullet2' => $product->bullet2,
                    'bullet3' => $product->bullet3,
                    'bullet4' => $product->bullet4,
                    'bullet5' => $product->bullet5,
                    'product_description' => $product->product_description,
                    'description_1500' => $product->description_1500,
                    'description_1000' => $product->description_1000,
                    'description_800' => $product->description_800,
                    'description_600' => $product->description_600,
                    'amazon_aplus_content' => $product->amazon_aplus_content ?? '',
                    'amazon_aplus_images' => $product->amazon_aplus_images ?? null,
                ];
                $desc = [];
                foreach (array_keys($marketTables) as $mp) {
                    $desc[$mp] = $descriptionsByMp[$mp][$sku] ?? '';
                }
                $row['descriptions'] = $desc;
                $row['shopify_main_bullets'] = $bulletsShopifyMain[$sku] ?? $this->defaultBulletsFromPmArray($row);
                $row['shopify_pls_bullets'] = $bulletsShopifyPls[$sku] ?? $this->defaultBulletsFromPmArray($row);
                $result[] = $row;
            }

            return response()->json([
                'message' => 'Description Master data loaded',
                'data' => $result,
                'status' => 200,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
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
                'updates.*.image_urls' => 'nullable|array',
                'updates.*.image_urls.*' => 'nullable|string',
            ]);

            $sku = $this->normalizeSku($validated['sku']);
            $results = [];
            $allowedMarketplaces = array_keys($this->marketplaceTableMap());

            foreach ($validated['updates'] as $u) {
                $marketplace = strtolower(trim($u['marketplace']));
                $text = trim((string) ($u['description'] ?? ''));
                $imageUrls = array_values(array_filter(array_map('trim', (array) ($u['image_urls'] ?? []))));
                if ($text === '') {
                    $results[$marketplace] = ['success' => false, 'message' => 'Description cannot be empty'];

                    continue;
                }

                if (! in_array($marketplace, $allowedMarketplaces, true)) {
                    $results[$marketplace] = ['success' => false, 'message' => 'Unknown or unsupported marketplace'];

                    continue;
                }

                $max = $this->maxCharsForMarketplace($marketplace);
                if (mb_strlen($text) > $max) {
                    $results[$marketplace] = ['success' => false, 'message' => "Description exceeds {$max} characters for this marketplace."];

                    continue;
                }

                $this->saveDescriptionToMarketplaceTable($marketplace, $sku, $text);
                $serviceResult = $this->callMarketplaceDescriptionService($marketplace, $sku, $text, $imageUrls);

                $success = (bool) ($serviceResult['success'] ?? false);
                $results[$marketplace] = [
                    'success' => $success,
                    'message' => $serviceResult['message'] ?? ($success ? 'Updated' : 'Update failed'),
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
     * POST /product-description/generate — Anthropic Claude; tier sets min/max length (1500 / 1000 / 800 / 600 groups).
     */
    public function generateDescriptionWithAI(Request $request)
    {
        try {
            $validated = $request->validate([
                'product_name' => 'required|string',
                'current_text' => 'nullable|string',
                'tier' => 'nullable|string|in:1500,1000,800,600',
            ]);

            $productName = $validated['product_name'];
            $current = trim((string) ($validated['current_text'] ?? ''));
            $tier = $validated['tier'] ?? '1500';

            [$minLen, $maxLen] = match ($tier) {
                '1000' => [900, 1000],
                '800' => [700, 800],
                '600' => [500, 600],
                default => [1400, 1500],
            };

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
                'tier' => $tier,
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
                if ($update !== []) {
                    ProductMaster::query()->where('sku', $sku)->update($update);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Amazon A+ content fetched.',
                'data' => $data,
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

    private function marketplaceTableMap(): array
    {
        return [
            'amazon' => 'amazon_metrics',
            'temu' => 'temu_metrics',
            'reverb' => 'reverb_metrics',
            'shopify_main' => 'shopify_metrics',
            'shopify_pls' => 'shopify_pls_metrics',
            'ebay' => 'ebay_metrics',
            'ebay2' => 'ebay_2_metrics',
            'ebay3' => 'ebay_3_metrics',
            'macy' => 'macy_metrics',
        ];
    }

    private function maxCharsForMarketplace(string $marketplace): int
    {
        return match ($marketplace) {
            'amazon', 'temu', 'reverb' => 1500,
            'shopify_main', 'shopify_pls' => 1000,
            'ebay', 'ebay2', 'ebay3' => 800,
            'macy' => 600,
            default => 1500,
        };
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

        $map = [
            'amazon' => [AmazonSpApiService::class, 'updateAplusContent'],
            'temu' => [TemuApiService::class, 'updateDescription'],
            'reverb' => [ReverbApiService::class, 'updateDescription'],
            'macy' => [MacysApiService::class, 'updateDescription'],
            'ebay' => [EbayApiService::class, 'updateDescription'],
            'ebay2' => [Ebay2ApiService::class, 'updateDescription'],
            'ebay3' => [EbayThreeApiService::class, 'updateDescription'],
        ];

        try {
            if (! isset($map[$marketplace])) {
                return ['success' => false, 'message' => 'Unknown marketplace'];
            }
            [$class, $method] = $map[$marketplace];
            $service = app($class);
            if (! method_exists($service, $method)) {
                return ['success' => false, 'message' => 'Service method not available'];
            }

            $result = $service->{$method}($sku, $text, $imageUrls);
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
