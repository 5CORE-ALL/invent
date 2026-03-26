<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Services\AliExpressApiService;
use App\Services\AmazonSpApiService;
use App\Services\BestBuyApiService;
use App\Services\DobaApiService;
use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use App\Services\FaireService;
use App\Services\MacysApiService;
use App\Services\ReverbApiService;
use App\Services\SheinApiService;
use App\Services\ShopifyApiService;
use App\Services\ShopifyPLSApiService;
use App\Services\TemuApiService;
use App\Services\WalmartService;
use App\Services\WayfairApiService;
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
            Log::error('getDescriptionMasterData failed', ['error' => $e->getMessage()]);

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
            ]);

            $sku = $this->normalizeSku($validated['sku']);
            $results = [];

            foreach ($validated['updates'] as $u) {
                $marketplace = strtolower(trim($u['marketplace']));
                $text = trim((string) ($u['description'] ?? ''));
                if ($text === '') {
                    $results[$marketplace] = ['success' => false, 'message' => 'Description cannot be empty'];
                    continue;
                }

                $max = $this->maxCharsForMarketplace($marketplace);
                if (mb_strlen($text) > $max) {
                    $results[$marketplace] = ['success' => false, 'message' => "Description exceeds {$max} characters for this marketplace."];
                    continue;
                }

                $this->saveDescriptionToMarketplaceTable($marketplace, $sku, $text);
                $serviceResult = $this->callMarketplaceDescriptionService($marketplace, $sku, $text);

                $success = (bool) ($serviceResult['success'] ?? false);
                $results[$marketplace] = [
                    'success' => $success,
                    'message' => $serviceResult['message'] ?? ($success ? 'Updated' : 'Update failed'),
                ];
            }

            $totalSuccess = collect($results)->where('success', true)->count();
            $totalFailed = collect($results)->where('success', false)->count();

            return response()->json([
                'success' => $totalFailed === 0,
                'results' => $results,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'message' => "Updated {$totalSuccess} marketplace(s).".($totalFailed > 0 ? " {$totalFailed} failed." : ''),
            ]);
        } catch (\Throwable $e) {
            Log::error('pushDescriptionToMarketplaces failed', ['error' => $e->getMessage()]);

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
                "Include features, benefits, specifications, use cases, and quality highlights. ".
                "Make it comprehensive and persuasive. Plain text only (no HTML, no markdown headings).";

            $apiKey = config('services.anthropic.key') ?: env('ANTHROPIC_API_KEY');
            if (! $apiKey) {
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
            Log::error('generateDescriptionWithAI failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function marketplaceTableMap(): array
    {
        return [
            'shopify_main' => 'shopify_metrics',
            'shopify_pls' => 'shopify_pls_metrics',
            'ebay' => 'ebay_metrics',
            'ebay2' => 'ebay_2_metrics',
            'ebay3' => 'ebay_3_metrics',
            'temu' => 'temu_metrics',
            'amazon' => 'amazon_metrics',
            'reverb' => 'reverb_metrics',
            'walmart' => 'walmart_metrics',
            'macy' => 'macy_metrics',
            'aliexpress' => 'aliexpress_metrics',
            'faire' => 'faire_metrics',
            'bestbuy' => 'bestbuy_metrics',
            'wayfair' => 'wayfair_metrics',
            'shein' => 'shein_metrics',
            'doba' => 'doba_metrics',
        ];
    }

    private function maxCharsForMarketplace(string $marketplace): int
    {
        return match ($marketplace) {
            'amazon', 'temu', 'reverb', 'wayfair', 'walmart', 'aliexpress', 'shein', 'bestbuy' => 1500,
            'shopify_main', 'shopify_pls', 'doba' => 1000,
            'ebay', 'ebay2', 'ebay3' => 800,
            'macy', 'faire' => 600,
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

    private function getBulletPlainForShopify(string $sku, string $marketplace): string
    {
        $table = $marketplace === 'shopify_main' ? 'shopify_metrics' : 'shopify_pls_metrics';
        if (Schema::hasTable($table) && Schema::hasColumn($table, 'bullet_points')) {
            $row = DB::table($table)->where('sku', $sku)->first();
            if ($row && ! empty($row->bullet_points)) {
                return (string) $row->bullet_points;
            }
        }

        $pm = ProductMaster::query()
            ->where('sku', $sku)
            ->orWhere('sku', strtoupper($sku))
            ->orWhere('sku', strtolower($sku))
            ->first();
        if ($pm) {
            return implode("\n", array_filter(array_map('trim', [
                $pm->bullet1, $pm->bullet2, $pm->bullet3, $pm->bullet4, $pm->bullet5,
            ])));
        }

        return '';
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function callMarketplaceDescriptionService(string $marketplace, string $sku, string $text): array
    {
        if ($marketplace === 'shopify_main') {
            $bullets = $this->getBulletPlainForShopify($sku, 'shopify_main');

            return app(ShopifyApiService::class)->updateProductDescriptionWithBullets($sku, $bullets, $text);
        }
        if ($marketplace === 'shopify_pls') {
            $bullets = $this->getBulletPlainForShopify($sku, 'shopify_pls');

            return app(ShopifyPLSApiService::class)->updateProductDescriptionWithBullets($sku, $bullets, $text);
        }

        $map = [
            'amazon' => [AmazonSpApiService::class, 'updateProductDescription'],
            'temu' => [TemuApiService::class, 'updateProductDescription'],
            'reverb' => [ReverbApiService::class, 'updateProductDescription'],
            'walmart' => [WalmartService::class, 'updateProductDescription'],
            'macy' => [MacysApiService::class, 'updateProductDescription'],
            'aliexpress' => [AliExpressApiService::class, 'updateProductDescription'],
            'faire' => [FaireService::class, 'updateProductDescription'],
            'doba' => [DobaApiService::class, 'updateProductDescription'],
            'wayfair' => [WayfairApiService::class, 'updateProductDescription'],
            'shein' => [SheinApiService::class, 'updateProductDescription'],
            'bestbuy' => [BestBuyApiService::class, 'updateProductDescription'],
            'ebay' => [EbayApiService::class, 'updateProductDescription'],
            'ebay2' => [Ebay2ApiService::class, 'updateProductDescription'],
            'ebay3' => [EbayThreeApiService::class, 'updateProductDescription'],
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

            $result = $service->{$method}($sku, $text);
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
}
